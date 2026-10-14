<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;

final class LockWaitTimeoutAtMost extends AtMostCondition
{
    public function isMet(Violation $violation, AnalysisContext $context): bool
    {
        return (int) $context->sessionVariable('lock_wait_timeout') <= $this->effectiveLimit($context);
    }

    public function describe(Violation $violation, AnalysisContext $context): string
    {
        $limit = $this->limitDescription($context, fn (int $n): string => "{$n}s");

        return "lock_wait_timeout ≤ {$limit} (is {$context->sessionVariable('lock_wait_timeout')}s)";
    }

    public function preflight(ExemptFrom $exemption, AnalysisContext $context): PreflightResult
    {
        return PreflightResult::deferred(); // the migration itself may SET it
    }
}
