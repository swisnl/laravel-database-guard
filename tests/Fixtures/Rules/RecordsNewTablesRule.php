<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;

/**
 * Records whether the table of each statement was considered new while the rules ran.
 */
final class RecordsNewTablesRule extends FakeRule
{
    /** @var list<bool> */
    public array $seen = [];

    public function check(ParsedStatement $statement, AnalysisContext $context): ?Violation
    {
        if ($statement->table !== null) {
            $this->seen[] = $context->isNewTable($statement->table);
        }

        return null;
    }
}
