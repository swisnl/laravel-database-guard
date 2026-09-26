<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard;

class DatabaseGuard
{
    /**
     * @param  array<array-key, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
    ) {}

    public function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }
}
