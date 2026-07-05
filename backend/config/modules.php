<?php

return [
    /*
    | When false, the module gate is inert: every request passes through and the
    | app behaves exactly as today (all features on for everyone). Flip to true
    | only once entitlements have been seeded/backfilled and the frontend is ready.
    */
    'enforced' => (bool) env('MODULES_ENFORCED', false),

    // Effective months paid per year by billing period (matches the financial model).
    'billing_effective_months' => [
        'MONTHLY'   => 12,
        'QUARTERLY' => 10,
        'YEARLY'    => 8.4,
    ],
    // Foreign supplier yearly is a lighter discount (20 -> 200).
    'billing_effective_months_foreign' => [
        'MONTHLY'   => 12,
        'QUARTERLY' => 10,
        'YEARLY'    => 10,
    ],
];
