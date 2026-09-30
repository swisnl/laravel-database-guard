<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Console;

use Illuminate\Database\Console\Migrations\BaseCommand as MigrateBaseCommand;
use Illuminate\Database\Migrations\Migrator;
use Swis\Laravel\DatabaseGuard\Migrations\GuardedMigrator;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('migrate:database-guard-preflight')]
class MigratePreflightCommand extends MigrateBaseCommand
{
    protected $signature = <<<'STR'
        migrate:database-guard-preflight
            {--database= : The database connection to use}
            {--path=* : The path(s) to the migrations files to be checked}
            {--realpath : Indicate any provided migration file paths are pre-resolved absolute paths}
        STR;

    protected $description = 'Check exemption conditions of pending migrations without running them';

    public function __construct(
        protected readonly Migrator $migrator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $migrator = $this->migrator;

        if (! $migrator instanceof GuardedMigrator) {
            $this->components->error('The migrator is not guarded; is another package replacing it?');

            return self::FAILURE;
        }

        return $migrator->usingConnection($this->option('database'), function () use ($migrator): int {
            $result = $migrator->setOutput($this->output)
                ->preflight($this->getMigrationPaths());

            if ($result->isSuccessful()) {
                $this->components->info($result->migrationCount().' pending migration(s): exemption conditions met.');
                $this->components->info('Run `php artisan migrate --pretend` to analyse the statements themselves.');

                return self::SUCCESS;
            }

            foreach ($result->failures as $failure) {
                $this->components->error($failure);
            }

            return self::FAILURE;
        });
    }
}
