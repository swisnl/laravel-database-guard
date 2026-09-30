<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;

return new class extends Migration
{
    #[ExemptFrom(BlockingSchemaChangeRule::class, 'orders')]
    public function up(): void
    {
        DB::statement('set session lock_wait_timeout = 5');
        Schema::table('orders', fn (Blueprint $table) => $table->index('status'));
    }
};
