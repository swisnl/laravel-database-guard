<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature;

use Orchestra\Testbench\TestCase as BaseTestCase;
use Swis\Laravel\DatabaseGuard\DatabaseGuardServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            DatabaseGuardServiceProvider::class,
        ];
    }
}
