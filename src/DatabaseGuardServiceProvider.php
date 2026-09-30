<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\ServiceProvider;
use Swis\Laravel\DatabaseGuard\Console\MigratePreflightCommand;
use Swis\Laravel\DatabaseGuard\Migrations\GuardedMigrator;

class DatabaseGuardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/database-guard.php', 'database-guard');

        // extend() survives the deferred MigrationServiceProvider; afterResolving
        // callbacks (loadMigrationsFrom paths) fire on the replaced instance.
        $this->app->extend('migrator', fn (Migrator $original, Application $app): GuardedMigrator => new GuardedMigrator(
            $original->getRepository(),
            $app->make(ConnectionResolverInterface::class),
            $original->getFilesystem(),
            $app->make(Dispatcher::class),
        ));

        // Migrator::class is not bound to the migrator in early Laravel 12 releases.
        $this->app->when(MigratePreflightCommand::class)->needs(Migrator::class)->give(fn (Application $app) => $app->make('migrator'));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/database-guard.php' => config_path('database-guard.php'),
            ], 'database-guard');

            $this->commands([
                MigratePreflightCommand::class,
            ]);
        }
    }
}
