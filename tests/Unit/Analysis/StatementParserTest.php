<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Analysis\StatementKind;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\TableReference;

#[CoversClass(StatementParser::class)]
final class StatementParserTest extends TestCase
{
    #[Test]
    #[DataProvider('statements')]
    public function it_recognises_the_kind_and_table(string $sql, StatementKind $kind, ?string $table): void
    {
        $statement = (new StatementParser)->parse($sql);

        $this->assertSame($kind, $statement->kind);
        $this->assertSame($table, $statement->table?->name);
    }

    /**
     * @return iterable<string, array{string, StatementKind, string|null}>
     */
    public static function statements(): iterable
    {
        yield 'create table' => ['create table `orders` (`id` int)', StatementKind::CreateTable, 'orders'];
        yield 'create table if not exists' => ['create table if not exists `orders` (`id` int)', StatementKind::CreateTable, 'orders'];
        yield 'alter table' => ['alter table `orders` add `notes` text null', StatementKind::AlterTable, 'orders'];
        yield 'create index' => ['create unique index `orders_status_unique` on `orders` (`status`)', StatementKind::CreateIndex, 'orders'];
        yield 'drop index' => ['drop index `orders_status_index` on `orders`', StatementKind::DropIndex, 'orders'];
        yield 'drop table' => ['drop table `orders`', StatementKind::DropTable, 'orders'];
        yield 'drop table if exists' => ['drop table if exists `orders`', StatementKind::DropTable, 'orders'];
        yield 'truncate' => ['truncate table `orders`', StatementKind::Truncate, 'orders'];
        yield 'truncate without table keyword' => ['truncate `orders`', StatementKind::Truncate, 'orders'];
        yield 'rename table' => ['rename table `orders` to `purchases`', StatementKind::RenameTable, 'orders'];
        yield 'update' => ["update `orders` set `status` = 'paid'", StatementKind::Update, 'orders'];
        yield 'delete' => ['delete from `orders` where `id` = 1', StatementKind::Delete, 'orders'];
        yield 'insert' => ["insert into `orders` (`status`) values ('new')", StatementKind::Insert, 'orders'];
        yield 'set' => ['set session lock_wait_timeout = 5', StatementKind::SetVariable, null];
        yield 'select' => ['select * from `orders`', StatementKind::Other, null];
        yield 'unquoted and uppercase' => ['ALTER TABLE orders ADD COLUMN notes TEXT NULL;', StatementKind::AlterTable, 'orders'];
        yield 'leading block comment' => ['/* comment */ alter table `orders` add `notes` text null', StatementKind::AlterTable, 'orders'];
        yield 'leading line comment' => ["-- comment\nalter table `orders` add `notes` text null", StatementKind::AlterTable, 'orders'];
    }

    #[Test]
    public function it_reads_the_schema_of_qualified_tables(): void
    {
        $statement = (new StatementParser)->parse('alter table `shop`.`orders` add `notes` text null');

        $this->assertInstanceOf(TableReference::class, $statement->table);
        $this->assertSame('orders', $statement->table->name);
        $this->assertSame('shop', $statement->table->schema);
    }

    #[Test]
    public function it_leaves_the_schema_empty_for_unqualified_tables(): void
    {
        $this->assertNull((new StatementParser)->parse('alter table `orders` add `notes` text null')->table?->schema);
    }

    #[Test]
    public function it_splits_alter_table_clauses_on_top_level_commas(): void
    {
        $statement = (new StatementParser)->parse(
            "alter table `orders` add `amount` decimal(10, 2) null, add `notes` varchar(20) not null default 'a, b', algorithm=instant",
        );

        $this->assertSame([
            'add `amount` decimal(10, 2) null',
            "add `notes` varchar(20) not null default 'a, b'",
            'algorithm=instant',
        ], $statement->clauses);
    }

    #[Test]
    #[DataProvider('variables')]
    public function it_reads_session_variables(string $sql, string $variable, string $value): void
    {
        $statement = (new StatementParser)->parse($sql);

        $this->assertSame(StatementKind::SetVariable, $statement->kind);
        $this->assertSame($variable, $statement->variable);
        $this->assertSame($value, $statement->value);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function variables(): iterable
    {
        yield 'session keyword' => ['set session lock_wait_timeout = 5', 'lock_wait_timeout', '5'];
        yield 'session prefix' => ['SET @@session.LOCK_WAIT_TIMEOUT=5', 'lock_wait_timeout', '5'];
        yield 'quoted value' => ["set @@sql_mode = 'STRICT_ALL_TABLES'", 'sql_mode', 'STRICT_ALL_TABLES'];
        yield 'default' => ['set session lock_wait_timeout = DEFAULT', 'lock_wait_timeout', 'DEFAULT'];
    }

    #[Test]
    public function it_keeps_the_normalised_sql(): void
    {
        $this->assertSame('alter table `orders` add `notes` text null', (new StatementParser)->parse("  alter table `orders` add `notes` text null;\n")->sql);
    }
}
