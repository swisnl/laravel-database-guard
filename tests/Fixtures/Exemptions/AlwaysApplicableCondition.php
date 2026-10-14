<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\Condition;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Exemptions\PreflightResult;

final class AlwaysApplicableCondition implements Condition
{
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
