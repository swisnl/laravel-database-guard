<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;

final class YieldsViolationsRule extends FakeRule
{
    /**
     * @return iterable<Violation>
     */
    public function check(ParsedStatement $statement, AnalysisContext $context): iterable
    {
        yield Violation::of($this, $statement, 'yielded first');
        yield Violation::of($this, $statement, 'yielded second');
    }
}
