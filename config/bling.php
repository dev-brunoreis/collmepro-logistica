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
        'requests_per_second' => env('BLING_RATE_LIMIT', 1), // 1 request per second
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

    /*
    |--------------------------------------------------------------------------
    | Store Systems Mapping
    |--------------------------------------------------------------------------
    |
    | Mapping of store IDs to system names for identification
    |
    */
    'systems_map' => [
        '205404185' => [
            'name' => '(WooCommerce) Collme Beauty',
            'color' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
        ],
        '205274224' => [
            'name' => '(WooCommerce) Collme Pro',
            'color' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
        ],
        '205483126' => [
            'name' => '(Shopify) Collme Beauty',
            'color' => 'bg-pink-100 text-pink-800 dark:bg-pink-900 dark:text-pink-300',
        ],
        '205404186' => [
            'name' => '(WooCommerce) Collme Pro Atacadista',
            'color' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-300',
        ]
    ]
];