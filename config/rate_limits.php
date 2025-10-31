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
            'rps' => 2.7, // 90% do limite de 3 req/s (margem de segurança de 10%)
            'rpm' => 160, // Permite até ~9600 req/hora (bem abaixo do limite diário de 120k)
            'burst' => 3, // Permite picos de até 3 req simultâneas (limite da API)
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


