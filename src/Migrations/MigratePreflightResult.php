<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Migrations;

/**
 * The outcome of checking the exemption conditions of migrations.
 */
final readonly class MigratePreflightResult
{
    /**
     * @param  string[]  $migrations  migration file paths
     * @param  list<string>  $failures  descriptions of unmet exemption conditions
     */
    public function __construct(
        public array $migrations,
        public array $failures,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->failures === [];
    }

    public function migrationCount(): int
    {
        return count($this->migrations);
    }
}
