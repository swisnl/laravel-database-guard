<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Guard;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;
use Swis\Laravel\DatabaseGuard\Guard\GuardScope;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;

#[CoversClass(GuardScope::class)]
final class GuardScopeTest extends TestCase
{
    #[Test]
    public function scopes_track_unused_exemptions(): void
    {
        $used = new ExemptFrom(BlockingSchemaChangeRule::class, 'orders', new TableRowsAtMost(10));
        $unused = new ExemptFrom(BlockingSchemaChangeRule::class, 'invoices', new TableRowsAtMost(10));
        $scope = new GuardScope('test', [$used, $unused]);

        $this->assertSame([$used, $unused], $scope->unused());

        $scope->markUsed($used);

        $this->assertSame([$unused], $scope->unused());
    }
}
