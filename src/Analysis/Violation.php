<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Analysis;

use Swis\Laravel\DatabaseGuard\Rules\Rule;

/**
 * A value object that can also be thrown from a rule.
 */
class Violation extends \RuntimeException
{
    /**
     * @var string[]
     */
    private array $unmetConditions = [];

    /**
     * @param  class-string<Rule>  $rule
     */
    public function __construct(
        public readonly string $rule,
        public readonly ParsedStatement $statement,
        string $message,
        public readonly ?string $clause = null,
    ) {
        parent::__construct($message);
    }

    public static function of(Rule $rule, ParsedStatement $statement, string $message, ?string $clause = null): self
    {
        return new self($rule::class, $statement, $message, $clause);
    }

    public function recordUnmetConditions(string $description): void
    {
        $this->unmetConditions[] = $description;
    }

    /**
     * @return string[]
     */
    public function unmetConditions(): array
    {
        return $this->unmetConditions;
    }
}
