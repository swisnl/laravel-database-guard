<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

use Swis\Laravel\DatabaseGuard\Rules\Rule;

final readonly class StatementAnalyser
{
    /**
     * @param  Rule[]  $rules
     */
    public function __construct(
        private StatementParser $parser,
        private array $rules,
    ) {}

    /**
     * @return Violation[]
     */
    public function analyse(string $sql, AnalysisContext $context): array
    {
        $statement = $this->parser->parse($sql);
        $violations = [];

        foreach ($this->rules as $rule) {
            try {
                $result = $rule->check($statement, $context);

                foreach ($result instanceof Violation ? [$result] : ($result ?? []) as $violation) {
                    $violations[] = $violation;
                }
            } catch (Violation $violation) {
                $violations[] = $violation;
            }
        }

        // After the rules, so a CREATE/DROP only affects later statements.
        $context->observe($statement);

        return $violations;
    }
}
