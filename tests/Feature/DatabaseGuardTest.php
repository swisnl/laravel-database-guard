<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Swis\Laravel\DatabaseGuard\DatabaseGuard;

#[CoversClass(DatabaseGuard::class)]
final class DatabaseGuardTest extends TestCase
{
    #[Test]
    public function it_can_be_disabled(): void
    {
        $app = $this->app;
        $this->assertNotNull($app);

        config(['database-guard.enabled' => false]);
        $app->forgetInstance(DatabaseGuard::class);

        $this->assertFalse($app->make(DatabaseGuard::class)->isEnabled());
    }
}
