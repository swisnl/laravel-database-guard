<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

use Swis\Laravel\DatabaseGuard\Rules\Rule;

/**
 * A configuration error in an exemption, as opposed to a statement violation.
 */
final class InvalidExemption extends \LogicException
{
    public static function notARule(string $rule): self
    {
        return new self("{$rule} does not implement ".Rule::class);
    }

    public static function notACondition(mixed $condition): self
    {
        $name = match (true) {
            is_object($condition) => $condition::class,
            is_string($condition) => $condition,
            default => get_debug_type($condition),
        };

        return new self("{$name} does not implement ".Condition::class);
    }

    /**
     * @param  class-string<Rule>  $rule
     */
    public static function notApplicable(string $rule, Condition $condition): self
    {
        return new self(sprintf(
            '%s does not mitigate %s (accepted: %s)',
            class_basename($condition),
            class_basename($rule),
            implode(', ', array_map(class_basename(...), $rule::acceptedExemptions())) ?: 'none',
        ));
    }

    public static function invalidConfig(string $reason): self
    {
        return new self("Invalid database-guard.exemptions entry: {$reason}");
    }

    public static function unconditional(string $rule): self
    {
        return new self('Unconditional exemption from '.class_basename($rule).' is not allowed; add conditions via `when:`.');
    }
}
