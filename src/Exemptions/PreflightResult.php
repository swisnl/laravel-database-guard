<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Exemptions;

final readonly class PreflightResult
{
    private function __construct(
        public ?bool $met,
        public ?string $description = null,
    ) {}

    public static function met(): self
    {
        return new self(true);
    }

    public static function unmet(string $description): self
    {
        return new self(false, $description);
    }

    /**
     * Cannot be known before running; decided per statement at runtime.
     */
    public static function deferred(): self
    {
        return new self(null);
    }

    public function isUnmet(): bool
    {
        return $this->met === false;
    }
}
