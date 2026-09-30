<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;
use Swis\Laravel\DatabaseGuard\Rules\Rule;

#[CoversClass(BlockingSchemaChangeRule::class)]
final class BlockingSchemaChangeRuleTest extends RuleTestCase
{
    protected function rule(): Rule
    {
        return new BlockingSchemaChangeRule;
    }

    /**
     * @param  list<string>  $messages
     */
    #[Test]
    #[DataProvider('statements')]
    public function it_reports_violations(string $sql, array $messages): void
    {
        $this->assertViolations($sql, $messages);
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function statements(): iterable
    {
        $rebuild = 'not INSTANT: table rebuild or index build';

        yield 'add index' => ['alter table `orders` add index `i`(`status`)', [$rebuild]];
        yield 'one violation per blocking clause' => ['alter table `orders` add `notes` text null, add index `i`(`status`), modify `status` varchar(100)', [$rebuild, $rebuild]];
        yield 'instant add column' => ['alter table `orders` add `notes` text null', []];
        yield 'algorithm instant' => ['alter table `orders` modify `status` varchar(100), algorithm=instant', []];
        yield 'create index' => ['create index `i` on `orders` (`status`)', ['index build scans the whole table']];
        yield 'drop table' => ['drop table `orders`', []];
        yield 'data change' => ["update `orders` set `status` = 'paid'", []];
    }

    #[Test]
    public function it_reports_the_blocking_clause(): void
    {
        $violations = $this->analyse('alter table `orders` add `notes` text null, add index `i`(`status`)');

        $this->assertSame('add index `i`(`status`)', $violations[0]->clause);
    }
}
