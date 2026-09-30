<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;

interface Condition
{
    /**
     * Runtime: evaluated against the violation of the statement about to execute.
     */
    public function isMet(Violation $violation, AnalysisContext $context): bool;

    /**
     * Shown when unmet, e.g. "table rows ≤ 1,000 (is ~1,204,331)".
     */
    public function describe(Violation $violation, AnalysisContext $context): string;

    /**
     * Check the condition up front, without a statement to check against.
     *
     * Only the exemption itself is known; no statement has been analyzed and
     * no rule has reported a violation. Return met or unmet when that suffices,
     * e.g. the row count of the table the exemption names. Return deferred when
     * the outcome depends on a statement, e.g. an exemption for any table, or a
     * session variable that may still change; isMet() then decides per violation.
     */
    public function preflight(ExemptFrom $exemption, AnalysisContext $context): PreflightResult;
}
