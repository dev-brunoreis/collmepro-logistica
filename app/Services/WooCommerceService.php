<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class WooCommerceService
{
    private array $endpoints = [
        'collmepro' => 'https://pro.collmepro.com.br/app/wp-json/wc/v3/orders',
        'collmepro_atacadista' => 'https://atacadista.collmepro.com.br/app/wp-json/wc/v3/orders',
    ];

    private array $credentials = [
        'collmepro' => [
            'consumer_key' => null,
            'consumer_secret' => null,
        ],
        'collmepro_atacadista' => [
            'consumer_key' => null,
            'consumer_secret' => null,
        ],
    ];

    public function __construct()
    {
        $this->loadCredentials();
    }

    /**
     * Load API credentials from configuration.
     */
    private function loadCredentials(): void
    {
        $this->credentials['collmepro'] = [
            'consumer_key' => config('services.woocommerce.collmepro.consumer_key'),
            'consumer_secret' => config('services.woocommerce.collmepro.consumer_secret'),
        ];

        $this->credentials['collmepro_atacadista'] = [
            'consumer_key' => config('services.woocommerce.collmepro_atacadista.consumer_key'),
            'consumer_secret' => config('services.woocommerce.collmepro_atacadista.consumer_secret'),
        ];
    }

    /**
     * Fetch orders from WooCommerce API.
     */
    public function fetchOrders(string $system): array
    {
        try {
            if (!isset($this->endpoints[$system])) {
                throw new Exception("Invalid system: {$system}");
            }

            $response = $this->makeApiRequest($system);

            if (!$response->successful()) {
                Log::error("WooCommerce API error for {$system}", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $orders = $response->json();
            
            // Filter only processing orders and validate data
            return $this->filterAndValidateOrders($orders ?? []);

        } catch (Exception $e) {
            Log::error("WooCommerce service error for {$system}", [
                'error' => $e->getMessage(),
                'system' => $system
            ]);
            return [];
        }
    }

    /**
     * Make API request to WooCommerce.
     */
    private function makeApiRequest(string $system)
    {
        $endpoint = $this->endpoints[$system];
        $credentials = $this->credentials[$system];

        // Em ambiente de teste, usar credenciais mock
        if (app()->environment('testing')) {
            $credentials = [
                'consumer_key' => 'test_key',
                'consumer_secret' => 'test_secret'
            ];
        }

        if (!$credentials['consumer_key'] || !$credentials['consumer_secret']) {
            throw new Exception("Missing credentials for {$system}");
        }

        return Http::timeout(30)
            ->withBasicAuth($credentials['consumer_key'], $credentials['consumer_secret'])
            ->get($endpoint, [
                'status' => 'processing',
                'per_page' => 100,
                'orderby' => 'date',
                'order' => 'desc'
            ]);
    }

    /**
     * Filter and validate orders data.
     */
    private function filterAndValidateOrders(array $orders): array
    {
        $validOrders = [];

        foreach ($orders as $order) {
            if ($this->isValidOrder($order)) {
                $validOrders[] = $this->transformOrderData($order);
            }
        }

        return $validOrders;
    }

    /**
     * Check if order data is valid.
     */
    private function isValidOrder(array $order): bool
    {
        // Must have required fields
        $requiredFields = ['id', 'number', 'status', 'date_created', 'billing'];
        
        foreach ($requiredFields as $field) {
            if (!isset($order[$field]) || empty($order[$field])) {
                return false;
            }
        }

        // Must be processing status
        if ($order['status'] !== 'processing') {
            return false;
        }

        // Must have billing information
        if (!isset($order['billing']['first_name']) || !isset($order['billing']['last_name'])) {
            return false;
        }

        return true;
    }

    /**
     * Transform order data to our format.
     */
    private function transformOrderData(array $order): array
    {
        return [
            'id' => $order['id'],
            'number' => $order['number'],
            'status' => $order['status'],
            'customer_name' => $this->formatCustomerName($order['billing']),
            'seller_name' => $this->extractSellerName($order),
            'date_created' => $order['date_created'],
            'billing' => $order['billing'],
            'shipping' => $order['shipping'] ?? null,
            'line_items' => $order['line_items'] ?? [],
            'meta_data' => $order['meta_data'] ?? [],
        ];
    }

    /**
     * Format customer name from billing data.
     */
    private function formatCustomerName(array $billing): string
    {
        $firstName = $billing['first_name'] ?? '';
        $lastName = $billing['last_name'] ?? '';
        
        return trim($firstName . ' ' . $lastName);
    }

    /**
     * Extract seller name from order data.
     */
    private function extractSellerName(array $order): ?string
    {
        // Check if seller data exists
        if (isset($order['seller']['name'])) {
            return $order['seller']['name'];
        }

        // Check meta_data for seller information
        if (isset($order['meta_data'])) {
            foreach ($order['meta_data'] as $meta) {
                if (isset($meta['key']) && str_contains($meta['key'], 'seller')) {
                    return $meta['value'] ?? null;
                }
            }
        }

        return null;
    }

    /**
     * Test API connection.
     */
    public function testConnection(string $system): bool
    {
        try {
            $response = $this->makeApiRequest($system);
            return $response->successful();
        } catch (Exception $e) {
            Log::error("WooCommerce connection test failed for {$system}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get available systems.
     */
    public function getAvailableSystems(): array
    {
        return array_keys($this->endpoints);
    }

    /**
     * Get system endpoint.
     */
    public function getSystemEndpoint(string $system): ?string
    {
        return $this->endpoints[$system] ?? null;
    }
}
