<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Migrations;

use Illuminate\Console\View\Components\Warn;
use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\Log;
use Swis\Laravel\DatabaseGuard\Analysis\StatementAnalyser;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\DatabaseGuard;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Guard\GuardScope;
use Swis\Laravel\DatabaseGuard\Rules\Rule;
use Symfony\Component\Console\Output\OutputInterface;

class GuardedMigrator extends Migrator
{
    private ?DatabaseGuard $guard = null;

    /**
     * Run the preflight for pending migrations at a given path.
     *
     * @param  string|string[]  $paths
     */
    public function preflight(array|string $paths = []): MigratePreflightResult
    {
        // Without a migrations table, nothing has run yet.
        $ran = $this->repositoryExists() ? $this->repository->getRan() : [];

        $this->requireFiles($migrations = $this->pendingMigrations($this->getMigrationFiles($paths), $ran));

        return $this->runPreflightForMethod($migrations, 'up');
    }

    /**
     * @param  string[]  $migrations  migration files
     * @param  array<string, mixed>  $options
     *
     * @throws MigratePreflightFailed
     */
    public function runPreflight(array $migrations, string $method, array $options): void
    {
        if (! $this->enabled() || $migrations === []) {
            return;
        }

        $result = $this->runPreflightForMethod($migrations, $method);
        $failures = $result->failures;

        if ($failures === []) {
            return;
        }

        $exception = new MigratePreflightFailed($failures);

        if ($options['pretend'] ?? false) {
            $this->guard()->warn($exception->getMessage());

            return;
        }

        throw $exception;
    }

    #[\Override]
    public function runPending(array $migrations, array $options = []): void
    {
        $this->guard()->resetContexts();
        $this->runPreflight($migrations, 'up', $options);

        parent::runPending($migrations, $options);
    }

    /**
     * Covers rollback, reset and refresh (reset delegates here).
     *
     * @param  array<int, object{migration: string}|array{migration: string}>  $migrations  records from the migration repository
     * @param  string[]|string  $paths
     */
    #[\Override]
    protected function rollbackMigrations(array $migrations, $paths, array $options): array
    {
        // The records only hold migration names; the preflight needs the files.
        $files = $this->getMigrationFiles($paths);
        $toRollBack = [];

        foreach ($migrations as $migration) {
            $name = ((object) $migration)->migration;

            if (isset($files[$name])) {
                $toRollBack[] = $files[$name];
            }
        }

        $this->guard()->resetContexts();
        $this->runPreflight($toRollBack, 'down', $options);

        return parent::rollbackMigrations($migrations, $paths, $options);
    }

    #[\Override]
    public function resolveConnection($connection): Connection
    {
        $resolved = parent::resolveConnection($connection);

        if ($this->enabled()) {
            $this->guard()->attach($resolved);
        }

        return $resolved;
    }

    #[\Override]
    protected function runMethod($connection, $migration, $method): void
    {
        if (! $this->enabled()) {
            parent::runMethod($connection, $migration, $method);

            return;
        }

        $this->guard()->attach($connection);

        $file = (new \ReflectionClass($migration))->getFileName();
        $name = $file !== false ? $this->getMigrationName($file) : $migration::class;

        $scope = new GuardScope(
            "Migration $name ($method)",
            ExemptFrom::declaredOn($migration, $method),
        );

        $this->guard()->within($scope, fn () => parent::runMethod($connection, $migration, $method));
    }

    /**
     * @param  string[]  $migrations  migration file paths
     */
    protected function runPreflightForMethod(array $migrations, string $method): MigratePreflightResult
    {
        $failures = [];

        foreach ($migrations as $file) {
            $migration = $this->resolvePath($file);
            $connection = $this->resolveConnection($migration instanceof Migration ? $migration->getConnection() : null);

            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $context = $this->guard()->context($connection);

            foreach (ExemptFrom::declaredOn($migration, $method) as $exemption) {
                foreach ($exemption->when as $condition) {
                    $result = $condition->preflight($exemption, $context);

                    if ($result->isUnmet()) {
                        $failures[] = "{$this->getMigrationName($file)} ($method) #[ExemptFrom] {$exemption->describe()}: $result->description";
                    }
                }
            }
        }

        return new MigratePreflightResult($migrations, $failures);
    }

    private function enabled(): bool
    {
        return config('database-guard.enabled') && ! config('database-guard.bypass');
    }

    private function guard(): DatabaseGuard
    {
        return $this->guard ??= new DatabaseGuard(
            new StatementAnalyser(new StatementParser, $this->rules()),
            array_values(array_map(ExemptFrom::fromConfig(...), config()->array('database-guard.exemptions', []))),
            function (string $message): void {
                // Migrator types $output as always set, but only commands set it.
                /** @var OutputInterface|null $output */
                $output = $this->output;

                if ($output !== null) {
                    $this->write(Warn::class, $message);
                } else {
                    Log::warning($message);
                }
            },
            config('database-guard.unused_exemptions') === 'warn',
        );
    }

    /**
     * @return list<Rule>
     */
    private function rules(): array
    {
        $rules = [];

        // Rule::class, or Rule::class => [constructor args]
        foreach (config()->array('database-guard.rules', []) as $key => $value) {
            [$class, $parameters] = is_int($key) ? [$value, []] : [$key, $value];

            if (! is_string($class) || ! is_array($parameters)) {
                throw new \UnexpectedValueException('Each database-guard.rules entry must be Rule::class or Rule::class => [constructor args].');
            }

            $rule = app()->make($class, $parameters);

            if (! $rule instanceof Rule) {
                throw new \UnexpectedValueException("{$class} does not implement ".Rule::class.'.');
            }

            $rules[] = $rule;
        }

        return $rules;
    }
}
