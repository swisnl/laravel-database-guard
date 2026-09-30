<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Runs against the MySQL connection configured by the DB_* environment variables.
 */
abstract class MySqlTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'mysql');
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL is not available: '.$e->getMessage());
        }

        Schema::dropAllViews();
        Schema::dropAllTables();
    }

    protected function createTableWithRows(string $table, int $rows): void
    {
        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->string('status')->default('new');
        });

        $this->fill($table, $rows);
    }

    protected function fill(string $table, int $rows): void
    {
        if ($rows > 0) {
            DB::table($table)->insert(array_fill(0, $rows, []));
        }

        // TABLE_ROWS is an estimate; analysing makes it exact for small tables.
        DB::statement("analyze table `{$table}`");
    }
}
