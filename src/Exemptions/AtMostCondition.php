<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

use Swis\Laravel\DatabaseGuard\Analysis\AnalysisContext;

/**
 * Shared default/ceiling resolution for "value ≤ limit" conditions.
 */
abstract class AtMostCondition implements Condition
{
    public function __construct(
        public readonly ?int $limit = null,
    ) {}

    protected function effectiveLimit(AnalysisContext $context): int
    {
        $settings = $context->conditionSettings(static::class);
        $limit = $this->limit ?? $this->setting($settings, 'default') ?? 0;
        $max = $this->setting($settings, 'max');

        return $max !== null ? min($limit, $max) : $limit;
    }

    /**
     * @param  callable(int): string  $format
     */
    protected function limitDescription(AnalysisContext $context, callable $format): string
    {
        $effective = $this->effectiveLimit($context);

        if ($this->limit !== null && $effective < $this->limit) {
            return sprintf('%s, capped from %s by config', $format($effective), $format($this->limit));
        }

        return $format($effective);
    }

    /**
     * @param  array<array-key, mixed>  $settings
     */
    private function setting(array $settings, string $key): ?int
    {
        $value = $settings[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_int($value) || $value < 0) {
            throw new \UnexpectedValueException(sprintf('The database-guard.conditions setting %s of %s must be a non-negative integer, got %s.', $key, static::class, get_debug_type($value)));
        }

        return $value;
    }
}
