<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Guard;

use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;

final class GuardScope
{
    /**
     * @var array<int, true>
     */
    private array $used = [];

    /**
     * @param  string  $source  human-readable origin, e.g. "2026_09_26_000001_add_reference_to_orders (up)"
     * @param  ExemptFrom[]  $exemptions
     */
    public function __construct(
        public readonly string $source,
        public readonly array $exemptions = [],
    ) {}

    public function markUsed(ExemptFrom $exemption): void
    {
        $this->used[spl_object_id($exemption)] = true;
    }

    /**
     * @return ExemptFrom[]
     */
    public function unused(): array
    {
        return array_values(array_filter(
            $this->exemptions,
            fn (ExemptFrom $exemption): bool => ! isset($this->used[spl_object_id($exemption)]),
        ));
    }
}
