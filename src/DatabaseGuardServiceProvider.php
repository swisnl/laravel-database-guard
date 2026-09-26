<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard;

use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class DatabaseGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/database-guard.php', 'database-guard');

        $this->app->singleton(
            DatabaseGuard::class,
            fn (Application $app): DatabaseGuard => new DatabaseGuard($app['config']->array('database-guard', []))
        );
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/database-guard.php' => config_path('database-guard.php'),
            ], 'database-guard');
        }
    }
}
