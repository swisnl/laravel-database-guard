<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Analysis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Analysis\StatementParser;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;

#[CoversClass(Violation::class)]
final class ViolationTest extends TestCase
{
    #[Test]
    public function violations_record_unmet_conditions(): void
    {
        $violation = new Violation(BlockingSchemaChangeRule::class, (new StatementParser)->parse('alter table `orders` add index `i`(`status`)'), 'not INSTANT', 'add index `i`(`status`)');
        $violation->recordUnmetConditions('first');
        $violation->recordUnmetConditions('second');

        $this->assertSame(['first', 'second'], $violation->unmetConditions());
        $this->assertSame(BlockingSchemaChangeRule::class, $violation->rule);
        $this->assertSame('add index `i`(`status`)', $violation->clause);
    }
}
