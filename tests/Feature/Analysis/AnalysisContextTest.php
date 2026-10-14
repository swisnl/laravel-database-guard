<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Analysis;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\TableReference;
use Swis\Laravel\DatabaseGuard\Tests\Feature\MySqlTestCase;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Exemptions\AlwaysApplicableCondition;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Exemptions\ExposedAtMostCondition;

#[CoversClass(AnalysisContext::class)]
final class AnalysisContextTest extends MySqlTestCase
{
    #[Test]
    public function it_counts_table_rows(): void
    {
        $this->createTableWithRows('orders', 5);

        $this->assertSame(5, $this->context()->tableRows(new TableReference('orders')));
    }

    #[Test]
    public function it_counts_table_rows_in_another_schema(): void
    {
        $this->createTableWithRows('orders', 5);

        $this->assertSame(5, $this->context()->tableRows(new TableReference('orders', DB::connection()->getDatabaseName())));
    }

    #[Test]
    public function it_counts_no_rows_for_missing_tables(): void
    {
        $this->assertSame(0, $this->context()->tableRows(new TableReference('missing')));
    }

    #[Test]
    public function it_caches_row_counts_until_the_table_is_recreated_or_truncated(): void
    {
        $this->createTableWithRows('orders', 5);
        $context = $this->context();
        $orders = new TableReference('orders');

        $this->assertSame(5, $context->tableRows($orders));

        $this->fill('orders', 5);
        $this->assertSame(5, $context->tableRows($orders));

        $context->observe((new StatementParser)->parse('truncate table `orders`'));
        $this->assertSame(10, $context->tableRows($orders));
    }

    #[Test]
    public function it_tracks_tables_created_and_dropped(): void
    {
        $context = $this->context();
        $parser = new StatementParser;
        $invoices = new TableReference('invoices');

        $this->assertFalse($context->isNewTable($invoices));

        $context->observe($parser->parse('create table `invoices` (`id` int)'));
        $this->assertTrue($context->isNewTable($invoices));

        $context->observe($parser->parse('drop table `invoices`'));
        $this->assertFalse($context->isNewTable($invoices));
    }

    #[Test]
    public function it_reads_live_session_variables(): void
    {
        DB::connection()->getPdo()->exec('set session lock_wait_timeout = 7');

        $this->assertSame('7', $this->context()->sessionVariable('LOCK_WAIT_TIMEOUT'));
    }

    #[Test]
    public function it_uses_observed_session_variables_when_pretending(): void
    {
        $connection = DB::connection();
        $context = new AnalysisContext($connection);
        $parser = new StatementParser;
        $values = [];

        $connection->pretend(function () use ($context, $parser, &$values): void {
            $context->observe($parser->parse('set session lock_wait_timeout = 3'));
            $values[] = $context->sessionVariable('lock_wait_timeout');

            // DEFAULT restores the server value, which only the server knows.
            $context->observe($parser->parse('set session lock_wait_timeout = DEFAULT'));
            $values[] = $context->sessionVariable('lock_wait_timeout');
        });

        $expected = DB::scalar('select @@session.lock_wait_timeout');
        assert(is_string($expected) || is_int($expected));

        $this->assertSame(
            ['3', (string) $expected],
            $values,
        );
    }

    #[Test]
    public function it_ignores_observed_session_variables_when_not_pretending(): void
    {
        $context = $this->context();
        $context->observe((new StatementParser)->parse('set session lock_wait_timeout = 3'));

        $expected = DB::scalar('select @@session.lock_wait_timeout');
        assert(is_string($expected) || is_int($expected));

        $this->assertSame(
            (string) $expected,
            $context->sessionVariable('lock_wait_timeout'),
        );
    }

    #[Test]
    public function it_rejects_invalid_variable_names(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->context()->sessionVariable('lock_wait_timeout; drop table orders');
    }

    #[Test]
    public function it_reads_the_server_capabilities(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->context()->server()->version);
    }

    #[Test]
    public function it_reads_condition_settings(): void
    {
        config(['database-guard.conditions' => [
            ExposedAtMostCondition::class => ['default' => 5]],
        ]);

        $context = $this->context();

        $this->assertSame(['default' => 5], $context->conditionSettings(ExposedAtMostCondition::class));
        $this->assertSame([], $context->conditionSettings(AlwaysApplicableCondition::class));
    }

    private function context(): AnalysisContext
    {
        return new AnalysisContext(DB::connection());
    }
}
