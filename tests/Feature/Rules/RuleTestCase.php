<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Rules;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\StatementAnalyser;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Rules\Rule;
use Swis\Laravel\DatabaseGuard\Tests\Feature\MySqlTestCase;

abstract class RuleTestCase extends MySqlTestCase
{
    abstract protected function rule(): Rule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTableWithRows('orders', 5);
    }

    /**
     * @param  list<string>  $messages
     */
    protected function assertViolations(string $sql, array $messages): void
    {
        $this->assertSame($messages, array_map(fn (Violation $violation): string => $violation->getMessage(), $this->analyse($sql)));
    }

    /**
     * @return Violation[]
     */
    protected function analyse(string $sql, ?AnalysisContext $context = null): array
    {
        return (new StatementAnalyser(new StatementParser, [$this->rule()]))
            ->analyse($sql, $context ?? new AnalysisContext(DB::connection()));
    }

    #[Test]
    public function it_ignores_tables_created_earlier_in_the_run(): void
    {
        $context = new AnalysisContext(DB::connection());

        $this->analyse('create table `invoices` (`id` int)', $context);

        $this->assertSame([], $this->analyse('alter table `invoices` add index `i`(`id`)', $context));
        $this->assertSame([], $this->analyse('create index `j` on `invoices` (`id`)', $context));
        $this->assertSame([], $this->analyse('drop table `invoices`', $context));
    }
}
