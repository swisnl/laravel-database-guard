<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Guard;

use Swis\Laravel\DatabaseGuard\Analysis\Violation;

final class StatementDenied extends \RuntimeException
{
    /**
     * @param  Violation[]  $violations
     */
    public function __construct(
        public readonly string $source,
        public readonly array $violations,
    ) {
        $lines = ["Denied statement in {$source}; it was not executed."];

        foreach ($violations as $violation) {
            $lines[] = sprintf(
                '  [%s] %s: %s%s',
                class_basename($violation->rule),
                $violation->statement->table->name ?? '-',
                $violation->getMessage(),
                $violation->clause !== null ? " `{$violation->clause}`" : '',
            );

            foreach ($violation->unmetConditions() as $unmet) {
                $lines[] = "      exemption condition not met: {$unmet}";
            }
        }

        $lines[] = '  '.mb_strimwidth($violations[0]->statement->sql, 0, 200, '…');
        $lines[] = 'Statements and migrations before this one have already been applied.';

        parent::__construct(implode("\n", $lines));
    }
}
