<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Guard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Guard\StatementDenied;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;
use Swis\Laravel\DatabaseGuard\Rules\MetadataLockRule;

#[CoversClass(StatementDenied::class)]
final class StatementDeniedTest extends TestCase
{
    #[Test]
    public function statement_denied_describes_the_violations(): void
    {
        $violation = $this->violation('add index `i`(`status`)');
        $violation->recordUnmetConditions('table rows ≤ 10 (is ~20)');

        $exception = new StatementDenied('Migration 2026_01_01_000001_add_index (up)', [$violation]);

        $this->assertSame('Migration 2026_01_01_000001_add_index (up)', $exception->source);
        $this->assertSame([$violation], $exception->violations);
        $this->assertSame(
            <<<'STR'
            Denied statement in Migration 2026_01_01_000001_add_index (up); it was not executed.
              [BlockingSchemaChangeRule] orders: not INSTANT `add index `i`(`status`)`
                  exemption condition not met: table rows ≤ 10 (is ~20)
              alter table `orders` add index `i`(`status`)
            Statements and migrations before this one have already been applied.
            STR,
            $exception->getMessage(),
        );
    }

    #[Test]
    public function statement_denied_shortens_long_statements(): void
    {
        $sql = "alter table `orders` add `notes` text null comment '".str_repeat('x', 300)."'";
        $violation = new Violation(MetadataLockRule::class, (new StatementParser)->parse($sql), 'requires an exclusive metadata lock');

        $this->assertStringContainsString(mb_strimwidth($sql, 0, 200, '…'), (new StatementDenied('test', [$violation]))->getMessage());
    }

    private function violation(string $clause): Violation
    {
        return new Violation(
            BlockingSchemaChangeRule::class,
            (new StatementParser)->parse("alter table `orders` {$clause}"),
            'not INSTANT',
            $clause,
        );
    }
}
