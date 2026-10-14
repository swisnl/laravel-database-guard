<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Migrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Exemptions\InvalidExemption;
use Swis\Laravel\DatabaseGuard\Exemptions\LockWaitTimeoutAtMost;
use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;
use Swis\Laravel\DatabaseGuard\Guard\StatementDenied;
use Swis\Laravel\DatabaseGuard\Migrations\GuardedMigrator;
use Swis\Laravel\DatabaseGuard\Migrations\MigratePreflightFailed;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;
use Swis\Laravel\DatabaseGuard\Rules\DestructiveStatementRule;
use Swis\Laravel\DatabaseGuard\Rules\MetadataLockRule;
use Swis\Laravel\DatabaseGuard\Tests\Feature\Concerns\UsesMigrationFixtures;
use Swis\Laravel\DatabaseGuard\Tests\Feature\MySqlTestCase;

#[CoversClass(GuardedMigrator::class)]
final class GuardedMigratorTest extends MySqlTestCase
{
    use UsesMigrationFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTableWithRows('orders', 20);
    }

    #[Test]
    public function it_replaces_the_migrator(): void
    {
        $app = $this->app;
        $this->assertNotNull($app);

        $this->assertInstanceOf(GuardedMigrator::class, $app->make('migrator'));
    }

    #[Test]
    public function it_allows_instant_changes_on_large_tables(): void
    {
        $this->setRowLimit(10);
        $this->migration('add_notes_to_orders');

        $this->migrate();

        $this->assertTrue(Schema::hasColumn('orders', 'notes'));
    }

    #[Test]
    #[WithConfig('database-guard.rules', [
        MetadataLockRule::class,
    ])]
    #[WithConfig('database-guard.exemptions', [
        [
            'rule' => MetadataLockRule::class,
            'when' => [LockWaitTimeoutAtMost::class => []],
        ],
    ])]
    public function it_denies_schema_changes_with_a_long_lock_wait_timeout(): void
    {
        $this->migration('add_notes_to_orders_without_short_lock_wait');

        $exception = $this->assertDenied(fn () => $this->migrate());

        $this->assertSame(MetadataLockRule::class, $exception->violations[0]->rule);
        $this->assertStringContainsString('lock_wait_timeout ≤ 5s', $exception->getMessage());
        $this->assertFalse(Schema::hasColumn('orders', 'notes'));
        $this->assertSame(0, DB::table('migrations')->count());
    }

    #[Test]
    #[WithConfig('database-guard.rules', [
        BlockingSchemaChangeRule::class,
    ])]
    #[WithConfig('database-guard.exemptions', [
        [
            'rule' => BlockingSchemaChangeRule::class,
            'when' => [TableRowsAtMost::class => []],
        ],
    ])]
    public function it_allows_blocking_changes_within_the_default_row_limit(): void
    {
        $this->migration('add_index_to_orders');

        $this->migrate();

        $this->assertTrue(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    #[WithConfig('database-guard.exemptions', [
        [
            'rule' => BlockingSchemaChangeRule::class,
            'when' => [TableRowsAtMost::class => []],
        ],
    ])]
    public function it_denies_blocking_changes_above_the_row_limit(): void
    {
        $this->setRowLimit(10);
        $this->migration('add_index_to_orders');

        $exception = $this->assertDenied(fn () => $this->migrate());

        $this->assertSame(BlockingSchemaChangeRule::class, $exception->violations[0]->rule);
        $this->assertSame(['table rows ≤ 10 (is ~20)'], $exception->violations[0]->unmetConditions());
        $this->assertStringContainsString('Migration 2026_01_01_000001_add_index_to_orders (up)', $exception->getMessage());
        $this->assertFalse(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    public function it_allows_blocking_changes_exempted_by_an_attribute(): void
    {
        $this->setRowLimit(10);
        $this->migration('add_index_to_orders_exempt_up_to_100_rows');

        $this->migrate();

        $this->assertTrue(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    public function it_checks_attribute_conditions_before_running_anything(): void
    {
        $this->migration('create_invoices_table');
        $this->migration('add_index_to_orders_exempt_up_to_10_rows');

        $exception = $this->assertPreflightFailed(fn () => $this->migrate());

        $this->assertSame(
            ['2026_01_01_000002_add_index_to_orders_exempt_up_to_10_rows (up) #[ExemptFrom] BlockingSchemaChangeRule on orders: table rows ≤ 10 (is ~20)'],
            $exception->failures,
        );
        $this->assertFalse(Schema::hasTable('invoices'));
        $this->assertSame(0, DB::table('migrations')->count());
    }

    #[Test]
    public function it_caps_attribute_limits_at_the_configured_maximum(): void
    {
        config(['database-guard.conditions.'.TableRowsAtMost::class => ['default' => 10, 'max' => 15]]);
        $this->migration('add_index_to_orders_exempt_up_to_100_rows');

        $exception = $this->assertPreflightFailed(fn () => $this->migrate());

        $this->assertStringContainsString('table rows ≤ 15, capped from 100 by config (is ~20)', $exception->getMessage());
    }

    #[Test]
    public function it_rejects_unconditional_exemptions(): void
    {
        $this->migration('add_index_to_orders_exempt_unconditionally');

        $this->expectException(InvalidExemption::class);

        $this->migrate();
    }

    #[Test]
    public function it_can_allow_unconditional_exemptions(): void
    {
        config(['database-guard.allow_unconditional_exemptions' => true]);
        $this->setRowLimit(10);
        $this->migration('add_index_to_orders_exempt_unconditionally');

        $this->migrate();

        $this->assertTrue(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    public function it_does_not_guard_tables_created_earlier_in_the_run(): void
    {
        $this->migration('create_invoices_table_with_indexes');

        $this->migrate();

        $this->assertTrue(Schema::hasIndex('invoices', ['number'], 'unique'));
    }

    #[Test]
    public function it_forgets_new_tables_between_runs(): void
    {
        $this->migration('create_invoices_table');
        $this->migrate();

        $this->fill('invoices', 20);
        $this->setRowLimit(10);
        $this->migration('add_index_to_invoices');

        // Created by the previous run, so no longer new in this one.
        $exception = $this->assertDenied(fn () => $this->migrate());

        $this->assertSame(BlockingSchemaChangeRule::class, $exception->violations[0]->rule);
    }

    #[Test]
    public function it_denies_dropping_tables_with_more_rows_than_allowed(): void
    {
        $this->setRowLimit(10);
        $this->migration('drop_orders_table');

        $exception = $this->assertDenied(fn () => $this->migrate());

        $this->assertSame(DestructiveStatementRule::class, $exception->violations[0]->rule);
        $this->assertTrue(Schema::hasTable('orders'));
    }

    #[Test]
    public function it_warns_instead_of_denying_when_pretending(): void
    {
        $this->setRowLimit(10);
        $this->migration('add_index_to_orders');

        $this->migrate(['--pretend' => true]);

        $this->assertStringContainsString('Denied statement in Migration 2026_01_01_000001_add_index_to_orders (up)', Artisan::output());
        $this->assertFalse(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    public function it_uses_session_variables_set_by_the_migration_when_pretending(): void
    {
        $this->migration('add_notes_to_orders');

        $this->migrate(['--pretend' => true]);

        $this->assertStringNotContainsString('Denied statement', Artisan::output());
    }

    #[Test]
    public function it_warns_about_preflight_failures_when_pretending(): void
    {
        $this->migration('add_index_to_orders_exempt_up_to_10_rows');

        $this->migrate(['--pretend' => true]);

        $this->assertStringContainsString('Refusing to migrate: exemption conditions not met.', Artisan::output());
    }

    #[Test]
    public function it_warns_about_unused_exemptions(): void
    {
        $this->migration('add_notes_to_orders_exempt_up_to_100_rows');

        $this->migrate();

        $this->assertStringContainsString('Unused exemption in Migration 2026_01_01_000001_add_notes_to_orders_exempt_up_to_100_rows (up): #[ExemptFrom] BlockingSchemaChangeRule on orders', Artisan::output());
    }

    #[Test]
    public function it_can_ignore_unused_exemptions(): void
    {
        config(['database-guard.unused_exemptions' => 'ignore']);
        $this->migration('add_notes_to_orders_exempt_up_to_100_rows');

        $this->migrate();

        $this->assertStringNotContainsString('Unused exemption', Artisan::output());
    }

    #[Test]
    public function it_can_be_bypassed(): void
    {
        config(['database-guard.bypass' => true]);
        $this->migration('add_index_to_orders_without_short_lock_wait');

        $this->migrate();

        $this->assertTrue(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    public function it_can_be_disabled(): void
    {
        config(['database-guard.enabled' => false]);
        $this->migration('add_index_to_orders_without_short_lock_wait');

        $this->migrate();

        $this->assertTrue(Schema::hasIndex('orders', ['status']));
    }

    #[Test]
    public function it_guards_rollbacks(): void
    {
        $this->migration('create_invoices_table');

        $this->migrate();
        $this->fill('invoices', 20);
        $this->setRowLimit(10);

        $exception = $this->assertDenied(fn () => Artisan::call('migrate:rollback', ['--path' => $this->migrationPath]));

        $this->assertSame(DestructiveStatementRule::class, $exception->violations[0]->rule);
        $this->assertStringContainsString('Migration 2026_01_01_000001_create_invoices_table (down)', $exception->getMessage());
        $this->assertTrue(Schema::hasTable('invoices'));
    }

    #[Test]
    public function it_checks_attribute_conditions_before_rolling_back(): void
    {
        $this->migration('create_invoices_table_exempt_drop_up_to_10_rows');

        $this->migrate();
        $this->fill('invoices', 20);

        $exception = $this->assertPreflightFailed(fn () => Artisan::call('migrate:rollback', ['--path' => $this->migrationPath]));

        $this->assertStringContainsString('2026_01_01_000001_create_invoices_table_exempt_drop_up_to_10_rows (down)', $exception->getMessage());
        $this->assertTrue(Schema::hasTable('invoices'));
    }

    private function setRowLimit(int $limit): void
    {
        config(['database-guard.conditions.'.TableRowsAtMost::class.'.default' => $limit]);
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertDenied(callable $callback): StatementDenied
    {
        try {
            $callback();
        } catch (StatementDenied $e) {
            return $e;
        }

        $this->fail('Expected a statement to be denied.');
    }

    /**
     * @param  callable(): mixed  $callback
     */
    private function assertPreflightFailed(callable $callback): MigratePreflightFailed
    {
        try {
            $callback();
        } catch (MigratePreflightFailed $e) {
            return $e;
        }

        $this->fail('Expected the preflight to fail.');
    }
}
