<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\RateLimiter\RateLimiter;

class WooCommerceService
{
    // Centralized rate limiting handled via RateLimiter service

    /**
     * Get seller information from WooCommerce order
     * 
     * @param string $woocommerceOrderId The WooCommerce order ID
     * @param string $accountType The account type ('pro' or 'atacadista')
     * @return array|null Returns ['id', 'name', 'email'] or null on failure
     */
    public function getOrderSeller(string $woocommerceOrderId, string $accountType): ?array
    {
        try {
            // Get environment variables based on account type
            $url = $this->getWooCommerceUrl($accountType, $woocommerceOrderId);
            $consumerKey = $this->getConsumerKey($accountType);
            $consumerSecret = $this->getConsumerSecret($accountType);

            if (!$url || !$consumerKey || !$consumerSecret) {
                Log::warning('WooCommerceService: Missing credentials', [
                    'account_type' => $accountType,
                    'order_id' => $woocommerceOrderId,
                ]);
                return null;
            }

            // Make request to WooCommerce API via centralized rate limiter
            $http = RateLimiter::for('woocommerce')->http(
                Http::withHeaders([
                    'Authorization' => 'Basic ' . base64_encode($consumerKey . ':' . $consumerSecret),
                ])->timeout(10),
                'woocommerce'
            );

            $response = $http->get($url);

            if (!$response->successful()) {
                // Check for rate limiting
                if ($response->status() === 429) {
                    Log::warning('WooCommerceService: Rate limit hit', [
                        'account_type' => $accountType,
                        'order_id' => $woocommerceOrderId,
                        'status_code' => $response->status(),
                    ]);
                    // Let the queue/job control retries; limiter already backs off
                    return null;
                }
                
                Log::warning('WooCommerceService: API request failed', [
                    'account_type' => $accountType,
                    'order_id' => $woocommerceOrderId,
                    'status_code' => $response->status(),
                ]);
                return null;
            }

            $orderData = $response->json();

            // Extract seller data
            if (!isset($orderData['seller'])) {
                Log::debug('WooCommerceService: No seller data in order', [
                    'account_type' => $accountType,
                    'order_id' => $woocommerceOrderId,
                ]);
                return null;
            }

            $seller = $orderData['seller'];

            return [
                'id' => $seller['id'] ?? null,
                'name' => $seller['name'] ?? null,
                'email' => $seller['email'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::warning('WooCommerceService: Exception fetching seller', [
                'account_type' => $accountType,
                'order_id' => $woocommerceOrderId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get WooCommerce URL for account type
     */
    private function getWooCommerceUrl(string $accountType, string $orderId): ?string
    {
        $baseUrl = match($accountType) {
            'pro' => env('WOOCOMMERCE_PRO_URL'),
            'atacadista' => env('WOOCOMMERCE_ATACADISTA_URL'),
            default => null,
        };

        if (!$baseUrl) {
            return null;
        }

        return $baseUrl . '/wp-json/wc/v3/orders/' . $orderId;
    }

    /**
     * Get WooCommerce consumer key for account type
     */
    private function getConsumerKey(string $accountType): ?string
    {
        return match($accountType) {
            'pro' => env('WOOCOMMERCE_PRO_CONSUMER_KEY'),
            'atacadista' => env('WOOCOMMERCE_ATACADISTA_CONSUMER_KEY'),
            default => null,
        };
    }

    /**
     * Get WooCommerce consumer secret for account type
     */
    private function getConsumerSecret(string $accountType): ?string
    {
        return match($accountType) {
            'pro' => env('WOOCOMMERCE_PRO_CONSUMER_SECRET'),
            'atacadista' => env('WOOCOMMERCE_ATACADISTA_CONSUMER_SECRET'),
            default => null,
        };
    }
}

