<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bling API Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para integração com a API do Bling
    | Documentação: https://ajuda.bling.com.br/hc/pt-br/articles/360046967853
    |
    */

    'api' => [
        'base_url' => env('BLING_API_URL', 'https://api.bling.com.br/Api/v3'),
        'api_key' => env('BLING_API_KEY'),
        'timeout' => env('BLING_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Limites da API do Bling:
    | - 3 requisições por segundo
    | - 120.000 requisições por dia
    |
    */

    'rate_limit' => [
        'requests_per_second' => 3,
        'requests_per_day' => 120000,
        'retry_after_seconds' => 1, // Tempo de espera entre requisições
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Parameters
    |--------------------------------------------------------------------------
    |
    | Parâmetros padrão para busca de notas fiscais
    |
    */

    'defaults' => [
        'invoice_status' => 5, // 5 = Autorizada
        'invoice_type' => 1,   // 1 = Saída
        'limit_per_page' => 100,
        'max_pages' => 10, // Máximo de páginas para buscar
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Status Mapping
    |--------------------------------------------------------------------------
    |
    | Mapeamento dos status das notas fiscais no Bling
    |
    */

    'invoice_status' => [
        1 => 'Pendente',
        2 => 'Cancelada',
        3 => 'Aguardando recibo',
        4 => 'Rejeitada',
        5 => 'Autorizada',
        6 => 'Emitida DANFE',
        7 => 'Registrada',
        8 => 'Aguardando protocolo',
        9 => 'Denegada',
        10 => 'Consulta situação',
        11 => 'Bloqueada',
    ],

    /*
    |--------------------------------------------------------------------------
    | Invoice Type Mapping
    |--------------------------------------------------------------------------
    |
    | Mapeamento dos tipos de notas fiscais
    |
    */

    'invoice_type' => [
        0 => 'Entrada',
        1 => 'Saída',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configurações para sincronização automática
    |
    */

    'sync' => [
        'enabled' => env('BLING_SYNC_ENABLED', true),
        'batch_size' => env('BLING_SYNC_BATCH_SIZE', 50),
        'retry_attempts' => env('BLING_SYNC_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('BLING_SYNC_RETRY_DELAY', 60), // segundos
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configurações de log para debug
    |
    */

    'logging' => [
        'enabled' => env('BLING_LOGGING_ENABLED', true),
        'level' => env('BLING_LOG_LEVEL', 'info'),
        'log_requests' => env('BLING_LOG_REQUESTS', false),
        'log_responses' => env('BLING_LOG_RESPONSES', false),
    ],
];
