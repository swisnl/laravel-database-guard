<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\Condition;

interface Rule
{
    /**
     * Return null, a Violation, or an iterable of Violations (a generator is fine),
     * or throw a Violation.
     *
     * @return Violation|iterable<Violation>|null
     */
    public function check(ParsedStatement $statement, AnalysisContext $context): Violation|iterable|null;

    /**
     * Conditions that mitigate this rule's risk.
     *
     * @return array<class-string<Condition>>
     */
    public static function acceptedExemptions(): array;
}
