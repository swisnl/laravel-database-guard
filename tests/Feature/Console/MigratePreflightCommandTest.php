<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Console;

use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Console\MigratePreflightCommand;
use Swis\Laravel\DatabaseGuard\Tests\Feature\Concerns\UsesMigrationFixtures;
use Swis\Laravel\DatabaseGuard\Tests\Feature\MySqlTestCase;

#[CoversClass(MigratePreflightCommand::class)]
final class MigratePreflightCommandTest extends MySqlTestCase
{
    use UsesMigrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTableWithRows('orders', 20);
    }

    #[Test]
    public function it_succeeds_when_the_exemption_conditions_are_met(): void
    {
        $this->migration('add_index_to_orders_exempt_up_to_100_rows');

        $this->assertSame(0, Artisan::call('migrate:database-guard-preflight', ['--path' => base_path($this->migrationPath), '--realpath' => true]));
        $this->assertStringContainsString('1 pending migration(s): exemption conditions met.', Artisan::output());
    }

    #[Test]
    public function it_fails_when_the_exemption_conditions_are_not_met(): void
    {
        $this->migration('add_index_to_orders_exempt_up_to_10_rows');

        $this->assertSame(1, Artisan::call('migrate:database-guard-preflight', ['--path' => base_path($this->migrationPath), '--realpath' => true]));
        $this->assertStringContainsString('2026_01_01_000001_add_index_to_orders_exempt_up_to_10_rows (up) #[ExemptFrom] BlockingSchemaChangeRule on orders: table rows ≤ 10 (is ~20)', Artisan::output());
    }

    #[Test]
    public function it_resolves_paths_relative_to_the_base_path(): void
    {
        $this->migration('add_index_to_orders_exempt_up_to_100_rows');

        $this->assertSame(0, Artisan::call('migrate:database-guard-preflight', ['--path' => $this->migrationPath]));
        $this->assertStringContainsString('1 pending migration(s)', Artisan::output());
    }

    #[Test]
    public function it_only_checks_pending_migrations(): void
    {
        $this->migration('add_index_to_orders_exempt_up_to_100_rows');

        $this->migrate();

        $this->assertSame(0, Artisan::call('migrate:database-guard-preflight', ['--path' => $this->migrationPath, '--database' => 'mysql']));
        $this->assertStringContainsString('0 pending migration(s)', Artisan::output());
    }

    #[Test]
    public function it_runs_without_a_migrations_table(): void
    {
        $this->assertSame(0, Artisan::call('migrate:database-guard-preflight', ['--path' => $this->migrationPath]));
        $this->assertStringContainsString('0 pending migration(s)', Artisan::output());
    }
}
