<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Migrations;

use Illuminate\Database\Migrations\Migration;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;

final class MigrationWithUnconditionalExemption extends Migration
{
    #[ExemptFrom(BlockingSchemaChangeRule::class, 'orders')]
    public function up(): void {}
}
