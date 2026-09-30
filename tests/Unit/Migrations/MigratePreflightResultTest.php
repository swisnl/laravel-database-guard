<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Migrations;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Migrations\MigratePreflightResult;

#[CoversClass(MigratePreflightResult::class)]
final class MigratePreflightResultTest extends TestCase
{
    #[Test]
    public function migrate_preflight_results_are_successful_without_failures(): void
    {
        $success = new MigratePreflightResult(['a.php', 'b.php'], []);
        $failure = new MigratePreflightResult(['a.php'], ['a (up): unmet']);

        $this->assertTrue($success->isSuccessful());
        $this->assertSame(2, $success->migrationCount());
        $this->assertFalse($failure->isSuccessful());
        $this->assertSame(1, $failure->migrationCount());
    }
}
