<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;
use Swis\Laravel\DatabaseGuard\Analysis\Violation;
use Swis\Laravel\DatabaseGuard\Rules\Rule;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final readonly class ExemptFrom
{
    /** @var Condition[] */
    public array $when;

    /**
     * The rule and conditions are validated, because they come from attributes and config.
     *
     * @param  string  $rule  a class implementing Rule
     * @param  string|null  $table  unprefixed table name; null = any table
     * @param  Condition|array<mixed>  $when  Condition(s), all must be met; empty = unconditional
     */
    public function __construct(
        public string $rule,
        public ?string $table = null,
        Condition|array $when = [],
    ) {
        if (! is_a($rule, Rule::class, true)) {
            throw InvalidExemption::notARule($rule);
        }

        $conditions = is_array($when) ? $when : [$when];

        foreach ($conditions as $condition) {
            if (! $condition instanceof Condition) {
                throw InvalidExemption::notACondition($condition);
            }

            $accepted = array_filter($rule::acceptedExemptions(), fn (string $class): bool => $condition instanceof $class);

            if ($accepted === []) {
                throw InvalidExemption::notApplicable($rule, $condition);
            }
        }

        $this->when = $conditions;
    }

    public static function fromConfig(mixed $config): self
    {
        if (! is_array($config)) {
            throw InvalidExemption::invalidConfig('expected an array, got '.get_debug_type($config));
        }

        $rule = $config['rule'] ?? null;
        $table = $config['table'] ?? null;
        $conditions = $config['when'] ?? [];

        if (! is_string($rule)) {
            throw InvalidExemption::invalidConfig("'rule' must be a class name, got ".get_debug_type($rule));
        }

        if ($table !== null && ! is_string($table)) {
            throw InvalidExemption::invalidConfig("'table' must be a string or null, got ".get_debug_type($table));
        }

        if (! is_array($conditions)) {
            throw InvalidExemption::invalidConfig("'when' must map condition classes to constructor arguments, got ".get_debug_type($conditions));
        }

        $when = [];

        foreach ($conditions as $class => $args) {
            if (! is_string($class) || ! is_a($class, Condition::class, true)) {
                throw InvalidExemption::notACondition($class);
            }

            if (! is_array($args)) {
                throw InvalidExemption::invalidConfig("the constructor arguments of {$class} must be an array, got ".get_debug_type($args));
            }

            $when[] = new $class(...$args);
        }

        return new self($rule, $table, $when);
    }

    /** @return self[] class-level first, then method-level */
    public static function declaredOn(object $object, string $method): array
    {
        $attrs = (new \ReflectionClass($object))->getAttributes(self::class);

        if (method_exists($object, $method)) {
            $attrs = [...$attrs, ...(new \ReflectionMethod($object, $method))->getAttributes(self::class)];
        }

        $exemptions = array_map(fn (\ReflectionAttribute $attr): ExemptFrom => $attr->newInstance(), $attrs);

        if (! config('database-guard.allow_unconditional_exemptions')) {
            foreach ($exemptions as $exemption) {
                if ($exemption->when === []) {
                    throw InvalidExemption::unconditional($exemption->rule);
                }
            }
        }

        return $exemptions;
    }

    public function matches(Violation $violation, string $tablePrefix): bool
    {
        return is_a($violation->rule, $this->rule, true)
            && ($this->table === null || $violation->statement->table?->name === $tablePrefix.$this->table);
    }

    /**
     * @return string[] descriptions of unmet conditions
     */
    public function unmetConditions(Violation $violation, AnalysisContext $context): array
    {
        $unmet = [];

        foreach ($this->when as $condition) {
            if (! $condition->isMet($violation, $context)) {
                $unmet[] = $condition->describe($violation, $context);
            }
        }

        return $unmet;
    }

    public function describe(): string
    {
        return class_basename($this->rule).($this->table !== null ? " on $this->table" : '');
    }
}
