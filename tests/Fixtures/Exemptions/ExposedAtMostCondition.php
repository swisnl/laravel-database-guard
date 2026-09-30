<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\AtMostCondition;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Exemptions\PreflightResult;

/**
 * Exposes the limit resolution of AtMostCondition.
 */
final class ExposedAtMostCondition extends AtMostCondition
{
    public function limit(AnalysisContext $context): int
    {
        return $this->effectiveLimit($context);
    }

    public function description(AnalysisContext $context): string
    {
        return $this->limitDescription($context, fn (int $n): string => "{$n} things");
    }

    public function isMet(Violation $violation, AnalysisContext $context): bool
    {
        return true;
    }

    public function describe(Violation $violation, AnalysisContext $context): string
    {
        return '';
    }

    public function preflight(ExemptFrom $exemption, AnalysisContext $context): PreflightResult
    {
        return PreflightResult::deferred();
    }
}
