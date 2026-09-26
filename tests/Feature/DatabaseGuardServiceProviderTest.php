<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature;

use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\DatabaseGuard;
use Swis\Laravel\DatabaseGuard\DatabaseGuardServiceProvider;

#[CoversClass(DatabaseGuardServiceProvider::class)]
final class DatabaseGuardServiceProviderTest extends TestCase
{
    #[Test]
    public function it_merges_the_config(): void
    {
        $this->assertTrue(config('database-guard.enabled'));
    }

    #[Test]
    public function it_registers_the_guard_as_singleton(): void
    {
        $app = $this->app;
        $this->assertNotNull($app);

        $this->assertInstanceOf(DatabaseGuard::class, $app->make(DatabaseGuard::class));
        $this->assertSame($app->make(DatabaseGuard::class), $app->make(DatabaseGuard::class));
    }

    #[Test]
    public function it_publishes_the_config(): void
    {
        $paths = ServiceProvider::pathsToPublish(DatabaseGuardServiceProvider::class, 'database-guard');

        $this->assertSame([config_path('database-guard.php')], array_values($paths));
    }
}
