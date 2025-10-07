<?php

namespace App\Services;

use App\Models\OrderSummary;
use App\Models\OrderStatus;
use App\Services\OrderHistoryService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

abstract class BlingService
{
    protected BlingOAuthService $oauthService;
    protected const BLING_API_BASE_URL = 'https://api.bling.com.br/Api/v3';

    public function __construct(BlingOAuthService $oauthService)
    {
        $this->oauthService = $oauthService;
    }

    /**
     * Get the account type for this service
     */
    abstract protected function getAccountType(): string;

    /**
     * Get the client ID for this account
     */
    abstract protected function getClientId(): string;

    /**
     * Get the client secret for this account
     */
    abstract protected function getClientSecret(): string;

    /**
     * Get the client ID for this account (public access)
     */
    public function getPublicClientId(): string
    {
        return $this->getClientId();
    }

    /**
     * Get the client secret for this account (public access)
     */
    public function getPublicClientSecret(): string
    {
        return $this->getClientSecret();
    }

    /**
     * Get a valid access token for API requests
     */
    protected function getValidToken(): string
    {
        return $this->oauthService->getValidToken(
            $this->getAccountType(),
            $this->getClientId(),
            $this->getClientSecret()
        );
    }

    /**
     * Make an authenticated request to Bling API
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getValidToken();
        
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->$method(self::BLING_API_BASE_URL . $endpoint, $data);

        if (!$response->successful()) {
            Log::error("Bling API request failed", [
                'method' => $method,
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
                'account' => $this->getAccountType(),
            ]);
            
            throw new Exception("Bling API request failed: " . $response->body());
        }

        return $response->json();
    }

    /**
     * Apply rate limiting: 3 requests per second
     */
    protected function applyRateLimit(): void
    {
        usleep(333333); // 333ms = 3 requests per second
    }

    /**
     * Sync orders from Bling
     */
    public function syncOrders(): array
    {
        try {
            Log::info("Starting order sync for account: {$this->getAccountType()}");
            
            $orders = $this->getAllOrders();
            
            if (empty($orders)) {
                Log::info("No orders found to sync for account: {$this->getAccountType()}");
                return [
                    'account' => $this->getAccountType(),
                    'total_orders' => 0,
                    'processed' => 0,
                    'failed' => 0,
                ];
            }

            Log::info("Found orders to process for account {$this->getAccountType()}", [
                'count' => count($orders)
            ]);

            // Process orders
            $processed = 0;
            $created = 0;
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            foreach ($orders as $blingOrder) {
                try {
                    $result = $this->processOrder($blingOrder);
                    
                    if ($result['created']) {
                        $created++;
                    } elseif ($result['updated']) {
                        $updated++;
                    } elseif ($result['skipped'] ?? false) {
                        $skipped++;
                    }
                    
                    $processed++;

                } catch (Exception $e) {
                    $errors++;
                    Log::error("Failed to process order {$blingOrder['id']}: " . $e->getMessage());
                }
            }

            Log::info("Order sync completed for account {$this->getAccountType()}: {$processed} orders processed");
            
            return [
                'account' => $this->getAccountType(),
                'total_orders' => count($orders),
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'failed' => $errors,
            ];

        } catch (Exception $e) {
            Log::error("Order sync failed for account {$this->getAccountType()}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get all orders from Bling with pagination
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
                Log::info("Fetching page {$pagina} of orders for account: {$this->getAccountType()}");

                $response = $this->makeRequest('GET', '/pedidos/vendas', [
                    'pagina' => $pagina,
                    'limite' => $limite,
                    ...$filters
                ]);

                $requestCount++;
                
                // Log the full response structure for debugging
                Log::info("API Response structure for account {$this->getAccountType()}", [
                    'has_data_key' => isset($response['data']),
                    'data_keys' => isset($response['data']) ? array_keys($response['data']) : [],
                    'full_response_keys' => array_keys($response),
                ]);
                
                $data = $response['data'] ?? [];
                // Bling v3 returns orders directly as an array, not nested in 'data'
                $pageOrders = is_array($data) ? $data : [];
                
                Log::info("Page orders count", [
                    'page' => $pagina,
                    'orders_count' => count($pageOrders),
                    'account' => $this->getAccountType(),
                ]);

                if (empty($pageOrders)) {
                    Log::info("No more orders found on page {$pagina}, stopping pagination");
                    break;
                }

                $orders = array_merge($orders, $pageOrders);
                
                Log::info("Fetched " . count($pageOrders) . " orders from page {$pagina}");

                // If we got less than the limit, we've reached the end
                if (count($pageOrders) < $limite) {
                    Log::info("Got " . count($pageOrders) . " orders (less than limit {$limite}), stopping pagination");
                    break;
                }

                $pagina++;

                // Rate limiting: 3 requests per second
                if ($pagina <= $maxPages) {
                    $this->applyRateLimit();
                }

            } catch (Exception $e) {
                Log::error("Exception while fetching orders", [
                    'page' => $pagina,
                    'account' => $this->getAccountType(),
                'error' => $e->getMessage(),
                ]);
                
                // If it's an authentication error, propagate it
                if (str_contains($e->getMessage(), 'No valid token found') || 
                    str_contains($e->getMessage(), '401') ||
                    str_contains($e->getMessage(), 'Unauthorized')) {
                    throw $e;
                }
                
                break;
            }

        } while ($pagina <= $maxPages);

        Log::info("Total orders fetched for account {$this->getAccountType()}: " . count($orders) . " (requests made: {$requestCount})");

        return $orders;
    }

    /**
     * Process individual order data - BASED ON ORIGINAL LOGIC
     */
    protected function processOrder(array $blingOrder): array
    {
        $blingId = $blingOrder['id'];
        
        // Check if order already exists
        $existingOrder = OrderSummary::where('bling_id', $blingId)
            ->where('source', 'bling')
            ->first();
        
        if ($existingOrder) {
            // Check if order data is exactly the same
            if ($this->isOrderDataIdentical($existingOrder, $blingOrder)) {
                Log::debug('Order data is identical, skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero']
                ]);
                return ['created' => false, 'updated' => false, 'skipped' => true];
            }
            
            // Check if order was updated since last sync
            $blingUpdatedAt = Carbon::parse($blingOrder['dataAlteracao'] ?? $blingOrder['data']);
            if ($existingOrder->updated_at && 
                $blingUpdatedAt->lte($existingOrder->updated_at)) {
                Log::debug('Order not updated since last sync, skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero']
                ]);
                return ['created' => false, 'updated' => false, 'skipped' => true];
            }
        }

        DB::beginTransaction();
        
        try {
            // Get complete order data for raw storage
            $completeOrderData = $this->getCompleteOrderData($blingId);
            if (!$completeOrderData) {
                throw new Exception("Failed to fetch complete order data for ID {$blingId}");
            }

            $orderData = $this->mapBlingOrderToOrder($completeOrderData);
            $orderData['raw_order_data'] = $completeOrderData;
            
            if ($existingOrder) {
                $existingOrder->update($orderData);
                $order = $existingOrder;
                $created = false;
                $updated = true;
            } else {
                $order = OrderSummary::create($orderData);
                $created = true;
                $updated = false;
                
                // Record order import in history
                if (class_exists(OrderHistoryService::class)) {
                    app(OrderHistoryService::class)->recordOrderImported($order, 'bling', [
                        'bling_id' => $blingId,
                        'numero' => $blingOrder['numero'],
                        'total' => $blingOrder['total'] ?? null,
                    ]);
                }
            }

            // Create or update order status if not exists
            if (!$order->orderStatus) {
                OrderStatus::create([
                    'order_summary_id' => $order->id,
                    'picking' => false,
                    'packing' => false,
                    'picked_up' => false,
                ]);
            }

            // Sync invoices (NFE) and tracking with complete data
            $this->syncInvoicesAndTrackingWithCompleteData($order, $completeOrderData);

            DB::commit();

            Log::debug('Order processed successfully', [
                'bling_id' => $blingId,
                'order_id' => $order->id,
                'numero' => $blingOrder['numero'],
                'action' => $created ? 'created' : 'updated'
            ]);

            return ['created' => $created, 'updated' => $updated, 'order' => $order];

        } catch (Exception $e) {
            DB::rollBack();
            throw $e;
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

            return $response['data'] ?? null;

        } catch (Exception $e) {
            Log::error("Exception while fetching complete order data for ID {$orderId}", [
                'error' => $e->getMessage(),
                'account' => $this->getAccountType(),
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

            return $response['data'] ?? null;

        } catch (Exception $e) {
            Log::error("Exception while fetching complete invoice data for ID {$invoiceId}", [
                'error' => $e->getMessage(),
                'account' => $this->getAccountType(),
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

            return $response['data'] ?? null;

        } catch (Exception $e) {
            Log::error("Exception while fetching complete tracking data for volume ID {$volumeId}", [
                'error' => $e->getMessage(),
                'account' => $this->getAccountType(),
            ]);
            return null;
        }
    }

    /**
     * Sync invoices and tracking for an order with complete data
     */
    protected function syncInvoicesAndTrackingWithCompleteData(OrderSummary $order, array $completeOrderData): void
    {
        // Check if order has invoice ID in the complete data
        $invoiceId = $completeOrderData['notaFiscal']['id'] ?? null;
        
        if (!$invoiceId) {
            Log::debug('No invoice ID found in order data', [
                'order_id' => $order->id,
                'numero' => $order->numero
            ]);
            return;
        }

        // Get complete invoice data
        $completeInvoiceData = $this->getCompleteInvoiceData($invoiceId);
        
        if (!$completeInvoiceData) {
            Log::debug('Failed to fetch complete invoice data', [
                'order_id' => $order->id,
                'invoice_id' => $invoiceId
            ]);
            return;
        }

        // Store complete invoice data
        $order->update([
            'raw_invoice_data' => $completeInvoiceData,
            'external_updated_at' => now(),
        ]);

        // Record invoice sync in history
        if (class_exists(OrderHistoryService::class)) {
            app(OrderHistoryService::class)->recordInvoiceSynced($order, [
                'invoice_id' => $invoiceId,
                'numero' => $completeInvoiceData['numero'] ?? null,
                'valor_total' => $completeInvoiceData['valorTotal'] ?? null,
            ]);
        }

        // Check for tracking data in invoice transport volumes
        $trackingData = null;
        $volumes = $completeInvoiceData['transporte']['volumes'] ?? [];
        
        if (!empty($volumes)) {
            $volumeId = $volumes[0]['id'] ?? null;
            
            if ($volumeId) {
                // Get complete tracking data
                $completeTrackingData = $this->getCompleteTrackingData($volumeId);
                
                if ($completeTrackingData) {
                    $trackingData = $completeTrackingData;
                    
                    // Store complete tracking data
                    $order->update([
                        'raw_tracking_data' => $completeTrackingData,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                        'service_type' => $completeTrackingData['servico']['id'] ?? null,
                        'volume' => $completeTrackingData['dimensao']['peso'] ?? null,
                        'weight' => $completeTrackingData['dimensao']['peso'] ?? null,
                        'external_updated_at' => now(),
                    ]);

                    // Record tracking fetch in history
                    if (class_exists(OrderHistoryService::class)) {
                        app(OrderHistoryService::class)->recordTrackingFetched($order, [
                            'volume_id' => $volumeId,
                            'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                            'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                        ]);
                    }

                    Log::debug('Complete tracking data updated for order', [
                        'order_id' => $order->id,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'volume_id' => $volumeId
                    ]);
                }
            }
        }

        Log::debug('Complete data sync finished for order', [
            'order_id' => $order->id,
            'invoice_id' => $invoiceId,
            'has_tracking' => $trackingData !== null
        ]);
    }

    /**
     * Extract logistics provider from tracking data
     */
    protected function extractLogisticsProvider(array $trackingData, array $invoiceData = null): ?string
    {
        // Extract service name from tracking data
        $serviceName = $trackingData['servico']['nome'] ?? 
                      $trackingData['embalagem']['nome'] ?? 
                      $trackingData['rastreamento']['descricao'] ?? 
                      null;

        // If we have invoice data, try to get the full provider name
        if ($invoiceData && isset($invoiceData['transporte']['servico'])) {
            $transportService = $invoiceData['transporte']['servico'];
            
            // Check if service name contains "PAC", "SEDEX", etc.
            if (stripos($serviceName, 'PAC') !== false || stripos($serviceName, 'SEDEX') !== false) {
                // Return "Correios - PAC" or "Correios - SEDEX"
                return "Correios - {$serviceName}";
            }
            
            // For other providers, use the service name as is
            if ($serviceName) {
                return $serviceName;
            }
        }
        
        return $serviceName;
    }

    /**
     * Map Bling order data to our OrderSummary model
     */
    protected function mapBlingOrderToOrder(array $blingOrder): array
    {
        $contact = $blingOrder['contato'] ?? [];

            return [
            'bling_id' => $blingOrder['id'],
            'numero' => $blingOrder['numero'],
            'numero_loja' => $blingOrder['numeroLoja'] ?? $blingOrder['numero'],
            'data' => Carbon::parse($blingOrder['data']),
            'data_saida' => isset($blingOrder['dataSaida']) ? Carbon::parse($blingOrder['dataSaida']) : null,
            'data_prevista' => isset($blingOrder['dataPrevista']) ? Carbon::parse($blingOrder['dataPrevista']) : null,
            'total' => (float) ($blingOrder['total'] ?? 0),
            'total_produtos' => count($blingOrder['itens'] ?? []),
            'contato_id' => $contact['id'] ?? 0,
            'contato_nome' => $contact['nome'] ?? 'N/A',
            'contato_tipo_pessoa' => $contact['tipoPessoa'] ?? 'F',
            'contato_documento' => $contact['numeroDocumento'] ?? null,
            'situacao_id' => $blingOrder['situacao']['id'] ?? 1,
            'situacao_valor' => $blingOrder['situacao']['valor'] ?? 1,
            'loja_id' => $blingOrder['loja']['id'] ?? 1,
            'source' => 'bling',
            'data_processed' => false,
            'external_updated_at' => now(),
        ];
    }

    /**
     * Check if order data is identical to existing order
     */
    protected function isOrderDataIdentical(OrderSummary $existingOrder, array $blingOrder): bool
    {
        // Compare key fields that would indicate if the order changed
        $contact = $blingOrder['contato'] ?? [];
        
        $existingData = [
            'numero' => $existingOrder->numero,
            'total' => (float) $existingOrder->total,
            'contato_nome' => $existingOrder->contato_nome,
            'contato_documento' => $existingOrder->contato_documento,
            'situacao_valor' => $existingOrder->situacao_valor,
        ];
        
        $newData = [
            'numero' => $blingOrder['numero'],
            'total' => (float) ($blingOrder['total'] ?? 0),
            'contato_nome' => $contact['nome'] ?? 'N/A',
            'contato_documento' => $contact['numeroDocumento'] ?? null,
            'situacao_valor' => $blingOrder['situacao']['valor'] ?? 1,
        ];
        
        return $existingData === $newData;
    }

    /**
     * Get orders with specific status
     */
    public function getOrdersByStatus(string $status): array
    {
        $response = $this->makeRequest('GET', "/pedidos/vendas?filtros=situacao[{$status}]");
        return $response['data'] ?? [];
    }

    /**
     * Get order by ID
     */
    public function getOrderById(string $orderId): array
    {
        $response = $this->makeRequest('GET', "/pedidos/vendas/{$orderId}");
        return $response['data'] ?? [];
    }

    /**
     * Get products
     */
    public function getProducts(): array
    {
        $response = $this->makeRequest('GET', '/produtos');
        return $response['data'] ?? [];
    }

    /**
     * Get contacts
     */
    public function getContacts(): array
    {
        $response = $this->makeRequest('GET', '/contatos');
        return $response['data'] ?? [];
    }

    /**
     * Get authorization URL for manual token setup
     */
    public function getAuthorizationUrl(): string
    {
        return $this->oauthService->getAuthorizationUrl(
            $this->getAccountType(),
            $this->getClientId()
        );
    }
}