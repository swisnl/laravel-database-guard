<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;

final class ReturnsViolationRule extends FakeRule
{
    public function check(ParsedStatement $statement, AnalysisContext $context): Violation
    {
        return Violation::of($this, $statement, 'returned');
    }
}
