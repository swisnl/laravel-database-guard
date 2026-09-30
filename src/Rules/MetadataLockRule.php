<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Rules;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\ParsedStatement;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Exemptions\LockWaitTimeoutAtMost;

/**
 * Any DDL on an existing table needs an exclusive metadata lock, even INSTANT.
 * Behind a long transaction it queues, and every query on the table queues behind it.
 */
final class MetadataLockRule implements Rule
{
    public function check(ParsedStatement $statement, AnalysisContext $context): ?Violation
    {
        if (! $statement->isSchemaChange()
            || $statement->table === null
            || $context->isNewTable($statement->table)) {
            return null;
        }

        return Violation::of($this, $statement, 'requires an exclusive metadata lock');
    }

    public static function acceptedExemptions(): array
    {
        return [LockWaitTimeoutAtMost::class];
    }
}
