<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bling API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Bling API integration
    |
    */

    'api' => [
        'base_url' => env('BLING_BASE_URL', 'https://api.bling.com.br/Api/v3'),
        'bearer_token' => env('BLING_BEARER_TOKEN'),
        'timeout' => env('BLING_TIMEOUT', 30),
    ],

    'rate_limit' => [
        'requests_per_second' => env('BLING_RATE_LIMIT', 0.5), // 0.5 req/s per worker (2000ms delay)
        // Bling API limit: 3 req/s account-wide
        // With 4 workers: 4 × 0.5 = 2 req/s total (67% of limit)
    ],

    'defaults' => [
        'limit_per_page' => 100,
        'max_pages' => 10,
        'invoice_status' => 5, // Autorizada
        'invoice_type' => 'S', // Saída
    ],

    'logging' => [
        'enabled' => env('BLING_LOGGING_ENABLED', true),
        'log_requests' => env('BLING_LOG_REQUESTS', false),
        'log_responses' => env('BLING_LOG_RESPONSES', false),
    ],

];