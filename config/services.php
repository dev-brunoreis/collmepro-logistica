<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'woocommerce' => [
        'collmepro' => [
            'consumer_key' => env('WOOCOMMERCE_COLLMEPRO_CONSUMER_KEY'),
            'consumer_secret' => env('WOOCOMMERCE_COLLMEPRO_CONSUMER_SECRET'),
        ],
        'collmepro_wholesale' => [
            'consumer_key' => env('WOOCOMMERCE_COLLMEPRO_WHOLESALE_CONSUMER_KEY'),
            'consumer_secret' => env('WOOCOMMERCE_COLLMEPRO_WHOLESALE_CONSUMER_SECRET'),
        ],
    ],

    'shopify' => [
        'collme_beauty' => [
            'access_token' => env('SHOPIFY_COLLME_BEAUTY_ACCESS_TOKEN'),
            'shop_domain' => env('SHOPIFY_COLLME_BEAUTY_SHOP_DOMAIN', 'collmebeauty.myshopify.com'),
        ],
    ],

    'bling' => [
        'bearer_token' => env('BLING_BEARER_TOKEN'),
    ],
];
