<?php

declare(strict_types=1);

namespace Swis\Laravel\DatabaseGuard\Tests\Fixtures\Migrations;

use Illuminate\Database\Migrations\Migration;
use Swis\Laravel\DatabaseGuard\Exemptions\ExemptFrom;
use Swis\Laravel\DatabaseGuard\Exemptions\LockWaitTimeoutAtMost;
use Swis\Laravel\DatabaseGuard\Exemptions\TableRowsAtMost;
use Swis\Laravel\DatabaseGuard\Rules\BlockingSchemaChangeRule;
use Swis\Laravel\DatabaseGuard\Rules\MetadataLockRule;

#[ExemptFrom(MetadataLockRule::class, when: new LockWaitTimeoutAtMost(3))]
final class MigrationWithExemptions extends Migration
{
    #[ExemptFrom(BlockingSchemaChangeRule::class, 'orders', when: new TableRowsAtMost(100))]
    public function up(): void {}

    public function down(): void {}
}
