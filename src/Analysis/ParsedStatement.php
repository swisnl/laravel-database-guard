<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

final readonly class ParsedStatement
{
    /**
     * @param  string[]  $clauses  top-level ALTER TABLE clauses
     */
    public function __construct(
        public StatementKind $kind,
        public ?TableReference $table,
        public array $clauses,
        public string $sql,
        public ?string $variable = null,
        public ?string $value = null,
    ) {}

    public function isSchemaChange(): bool
    {
        return in_array($this->kind, [
            StatementKind::AlterTable,
            StatementKind::CreateIndex,
            StatementKind::DropIndex,
            StatementKind::DropTable,
            StatementKind::Truncate,
            StatementKind::RenameTable,
        ], true);
    }
}
