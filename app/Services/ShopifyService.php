<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ShopifyService
{
    private array $endpoints = [
        'collme_beauty' => 'https://collmebeauty.myshopify.com/admin/api/2024-01/orders.json',
    ];

    private array $credentials = [
        'collme_beauty' => [
            'access_token' => null,
            'shop_domain' => null,
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
        $this->credentials['collme_beauty'] = [
            'access_token' => config('services.shopify.collme_beauty.access_token'),
            'shop_domain' => config('services.shopify.collme_beauty.shop_domain'),
        ];
    }

    /**
     * Fetch orders from Shopify API.
     */
    public function fetchOrders(string $system): array
    {
        try {
            if (!isset($this->endpoints[$system])) {
                throw new Exception("Invalid system: {$system}");
            }

            $response = $this->makeApiRequest($system);

            if (!$response->successful()) {
                Log::error("Shopify API error for {$system}", [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            $data = $response->json();
            $orders = $data['orders'] ?? [];
            
            // Filter only paid orders and validate data
            return $this->filterAndValidateOrders($orders);

        } catch (Exception $e) {
            Log::error("Shopify service error for {$system}", [
                'error' => $e->getMessage(),
                'system' => $system
            ]);
            return [];
        }
    }

    /**
     * Make API request to Shopify.
     */
    private function makeApiRequest(string $system)
    {
        $endpoint = $this->endpoints[$system];
        $credentials = $this->credentials[$system];

        // Em ambiente de teste, usar credenciais mock
        if (app()->environment('testing')) {
            $credentials = [
                'access_token' => 'test_token',
                'shop_domain' => 'test.myshopify.com'
            ];
        }

        if (!$credentials['access_token'] || !$credentials['shop_domain']) {
            throw new Exception("Missing credentials for {$system}");
        }

        return Http::timeout(30)
            ->withHeaders([
                'X-Shopify-Access-Token' => $credentials['access_token'],
                'Content-Type' => 'application/json',
            ])
            ->get($endpoint, [
                'financial_status' => 'paid',
                'status' => 'open',
                'limit' => 100,
                'order' => 'created_at desc'
            ]);
    }

    /**
     * Filter and validate orders data.
     */
    private function filterAndValidateOrders(array $orders): array
    {
        $validOrders = [];

        foreach ($orders as $order) {
            // Validate required fields
            if (empty($order['name']) || empty($order['created_at']) || empty($order['customer'])) {
                Log::warning("Skipping invalid order", ['order' => $order]);
                continue;
            }

            // Extract seller information (if available)
            $sellerName = $order['note_attributes']['seller'] ?? 'N/A';

            $validOrders[] = [
                'number' => $order['name'],
                'date_created' => $order['created_at'],
                'customer_name' => $order['customer']['first_name'] . ' ' . $order['customer']['last_name'],
                'seller_name' => $sellerName,
            ];
        }

        return $validOrders;
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
            Log::error("Shopify connection test failed for {$system}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
