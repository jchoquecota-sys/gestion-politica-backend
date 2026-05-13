<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Configure which origins, methods, and headers are allowed.
    | Set FRONTEND_URL in your .env file to your Next.js URL (e.g. http://localhost:3000).
    | In production, replace it with your actual domain.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Read origins from .env so you never have to touch this file per environment.
    'allowed_origins' => array_filter(
        explode(',', env('FRONTEND_URL', 'http://localhost:3000'))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Required when using Sanctum cookie-based SPA auth. For pure token auth,
    // this can stay false — but enabling it causes no harm and allows both modes.
    'supports_credentials' => true,

];
