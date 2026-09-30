<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Migrations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Migrations\MigratePreflightFailed;

#[CoversClass(MigratePreflightFailed::class)]
final class MigratePreflightFailedTest extends TestCase
{
    #[Test]
    public function migrate_preflight_failed_lists_the_failures(): void
    {
        $exception = new MigratePreflightFailed(['first failure', 'second failure']);

        $this->assertSame(['first failure', 'second failure'], $exception->failures);
        $this->assertSame("Refusing to migrate: exemption conditions not met. Nothing was run.\n  first failure\n  second failure", $exception->getMessage());
    }
}
