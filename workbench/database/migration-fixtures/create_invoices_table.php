<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', fn (Blueprint $table) => $table->id());
    }

    public function down(): void
    {
        DB::statement('set session lock_wait_timeout = 5');
        Schema::drop('invoices');
    }
};
