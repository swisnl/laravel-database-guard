<?php

declare(strict_types=1);

use Swis\Laravel\DatabaseGuard\Exemptions;
use Swis\Laravel\DatabaseGuard\Rules;

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Whether the database guard is enabled.
    |
    */
    'enabled' => env('DB_GUARD_ENABLED', true),

    // Maintenance window escape hatch: DB_GUARD_BYPASS=true php artisan migrate
    'bypass' => env('DB_GUARD_BYPASS', false),

    // Rule::class, or Rule::class => [constructor args] (resolved via the container)
    'rules' => [
        Rules\BlockingSchemaChangeRule::class,
        Rules\DestructiveStatementRule::class,
        // Rules\MetadataLockRule::class,
    ],

    // Per condition: 'default' when the attribute omits a value,
    // 'max' as a ceiling no attribute can exceed.
    'conditions' => [
        Exemptions\TableRowsAtMost::class => [
            'default' => (int) env('DB_GUARD_TABLE_ROWS_DEFAULT', 1_000),
            'max' => (int) env('DB_GUARD_TABLE_ROWS_MAX', 10_000),
        ],
        Exemptions\LockWaitTimeoutAtMost::class => [
            'default' => 5,
            'max' => 10,
        ],
    ],

    // Applied to every migration. 'when' maps a condition class to its
    // constructor arguments; [] means "use the configured default".
    'exemptions' => [
        // [
        //    'rule' => Rules\BlockingSchemaChangeRule::class,
        //    'when' => [Exemptions\TableRowsAtMost::class => []],
        // ],
        // [
        //    'rule' => Rules\DestructiveStatementRule::class,
        //    'when' => [Exemptions\TableRowsAtMost::class => []],
        // ],
        // [
        //    'rule' => Rules\MetadataLockRule::class,
        //    'when' => [Exemptions\LockWaitTimeoutAtMost::class => []],
        // ],
    ],

    // Exemptions without conditions (`when:`) exempt unconditionally.
    'allow_unconditional_exemptions' => false,

    // Declared exemptions that matched no violation: 'warn' or 'ignore'.
    'unused_exemptions' => env('DB_GUARD_UNUSED_EXEMPTIONS', 'warn'),
];
