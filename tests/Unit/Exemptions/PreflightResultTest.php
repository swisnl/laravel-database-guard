<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Unit\Exemptions;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Swis\Laravel\DatabaseGuard\Exemptions\PreflightResult;

#[CoversClass(PreflightResult::class)]
final class PreflightResultTest extends TestCase
{
    #[Test]
    public function preflight_results_are_met_unmet_or_deferred(): void
    {
        $this->assertFalse(PreflightResult::met()->isUnmet());
        $this->assertFalse(PreflightResult::deferred()->isUnmet());
        $this->assertNull(PreflightResult::deferred()->met);

        $unmet = PreflightResult::unmet('table rows ≤ 10 (is ~20)');

        $this->assertTrue($unmet->isUnmet());
        $this->assertSame('table rows ≤ 10 (is ~20)', $unmet->description);
    }
}
