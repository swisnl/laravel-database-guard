<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Exemption;

use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Exemptions\InvalidExemption;
use Swis\Laravel\DatabaseGuard\Exemptions\LockWaitTimeoutAtMost;
use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;
use Swis\Laravel\DatabaseGuard\Rules\DestructiveStatementRule;
use Swis\Laravel\DatabaseGuard\Rules\MetadataLockRule;
use Swis\Laravel\DatabaseGuard\Rules\Rule;
use Swis\Laravel\DatabaseGuard\Tests\Feature\TestCase;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Migrations\MigrationWithExemptions;
use Swis\Laravel\DatabaseGuard\Tests\Fixtures\Migrations\MigrationWithUnconditionalExemption;

#[CoversClass(ExemptFrom::class)]
final class ExemptFromTest extends TestCase
{
    #[Test]
    public function it_accepts_a_single_condition(): void
    {
        $condition = new TableRowsAtMost(10);
        $exemption = new ExemptFrom(BlockingSchemaChangeRule::class, 'orders', $condition);

        $this->assertSame(BlockingSchemaChangeRule::class, $exemption->rule);
        $this->assertSame('orders', $exemption->table);
        $this->assertSame([$condition], $exemption->when);
    }

    #[Test]
    public function it_accepts_multiple_conditions(): void
    {
        $conditions = [new TableRowsAtMost(10), new TableRowsAtMost(20)];

        $this->assertSame($conditions, (new ExemptFrom(BlockingSchemaChangeRule::class, when: $conditions))->when);
    }

    #[Test]
    public function it_rejects_classes_that_are_not_rules(): void
    {
        $this->expectException(InvalidExemption::class);
        $this->expectExceptionMessage('stdClass does not implement '.Rule::class);

        new ExemptFrom(\stdClass::class);
    }

    #[Test]
    public function it_rejects_values_that_are_not_conditions(): void
    {
        $this->expectException(InvalidExemption::class);
        $this->expectExceptionMessage('int does not implement');

        new ExemptFrom(BlockingSchemaChangeRule::class, when: [42]);
    }

    #[Test]
    public function it_rejects_conditions_the_rule_does_not_accept(): void
    {
        $this->expectException(InvalidExemption::class);
        $this->expectExceptionMessage('LockWaitTimeoutAtMost does not mitigate BlockingSchemaChangeRule (accepted: TableRowsAtMost)');

        new ExemptFrom(BlockingSchemaChangeRule::class, when: new LockWaitTimeoutAtMost(5));
    }

    #[Test]
    public function it_creates_exemptions_from_config(): void
    {
        $exemption = ExemptFrom::fromConfig([
            'rule' => DestructiveStatementRule::class,
            'table' => 'orders',
            'when' => [TableRowsAtMost::class => ['limit' => 500]],
        ]);

        $this->assertSame(DestructiveStatementRule::class, $exemption->rule);
        $this->assertSame('orders', $exemption->table);
        $this->assertCount(1, $exemption->when);
        $this->assertInstanceOf(TableRowsAtMost::class, $exemption->when[0]);
        $this->assertSame(500, $exemption->when[0]->limit);
    }

    #[Test]
    #[WithConfig('database-guard.exemptions', [
        [
            'rule' => BlockingSchemaChangeRule::class,
            'when' => [TableRowsAtMost::class => []],
        ],
        [
            'rule' => DestructiveStatementRule::class,
            'when' => [TableRowsAtMost::class => []],
        ],
    ])]
    public function it_creates_the_configured_default_exemptions(): void
    {
        $exemptions = array_map(ExemptFrom::fromConfig(...), config()->array('database-guard.exemptions'));

        $this->assertSame(
            [
                BlockingSchemaChangeRule::class,
                DestructiveStatementRule::class,
            ],
            array_map(fn (ExemptFrom $exemption): string => $exemption->rule, $exemptions),
        );
    }

    #[Test]
    #[DataProvider('invalidConfigs')]
    public function it_rejects_invalid_config(mixed $config, string $message): void
    {
        $this->expectException(InvalidExemption::class);
        $this->expectExceptionMessage($message);

        ExemptFrom::fromConfig($config);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'not an array' => ['orders', 'expected an array, got string'];
        yield 'missing rule' => [['when' => []], "'rule' must be a class name, got null"];
        yield 'invalid table' => [['rule' => BlockingSchemaChangeRule::class, 'table' => 1], "'table' must be a string or null, got int"];
        yield 'invalid when' => [['rule' => BlockingSchemaChangeRule::class, 'when' => 'always'], "'when' must map condition classes to constructor arguments, got string"];
        yield 'not a condition' => [['rule' => BlockingSchemaChangeRule::class, 'when' => [\stdClass::class => []]], 'stdClass does not implement'];
        yield 'invalid arguments' => [['rule' => BlockingSchemaChangeRule::class, 'when' => [TableRowsAtMost::class => 10]], 'the constructor arguments of '.TableRowsAtMost::class.' must be an array, got int'];
    }

    #[Test]
    public function it_reads_class_and_method_declarations(): void
    {
        $exemptions = ExemptFrom::declaredOn(new MigrationWithExemptions, 'up');

        $this->assertSame(
            [MetadataLockRule::class, BlockingSchemaChangeRule::class],
            array_map(fn (ExemptFrom $exemption): string => $exemption->rule, $exemptions),
        );
    }

    #[Test]
    public function it_only_reads_declarations_of_the_given_method(): void
    {
        $exemptions = ExemptFrom::declaredOn(new MigrationWithExemptions, 'down');

        $this->assertSame([MetadataLockRule::class], array_map(fn (ExemptFrom $exemption): string => $exemption->rule, $exemptions));
    }

    #[Test]
    public function it_rejects_unconditional_declarations(): void
    {
        $this->expectException(InvalidExemption::class);
        $this->expectExceptionMessage('Unconditional exemption from BlockingSchemaChangeRule is not allowed');

        ExemptFrom::declaredOn(new MigrationWithUnconditionalExemption, 'up');
    }

    #[Test]
    public function it_can_allow_unconditional_declarations(): void
    {
        config(['database-guard.allow_unconditional_exemptions' => true]);

        $this->assertCount(1, ExemptFrom::declaredOn(new MigrationWithUnconditionalExemption, 'up'));
    }

    #[Test]
    public function it_matches_violations_of_the_rule_on_the_table(): void
    {
        $exemption = new ExemptFrom(BlockingSchemaChangeRule::class, 'orders', new TableRowsAtMost(10));

        $this->assertTrue($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'orders'), ''));
        $this->assertFalse($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'invoices'), ''));
        $this->assertFalse($exemption->matches($this->violation(DestructiveStatementRule::class, 'orders'), ''));
    }

    #[Test]
    public function it_matches_tables_with_the_connection_prefix(): void
    {
        $exemption = new ExemptFrom(BlockingSchemaChangeRule::class, 'orders', new TableRowsAtMost(10));

        $this->assertTrue($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'shop_orders'), 'shop_'));
        $this->assertFalse($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'orders'), 'shop_'));
    }

    #[Test]
    public function it_matches_any_table_without_a_table(): void
    {
        $exemption = new ExemptFrom(BlockingSchemaChangeRule::class, when: new TableRowsAtMost(10));

        $this->assertTrue($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'orders'), ''));
        $this->assertTrue($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'invoices'), ''));
    }

    #[Test]
    public function it_matches_implementations_of_the_rule(): void
    {
        $exemption = new ExemptFrom(Rule::class);

        $this->assertTrue($exemption->matches($this->violation(BlockingSchemaChangeRule::class, 'orders'), ''));
        $this->assertTrue($exemption->matches($this->violation(MetadataLockRule::class, 'orders'), ''));
    }

    #[Test]
    public function it_describes_itself(): void
    {
        $this->assertSame('BlockingSchemaChangeRule on orders', (new ExemptFrom(BlockingSchemaChangeRule::class, 'orders', new TableRowsAtMost(10)))->describe());
        $this->assertSame('BlockingSchemaChangeRule', (new ExemptFrom(BlockingSchemaChangeRule::class, when: new TableRowsAtMost(10)))->describe());
    }

    /**
     * @param  class-string<Rule>  $rule
     */
    private function violation(string $rule, string $table): Violation
    {
        return new Violation($rule, (new StatementParser)->parse("alter table `{$table}` add index `i`(`status`)"), 'test');
    }
}
