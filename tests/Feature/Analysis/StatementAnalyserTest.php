<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Analysis;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\StatementAnalyser;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Tests\Feature\TestCase;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules\RecordsNewTablesRule;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules\ReturnsNothingRule;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules\ReturnsViolationRule;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules\ThrowsViolationRule;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules\YieldsViolationsRule;

#[CoversClass(StatementAnalyser::class)]
final class StatementAnalyserTest extends TestCase
{
    #[Test]
    public function it_collects_violations_returned_yielded_or_thrown_by_rules(): void
    {
        $analyser = new StatementAnalyser(new StatementParser, [
            new ReturnsNothingRule,
            new ReturnsViolationRule,
            new YieldsViolationsRule,
            new ThrowsViolationRule,
        ]);

        $violations = $analyser->analyse('alter table `orders` add index `i`(`status`)', $this->context());

        $this->assertSame(
            ['returned', 'yielded first', 'yielded second', 'thrown'],
            array_map(fn (Violation $violation): string => $violation->getMessage(), $violations),
        );
        $this->assertSame('orders', $violations[0]->statement->table?->name);
    }

    #[Test]
    public function it_returns_no_violations_without_rules(): void
    {
        $this->assertSame([], (new StatementAnalyser(new StatementParser, []))->analyse('drop table `orders`', $this->context()));
    }

    #[Test]
    public function it_observes_statements_after_the_rules(): void
    {
        $rule = new RecordsNewTablesRule;
        $analyser = new StatementAnalyser(new StatementParser, [$rule]);
        $context = $this->context();

        $analyser->analyse('create table `invoices` (`id` int)', $context);
        $analyser->analyse('alter table `invoices` add index `i`(`id`)', $context);
        $analyser->analyse('drop table `invoices`', $context);
        $analyser->analyse('create index `i` on `invoices` (`id`)', $context);

        // The CREATE only makes the table new for later statements, the DROP makes it existing again.
        $this->assertSame([false, true, true, false], $rule->seen);
    }

    private function context(): AnalysisContext
    {
        return new AnalysisContext(DB::connection());
    }
}
