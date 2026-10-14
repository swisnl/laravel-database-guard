<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\TableReference;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;

final class TableRowsAtMost extends AtMostCondition
{
    public function isMet(Violation $violation, AnalysisContext $context): bool
    {
        $table = $violation->statement->table;

        return $table !== null && $context->tableRows($table) <= $this->effectiveLimit($context);
    }

    public function describe(Violation $violation, AnalysisContext $context): string
    {
        $table = $violation->statement->table;

        return $this->format($table !== null ? $context->tableRows($table) : null, $context);
    }

    public function preflight(ExemptFrom $exemption, AnalysisContext $context): PreflightResult
    {
        if ($exemption->table === null) {
            return PreflightResult::deferred(); // any table: only known per statement
        }

        $rows = $context->tableRows(new TableReference($context->connection->getTablePrefix().$exemption->table));

        return $rows <= $this->effectiveLimit($context)
            ? PreflightResult::met()
            : PreflightResult::unmet($this->format($rows, $context));
    }

    private function format(?int $rows, AnalysisContext $context): string
    {
        $limit = $this->limitDescription($context, fn (int $n): string => number_format($n));
        $actual = $rows !== null ? '~'.number_format($rows) : 'unknown table';

        return "table rows ≤ {$limit} (is {$actual})";
    }
}
