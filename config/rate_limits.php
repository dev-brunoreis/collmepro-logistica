<?php

return [
    'providers' => [
        'woocommerce' => [
            'rps' => 2,
            'rpm' => 120,
            'burst' => 4,
            'concurrency' => 2,
            'backoff' => ['base' => 300, 'cap' => 4000, 'jitter' => true],
            'respect_headers' => true,
        ],
        'bling' => [
            'rps' => 1,
            'rpm' => 60,
            'burst' => 2,
            'concurrency' => 1,
            'backoff' => ['base' => 500, 'cap' => 5000, 'jitter' => true],
            'respect_headers' => true,
        ],
    ],
    'defaults' => [
        'rps' => 1,
        'burst' => 1,
        'concurrency' => 1,
        'backoff' => ['base' => 300, 'cap' => 4000, 'jitter' => true],
    ],
];


