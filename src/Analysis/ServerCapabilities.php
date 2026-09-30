<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

final readonly class ServerCapabilities
{
    private function __construct(
        public string $version,
        private bool $mariaDb,
    ) {}

    public static function fromVersion(string $version): self
    {
        preg_match('/^(\d+\.\d+\.\d+)/', $version, $m);

        return new self($m[1] ?? '0.0.0', str_contains(strtolower($version), 'mariadb'));
    }

    /** INSTANT ADD COLUMN at any position and INSTANT DROP COLUMN. */
    public function supportsInstantColumnAnywhere(): bool
    {
        return ! $this->mariaDb && version_compare($this->version, '8.0.29', '>=');
    }

    public function isMariaDb(): bool
    {
        return $this->mariaDb;
    }
}
