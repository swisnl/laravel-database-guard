<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Rules;

use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;
use Swis\Laravel\DatabaseGuard\Rules\Rule;

abstract class FakeRule implements Rule
{
    public static function acceptedExemptions(): array
    {
        return [TableRowsAtMost::class];
    }
}
