<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    |
    | Permet à l'application mobile Flutter (kairosmobile) d'appeler l'API
    | depuis n'importe quelle origine en production.
    | Ajoutez les domaines front-end éventuels dans CORS_ALLOWED_ORIGINS.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'up'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(
        array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', '*')))
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Total-Count', 'X-Page', 'X-Per-Page'],

    'max_age' => 86400, // 24h — préflight mis en cache par le navigateur

    'supports_credentials' => false,

];
