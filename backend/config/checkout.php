<?php

return [
    /*
    | checkout.com payment integration. When 'secret' is empty, module purchases
    | activate immediately (no charge) — useful for dev/testing. When set, a
    | purchase creates a PENDING subscription + a checkout.com Payment Link and
    | only activates on the payment webhook.
    */
    'secret'         => env('CHECKOUT_SECRET_KEY'),      // sk_... (or sk_sbox_...)
    'webhook_secret' => env('CHECKOUT_WEBHOOK_SECRET'),  // signing key for Cko-Signature
    'environment'    => env('CHECKOUT_ENV', 'sandbox'),  // sandbox | live

    'base_url' => env('CHECKOUT_ENV', 'sandbox') === 'live'
        ? 'https://api.checkout.com'
        : 'https://api.sandbox.checkout.com',

    // Where the customer returns after paying (frontend).
    'return_url' => rtrim(env('FRONTEND_URL', env('APP_URL', 'https://www.edgerx.app')), '/') . '/?modules=1',

    // ISO currencies with 3 decimal minor units (checkout.com expects minor units).
    'three_decimal_currencies' => ['KWD', 'BHD', 'OMR', 'JOD', 'TND'],
];
