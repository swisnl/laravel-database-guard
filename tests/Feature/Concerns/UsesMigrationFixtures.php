<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature\Concerns;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

use function Orchestra\Testbench\workbench_path;

trait UsesMigrationFixtures
{
    /**
     * Relative to the base path, so it can be passed to --path with or without --realpath.
     */
    private string $migrationPath;

    private int $migrationSequence = 0;

    // Priority -1: after setUp(), which boots the application.
    #[Before(-1)]
    protected function createMigrationPath(): void
    {
        $this->migrationPath = 'database/migrations-'.uniqid();

        (new Filesystem)->ensureDirectoryExists(base_path($this->migrationPath));
    }

    // Priority 1: before tearDown(), which destroys the application. Also runs when setUp() skipped the test.
    #[After(1)]
    protected function deleteMigrationPath(): void
    {
        if (isset($this->migrationPath)) {
            (new Filesystem)->deleteDirectory(base_path($this->migrationPath));
        }
    }

    /**
     * Copies a migration from the fixtures, prefixed so migrations run in the order they are added.
     */
    private function migration(string $fixture): void
    {
        copy(
            workbench_path("database/migration-fixtures/{$fixture}.php"),
            base_path(sprintf('%s/2026_01_01_%06d_%s.php', $this->migrationPath, ++$this->migrationSequence, $fixture)),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function migrate(array $options = []): void
    {
        Artisan::call('migrate', ['--path' => $this->migrationPath, ...$options]);
    }
}
