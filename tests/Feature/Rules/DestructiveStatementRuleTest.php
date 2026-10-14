<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Rules\DestructiveStatementRule;
use Swis\Laravel\DatabaseGuard\Rules\Rule;

final class DestructiveStatementRuleTest extends RuleTestCase
{
    protected function rule(): Rule
    {
        return new DestructiveStatementRule;
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
        yield 'drop table' => ['drop table `orders`', ['drops or truncates existing data']];
        yield 'truncate' => ['truncate table `orders`', ['drops or truncates existing data']];
        yield 'alter table' => ['alter table `orders` add index `i`(`status`)', []];
        yield 'delete' => ['delete from `orders`', []];
    }
}
