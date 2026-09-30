<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

final readonly class TableReference
{
    public function __construct(
        public string $name,
        public ?string $schema = null,
    ) {}
}
