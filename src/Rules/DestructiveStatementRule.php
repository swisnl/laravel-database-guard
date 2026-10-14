<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\StatementKind;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;

/** DROP/TRUNCATE of existing tables: data loss, and a large file unlink stalls TOI. */
final class DestructiveStatementRule implements Rule
{
    public function check(ParsedStatement $statement, AnalysisContext $context): ?Violation
    {
        if (! in_array($statement->kind, [StatementKind::DropTable, StatementKind::Truncate], true)
            || $statement->table === null
            || $context->isNewTable($statement->table)) {
            return null;
        }

        return Violation::of($this, $statement, 'drops or truncates existing data');
    }

    public static function acceptedExemptions(): array
    {
        return [TableRowsAtMost::class];
    }
}
