<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BlingService
{
    private string $apiKey;
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->apiKey = config('services.bling.api_key');
        $this->baseUrl = config('services.bling.base_url', 'https://api.bling.com.br/Api/v3');
        $this->timeout = config('services.bling.timeout', 30);
    }

    /**
     * Apply rate limiting: 3 requests per second
     */
    private function applyRateLimit(): void
    {
        usleep(333333); // 333ms = 3 requests per second
    }

    /**
     * Get all orders from Bling
     */
    public function getAllOrders(array $filters = []): array
    {
        $orders = [];
        $pagina = 1;
        $limite = 100;
        $maxPages = 50; // Prevent infinite loops
        $requestCount = 0;

        do {
            try {
                Log::info("BlingService: Fetching page {$pagina} of orders");

                $response = $this->makeRequest('GET', '/pedidos/vendas', [
                    'pagina' => $pagina,
                    'limite' => $limite,
                    ...$filters
                ]);

                $requestCount++;
                
                if (!$response['success']) {
                    Log::error("BlingService: Failed to fetch orders on page {$pagina}", [
                        'error' => $response['error']
                    ]);
                    break;
                }

                $data = $response['data'];
                $pageOrders = $data['data'] ?? [];

                if (empty($pageOrders)) {
                    Log::info("BlingService: No more orders found on page {$pagina}, stopping pagination");
                    break;
                }

                $orders = array_merge($orders, $pageOrders);
                
                Log::info("BlingService: Fetched " . count($pageOrders) . " orders from page {$pagina}");

                // If we got less than the limit, we've reached the end
                if (count($pageOrders) < $limite) {
                    Log::info("BlingService: Got " . count($pageOrders) . " orders (less than limit {$limite}), stopping pagination");
                    break;
                }

                $pagina++;

                // Rate limiting: 3 requests per second
                if ($pagina <= $maxPages) {
                    $this->applyRateLimit();
                }

        } catch (\Exception $e) {
                Log::error("BlingService: Exception while fetching orders", [
                    'page' => $pagina,
                'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                break;
            }

        } while ($pagina <= $maxPages);

        Log::info("BlingService: Total orders fetched: " . count($orders) . " (requests made: {$requestCount})");

        return $orders;
    }

    /**
     * Get a specific order by ID with full details
     */
    public function getOrderById(int $orderId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/pedidos/vendas/{$orderId}");
            
            // Rate limiting: 3 requests per second
            $this->applyRateLimit();

            if (!$response['success']) {
                Log::error("BlingService: Failed to fetch order {$orderId}", [
                    'error' => $response['error']
                ]);
                return null;
            }

            return $response['data']['data'] ?? null;

        } catch (\Exception $e) {
            Log::error("BlingService: Exception while fetching order {$orderId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get invoices (NFE) for an order
     */
    public function getInvoicesForOrder(string $orderNumber): array
    {
        try {
            $response = $this->makeRequest('GET', '/nfe', [
                'numero' => $orderNumber,
                'limit' => 100
            ]);
            
            // Rate limiting: 3 requests per second
            $this->applyRateLimit();

            if (!$response['success']) {
                Log::error("BlingService: Failed to fetch invoices for order {$orderNumber}", [
                    'error' => $response['error']
                ]);
                return [];
            }

            return $response['data']['data'] ?? [];

        } catch (\Exception $e) {
            Log::error("BlingService: Exception while fetching invoices for order {$orderNumber}", [
                'order_number' => $orderNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Get tracking information from invoice transport data
     */
    public function getTrackingFromInvoice(array $invoice): ?array
    {
        if (!isset($invoice['transporte'])) {
            return null;
        }

        $transport = $invoice['transporte'];
        
        $tracking = [
            'tracking_code' => $transport['codigoRastreamento'] ?? null,
            'logistics_provider' => $transport['transportadora'] ?? null,
            'service_type' => $transport['tipoFrete'] ?? null,
            'volume' => $transport['volume'] ?? null,
            'weight' => $transport['peso'] ?? null,
        ];

        // Only return if we have at least tracking code
        return $tracking['tracking_code'] ? $tracking : null;
    }

    /**
     * Make HTTP request to Bling API
     */
    private function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
        ];

        try {
            Log::debug("BlingService: Making {$method} request to {$url}", [
                'params' => $params,
                'headers' => $headers
            ]);

            $response = Http::withHeaders($headers)
                ->timeout($this->timeout)
                ->$method($url, $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                ];
            }

            Log::error("BlingService: HTTP request failed", [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => "HTTP {$response->status()}: {$response->body()}",
                'status' => $response->status()
            ];

        } catch (\Exception $e) {
            Log::error("BlingService: Request exception", [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
                'params' => $params
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get complete order data by ID
     */
    public function getCompleteOrderData(int $orderId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/pedidos/vendas/{$orderId}");
            
            $this->applyRateLimit();

            if (!$response['success']) {
                Log::error("BlingService: Failed to fetch complete order data for ID {$orderId}", [
                    'error' => $response['error']
                ]);
                return null;
            }

            return $response['data']['data'] ?? null;

        } catch (\Exception $e) {
            Log::error("BlingService: Exception while fetching complete order data for ID {$orderId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get complete invoice data by ID
     */
    public function getCompleteInvoiceData(int $invoiceId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/nfe/{$invoiceId}");
            
            $this->applyRateLimit();

            if (!$response['success']) {
                Log::error("BlingService: Failed to fetch complete invoice data for ID {$invoiceId}", [
                    'error' => $response['error']
                ]);
                return null;
            }

            return $response['data']['data'] ?? null;

        } catch (\Exception $e) {
            Log::error("BlingService: Exception while fetching complete invoice data for ID {$invoiceId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get complete tracking data by volume ID
     */
    public function getCompleteTrackingData(int $volumeId): ?array
    {
        try {
            $response = $this->makeRequest('GET', "/logisticas/objetos/{$volumeId}");
            
            $this->applyRateLimit();

            if (!$response['success']) {
                Log::error("BlingService: Failed to fetch complete tracking data for volume ID {$volumeId}", [
                    'error' => $response['error']
                ]);
                return null;
            }

            return $response['data']['data'] ?? null;

        } catch (\Exception $e) {
            Log::error("BlingService: Exception while fetching complete tracking data for volume ID {$volumeId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Test API connection
     */
    public function testConnection(): array
    {
        try {
            // Test with pedidos endpoint (orders) which we have permission for
            $response = $this->makeRequest('GET', '/pedidos/vendas', ['limite' => 1]);

            if ($response['success']) {
                return [
                    'success' => true,
                    'message' => 'Bling API connection successful - Orders access confirmed'
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'] ?? 'Unknown error'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
