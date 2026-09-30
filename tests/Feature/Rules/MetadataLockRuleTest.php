<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Rules;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Rules\MetadataLockRule;
use Swis\Laravel\DatabaseGuard\Rules\Rule;

final class MetadataLockRuleTest extends RuleTestCase
{
    protected function rule(): Rule
    {
        return new MetadataLockRule;
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
        yield 'instant alter' => ['alter table `orders` add `notes` text null', ['requires an exclusive metadata lock']];
        yield 'rename' => ['rename table `orders` to `purchases`', ['requires an exclusive metadata lock']];
        yield 'drop table' => ['drop table `orders`', ['requires an exclusive metadata lock']];
        yield 'create table' => ['create table `invoices` (`id` int)', []];
        yield 'data change' => ["insert into `orders` (`status`) values ('new')", []];
    }
}
