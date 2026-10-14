<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\StatementKind;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;

/**
 * Rebuilds and index builds: under PXC TOI they stall the cluster for their full duration.
 */
final class BlockingSchemaChangeRule implements Rule
{
    /**
     * @return iterable<Violation>
     */
    public function check(ParsedStatement $statement, AnalysisContext $context): iterable
    {
        if ($statement->table === null || $context->isNewTable($statement->table)) {
            return;
        }

        if ($statement->kind === StatementKind::CreateIndex) {
            yield Violation::of($this, $statement, 'index build scans the whole table');
        }

        if ($statement->kind === StatementKind::AlterTable && ! InstantAlter::isExplicitlyInstant($statement->clauses)) {
            foreach ($statement->clauses as $clause) {
                if (! InstantAlter::isInstant($clause, $context->server())) {
                    yield Violation::of($this, $statement, 'not INSTANT: table rebuild or index build', $clause);
                }
            }
        }
    }

    public static function acceptedExemptions(): array
    {
        return [TableRowsAtMost::class];
    }
}
