<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

use Illuminate\Database\Connection;
use Swis\Laravel\DatabaseGuard\Exemptions\Condition;

/**
 * One per connection for the whole run. Introspection goes straight to PDO:
 * no re-entry into the guard, no query log, and real results under --pretend.
 */
final class AnalysisContext
{
    /**
     * @var array<string, bool>
     */
    private array $created = [];

    /**
     * @var array<string, int>
     */
    private array $rows = [];

    /**
     * @var array<string, string|null>
     */
    private array $pretendSession = [];

    private ?ServerCapabilities $server = null;

    public function __construct(public readonly Connection $connection) {}

    /**
     * Called before the statement executes.
     */
    public function observe(ParsedStatement $statement): void
    {
        if ($statement->kind === StatementKind::SetVariable) {
            if ($statement->variable !== null) {
                $this->pretendSession[$statement->variable] = $statement->value;
            }

            return;
        }

        if ($statement->table === null) {
            return;
        }

        $key = $this->key($statement->table);

        match ($statement->kind) {
            StatementKind::CreateTable => $this->created[$key] = true,
            StatementKind::DropTable => $this->created[$key] = false,
            default => null,
        };

        if (in_array($statement->kind, [StatementKind::CreateTable, StatementKind::DropTable, StatementKind::Truncate], true)) {
            unset($this->rows[$key]);
        }
    }

    public function isNewTable(TableReference $table): bool
    {
        return $this->created[$this->key($table)] ?? false;
    }

    /**
     * Live session value.
     *
     * Under --pretend SETs are not executed, so use the observed ones.
     */
    public function sessionVariable(string $name): string
    {
        $name = strtolower($name);

        if (! preg_match('/^\w+$/', $name)) {
            throw new \InvalidArgumentException("Invalid variable name: {$name}");
        }

        if ($this->connection->pretending()
            && isset($this->pretendSession[$name])
            && strtoupper($this->pretendSession[$name]) !== 'DEFAULT') {
            return $this->pretendSession[$name];
        }

        return $this->fetchString("select @@SESSION.{$name}");
    }

    /**
     * TABLE_ROWS is an InnoDB estimate: fine for thresholds, no COUNT(*) on big tables.
     */
    public function tableRows(TableReference $table): int
    {
        return $this->rows[$this->key($table)] ??= (function () use ($table): int {
            // Default expiry is 24h in 8.0; stale counts would defeat the threshold.
            $this->connection->getPdo()->exec('SET SESSION information_schema_stats_expiry = 0');

            $rows = $this->fetchScalar('select TABLE_ROWS from information_schema.TABLES where TABLE_SCHEMA = ? and TABLE_NAME = ?', [
                $table->schema ?? $this->connection->getDatabaseName(),
                $table->name,
            ]);

            return match (true) {
                $rows === false,
                $rows === null => 0,
                is_int($rows) => $rows,
                ctype_digit($rows) => (int) $rows,
                default => throw new \UnexpectedValueException('Expected TABLE_ROWS to be an integer, got '.get_debug_type($rows).'.'),
            };
        })();
    }

    public function server(): ServerCapabilities
    {
        return $this->server ??= ServerCapabilities::fromVersion($this->fetchString('select version()'));
    }

    /**
     * @param  class-string<Condition>  $condition
     * @return array<array-key, mixed>
     */
    public function conditionSettings(string $condition): array
    {
        $settings = config()->array('database-guard.conditions', [])[$condition] ?? [];

        if (! is_array($settings)) {
            throw new \UnexpectedValueException("The database-guard.conditions setting of {$condition} must be an array.");
        }

        return $settings;
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    private function fetchScalar(string $sql, array $bindings = []): string|int|false|null
    {
        $statement = $this->connection->getPdo()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchColumn();
    }

    private function fetchString(string $sql): string
    {
        $value = $this->fetchScalar($sql);

        if (! is_string($value) && ! is_int($value)) {
            throw new \UnexpectedValueException("Expected a scalar result for `{$sql}`, got ".get_debug_type($value).'.');
        }

        return (string) $value;
    }

    private function key(TableReference $table): string
    {
        return ($table->schema ?? $this->connection->getDatabaseName()).'.'.$table->name;
    }
}
