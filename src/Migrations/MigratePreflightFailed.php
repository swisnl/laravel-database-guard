<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Migrations;

final class MigratePreflightFailed extends \RuntimeException
{
    /**
     * @param  string[]  $failures
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct(
            "Refusing to migrate: exemption conditions not met. Nothing was run.\n  ".implode("\n  ", $failures)
        );
    }
}
