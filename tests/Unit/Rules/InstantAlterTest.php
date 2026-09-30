<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Rules;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Analysis\ServerCapabilities;
use Swis\Laravel\DatabaseGuard\Rules\InstantAlter;

#[CoversClass(InstantAlter::class)]
final class InstantAlterTest extends TestCase
{
    #[Test]
    #[DataProvider('clauses')]
    public function it_classifies_clauses(string $clause, bool $instant, string $version = '8.0.35'): void
    {
        $this->assertSame($instant, InstantAlter::isInstant($clause, ServerCapabilities::fromVersion($version)));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool, 2?: string}>
     */
    public static function clauses(): iterable
    {
        yield 'add column' => ['add `notes` text null', true];
        yield 'add column keyword' => ['add column `notes` text null', true];
        yield 'add column after' => ['add `notes` text null after `status`', true];
        yield 'add column first' => ['add `notes` text null first', true];
        yield 'add column after, before 8.0.29' => ['add `notes` text null after `status`', false, '8.0.28'];
        yield 'add column after on mariadb' => ['add `notes` text null after `status`', false, '11.4.2-MariaDB'];
        yield 'add column named like a keyword' => ['add `after` int null', true, '8.0.28'];
        yield 'add column with keywords in its default' => ["add `notes` varchar(20) not null default 'unique after'", true, '8.0.28'];
        yield 'add auto increment column' => ['add `uid` bigint unsigned not null auto_increment primary key', false];
        yield 'add unique column' => ['add `code` varchar(10) unique', false];
        yield 'add stored column' => ['add `double_id` int as (`id` * 2) stored', false];
        yield 'add column with a foreign key' => ['add `user_id` bigint references `users` (`id`)', false];
        yield 'add index' => ['add index `orders_status_index`(`status`)', false];
        yield 'add unique index' => ['add unique `orders_status_unique`(`status`)', false];
        yield 'add primary key' => ['add primary key (`id`)', false];
        yield 'add foreign key' => ['add constraint `orders_user_id_foreign` foreign key (`user_id`) references `users` (`id`)', false];
        yield 'drop index' => ['drop index `orders_status_index`', true];
        yield 'drop key' => ['drop key `orders_status_index`', true];
        yield 'drop foreign key' => ['drop foreign key `orders_user_id_foreign`', true];
        yield 'drop primary key' => ['drop primary key', false];
        yield 'drop check' => ['drop check `orders_amount_check`', false];
        yield 'drop column' => ['drop `notes`', true];
        yield 'drop column keyword' => ['drop column `notes`', true];
        yield 'drop column, before 8.0.29' => ['drop `notes`', false, '8.0.28'];
        yield 'rename column' => ['rename column `a` to `b`', true];
        yield 'rename index' => ['rename index `a` to `b`', true];
        yield 'rename table' => ['rename to `purchases`', true];
        yield 'set default' => ["alter column `status` set default 'paid'", true];
        yield 'drop default' => ['alter `status` drop default', true];
        yield 'set invisible' => ['alter column `status` set invisible', true];
        yield 'algorithm' => ['algorithm=instant', true];
        yield 'modify' => ['modify `status` varchar(100) not null', false];
        yield 'change' => ['change `status` `state` varchar(100) not null', false];
        yield 'engine' => ['engine = InnoDB', false];
        yield 'unknown' => ["comment = 'Orders'", false];
    }

    /**
     * @param  list<string>  $clauses
     */
    #[Test]
    #[DataProvider('explicitlyInstant')]
    public function it_detects_an_explicit_algorithm_instant(array $clauses, bool $explicit): void
    {
        $this->assertSame($explicit, InstantAlter::isExplicitlyInstant($clauses));
    }

    /**
     * @return iterable<string, array{list<string>, bool}>
     */
    public static function explicitlyInstant(): iterable
    {
        yield 'algorithm instant' => [['add index `i`(`status`)', 'algorithm=instant'], true];
        yield 'spaces and uppercase' => [['modify `status` int', 'ALGORITHM = INSTANT'], true];
        yield 'algorithm inplace' => [['add index `i`(`status`)', 'algorithm=inplace'], false];
        yield 'no algorithm' => [['add `notes` text null'], false];
    }
}
