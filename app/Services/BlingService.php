<?php

namespace App\Services;

use App\Models\OrderSummary;
use App\Models\OrderStatus;
use App\Models\Invoice;
use App\Services\OrderHistoryService;
use App\Services\SystemMappingService;
use App\Traits\BroadcastsOrderUpdates;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Exception;

abstract class BlingService
{
    use BroadcastsOrderUpdates;
    
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
     * Make an authenticated request to Bling API with retry logic
     * 
     * @throws Exception When all retries are exhausted
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $token = $this->getValidToken();
        $timeout = (int) env('BLING_API_TIMEOUT', 30);
        $maxAttempts = (int) env('BLING_API_RETRY_ATTEMPTS', 3);
        $attempt = 0;
        $lastException = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            
            try {
                $response = Http::withToken($token)
                    ->timeout($timeout)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ])
                    ->$method(self::BLING_API_BASE_URL . $endpoint, $data);

                // Handle successful response
                if ($response->successful()) {
                    // Log retry success if this wasn't the first attempt
                    if ($attempt > 1) {
                        Log::channel('bling_sync')->info("Bling API request succeeded after retry", [
                            'method' => $method,
                            'endpoint' => $endpoint,
                            'attempt' => $attempt,
                            'account' => $this->getAccountType(),
                        ]);
                    }
                    return $response->json();
                }

                // Handle different error types
                $status = $response->status();
                $errorBody = $response->body();
                
                // 404 - Resource not found (don't retry)
                if ($status === 404) {
                    Log::channel('bling_sync')->warning("Bling API resource not found", [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'status' => $status,
                        'account' => $this->getAccountType(),
                    ]);
                    throw new Exception("Resource not found: {$endpoint}");
                }

                // 429 - Rate limit exceeded (let queue handle retry)
                if ($status === 429) {
                    Log::channel('bling_sync')->warning("Bling API rate limit hit - job will retry later", [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'retry_after' => $response->header('Retry-After', 2),
                        'account' => $this->getAccountType(),
                    ]);
                    
                    // Don't retry immediately - let the job queue handle it with exponential backoff
                    throw new Exception("Rate limit exceeded, job will retry later");
                }

                // 401 - Unauthorized (don't retry, token issue)
                if ($status === 401) {
                    Log::channel('bling_sync')->error("Bling API authentication failed", [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'account' => $this->getAccountType(),
                    ]);
                    throw new Exception("Authentication failed for account: " . $this->getAccountType());
                }

                // 5xx - Server errors (retry with exponential backoff)
                if ($status >= 500) {
                    $backoffDelay = min(pow(2, $attempt - 1), 8); // Max 8 seconds
                    
                    Log::channel('bling_sync')->warning("Bling API server error", [
                        'method' => $method,
                        'endpoint' => $endpoint,
                        'status' => $status,
                        'attempt' => $attempt,
                        'backoff_seconds' => $backoffDelay,
                        'account' => $this->getAccountType(),
                    ]);
                    
                    if ($attempt < $maxAttempts) {
                        sleep($backoffDelay);
                        continue;
                    }
                }

                // Other errors
                Log::channel('bling_sync')->error("Bling API request failed", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'status' => $status,
                    'body' => substr($errorBody, 0, 500), // Limit error body size
                    'attempt' => $attempt,
                    'account' => $this->getAccountType(),
                ]);
                
                throw new Exception("Bling API request failed (HTTP {$status}): " . substr($errorBody, 0, 200));

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                // Network/timeout errors - retry with exponential backoff
                $backoffDelay = min(pow(2, $attempt - 1), 8);
                
                $lastException = $e;
                
                Log::channel('bling_sync')->warning("Bling API connection error", [
                    'method' => $method,
                    'endpoint' => $endpoint,
                    'error' => $e->getMessage(),
                    'attempt' => $attempt,
                    'backoff_seconds' => $backoffDelay,
                    'account' => $this->getAccountType(),
                ]);
                
                if ($attempt < $maxAttempts) {
                    sleep($backoffDelay);
                    continue;
                }
                
                throw new Exception(
                    "Connection failed after {$maxAttempts} attempts: " . $e->getMessage(),
                    0,
                    $e
                );
            }
        }

        // All retries exhausted
        throw new Exception(
            "Bling API request failed after {$maxAttempts} attempts" .
            ($lastException ? ": " . $lastException->getMessage() : "")
        );
    }

    /**
     * Apply rate limiting for Bling API (Account-wide limit approach)
     * 
     * Bling API Limits (official documentation):
     * - 3 requests per second (ACCOUNT-WIDE, not per worker)
     * - 120,000 requests per day
     * - IP blocking: 600 requests in 10 seconds = 60 req/s triggers 10-minute block
     * 
     * System has 3-4 processes making Bling API requests simultaneously:
     * - BlingSyncJob (fetching orders list)
     * - SyncInvoiceJob (fetching invoices)
     * - ExtractTrackingJob (fetching tracking)
     * - Plus any manual syncs or retries
     * 
     * Conservative approach accounting for multiple workers:
     * - Bling limit: 3 req/s (account-wide)
     * - Active workers: ~4 simultaneous
     * - Safe rate per worker: 3 / 4 = 0.75 req/s
     * - With safety margin: 0.5 req/s per worker = 2000ms delay
     * - Total: 4 workers × 0.5 req/s = 2 req/s (67% of limit)
     * 
     * This CONSERVATIVE rate ensures:
     * - 33% safety margin below the 3 req/s limit
     * - Zero 429 errors across all workers
     * - No risk of IP blocking
     * - Sync is SLOW but 100% RELIABLE
     */
    protected function applyRateLimit(): void
    {
        // Conservative delay: 2000ms = 0.5 requests per second per worker
        // With 4 workers max: 4 × 0.5 = 2 req/s total (67% of 3 req/s limit)
        usleep(2000000); // 2000ms - Safe with multiple workers
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
     * 
     * @param array $filters Additional filters for the API
     * @param bool $incrementalSync If true, fetch only orders from last 30 days
     * @return array
     */
    public function getAllOrders(array $filters = [], bool $incrementalSync = true, int $maxPages = 0, int $startPage = 1): array
    {
        // Para sincronização incremental, buscar apenas últimos 30 dias (1 mês)
        // Pedidos mais antigos raramente têm alterações
        // API Bling v3 usa parâmetros: dataAlteracaoInicial e dataAlteracaoFinal
        if ($incrementalSync && !isset($filters['dataAlteracaoInicial'])) {
            $since = now()->subDays(30)->format('Y-m-d H:i:s');
            $filters['dataAlteracaoInicial'] = $since;
            Log::info("Incremental sync enabled for account: {$this->getAccountType()}", [
                'since' => $since,
                'days_back' => 30,
                'parameter' => 'dataAlteracaoInicial'
            ]);
        }
        
        $orders = [];
        $pagina = $startPage;
        $limite = 100;
        $requestCount = 0;
        $pagesFetched = 0;

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
                $pagesFetched++;
                
                Log::info("Fetched " . count($pageOrders) . " orders from page {$pagina}");

                // Check if we've reached the maximum number of pages
                if ($maxPages > 0 && $pagesFetched >= $maxPages) {
                    Log::info("Reached max pages limit ({$maxPages}), stopping pagination");
                    break;
                }

                // If we got less than the limit, we've reached the end
                if (count($pageOrders) < $limite) {
                    Log::info("Got " . count($pageOrders) . " orders (less than limit {$limite}), stopping pagination");
                    break;
                }

                $pagina++;

                // Rate limiting: 3 requests per second
                $this->applyRateLimit();

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

        } while (true); // Continue until we hit one of the break conditions above

        Log::info("Total orders fetched for account {$this->getAccountType()}: " . count($orders) . " (requests made: {$requestCount}, pages fetched: {$pagesFetched})");

        return $orders;
    }

    /**
     * Process individual order data - BASED ON ORIGINAL LOGIC
     */
    protected function processOrder(array $blingOrder): array
    {
        $blingId = $blingOrder['id'];
        
        // Check if order already exists
        $existingOrder = OrderSummary::where('bling_id', $blingId)->first();
        
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
            $shouldSkip = $existingOrder->updated_at && 
                          $blingUpdatedAt->lte($existingOrder->updated_at);
            
            // NOVA LÓGICA: Sempre verificar mudanças críticas mesmo se não houver mudança de data
            if ($shouldSkip) {
                Log::info('BlingService: Checking for changes in order that should be skipped', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero']
                ]);
                
                // Verificar mudanças em campos críticos
                $currentSituacaoValor = $blingOrder['situacao']['valor'] ?? 1;
                $currentSituacaoId = $blingOrder['situacao']['id'] ?? 1;
                $currentTotal = (float) ($blingOrder['total'] ?? 0);
                $currentContatoNome = $blingOrder['contato']['nome'] ?? '';
                $currentContatoDocumento = $blingOrder['contato']['numeroDocumento'] ?? '';
                $currentData = $blingOrder['data'] ?? '';
                $currentDataSaida = $blingOrder['dataSaida'] ?? '';
                $currentNumeroLoja = $blingOrder['numeroLoja'] ?? '';
                
                // Verificar se há nota fiscal no Bling
                $currentHasInvoice = !empty($blingOrder['notaFiscal']['id']);
                
                // Load invoice relationship before checking
                $existingOrder->load('invoice');
                $existingHasInvoice = !empty($existingOrder->invoice?->numero);
                
                $existingSituacaoValor = $existingOrder->situacao_valor;
                $existingSituacaoId = $existingOrder->situacao_id;
                $existingTotal = $existingOrder->total;
                $existingContatoNome = $existingOrder->contato_nome ?? '';
                $existingContatoDocumento = $existingOrder->contato_documento ?? '';
                $existingData = $existingOrder->data?->format('Y-m-d') ?? '';
                $existingDataSaida = $existingOrder->data_saida?->format('Y-m-d') ?? '';
                $existingNumeroLoja = $existingOrder->numero_loja ?? '';
                
                $hasChanges = (
                    $currentSituacaoValor !== $existingSituacaoValor ||
                    $currentSituacaoId !== $existingSituacaoId ||
                    $currentTotal !== $existingTotal ||
                    $currentContatoNome !== $existingContatoNome ||
                    $currentContatoDocumento !== $existingContatoDocumento ||
                    $currentData !== $existingData ||
                    $currentDataSaida !== $existingDataSaida ||
                    $currentNumeroLoja !== $existingNumeroLoja ||
                    $currentHasInvoice !== $existingHasInvoice
                );
                
                if ($hasChanges) {
                    Log::info('Order has changes, forcing sync', [
                        'bling_id' => $blingId,
                        'numero' => $blingOrder['numero'],
                        'changes' => [
                            'situacao_valor' => [$existingSituacaoValor, $currentSituacaoValor],
                            'situacao_id' => [$existingSituacaoId, $currentSituacaoId],
                            'total' => [$existingTotal, $currentTotal],
                            'contato_nome' => [$existingContatoNome, $currentContatoNome],
                            'contato_documento' => [$existingContatoDocumento, $currentContatoDocumento],
                            'data' => [$existingData, $currentData],
                            'data_saida' => [$existingDataSaida, $currentDataSaida],
                            'numero_loja' => [$existingNumeroLoja, $currentNumeroLoja],
                            'has_invoice' => [$existingHasInvoice, $currentHasInvoice],
                        ]
                    ]);
                    
                    // Mark critical changes detected (especially invoice changes)
                    if ($currentHasInvoice !== $existingHasInvoice) {
                        Log::info('BlingService: Critical invoice change detected', [
                            'bling_id' => $blingId,
                            'numero' => $blingOrder['numero'],
                            'has_invoice' => [$existingHasInvoice, $currentHasInvoice]
                        ]);
                    }
                    
                    // Não skip - continua para atualizar
                } else {
                    // SPECIAL CHECK: Always process orders with invoices to ensure Kanban displays correctly
                    // Even if no changes detected, we need to verify the frontend has correct data
                    $hasInvoice = !empty($blingOrder['notaFiscal']['id']);
                    
                    if ($hasInvoice) {
                        Log::info('BlingService: Order has invoice, forcing sync to verify Kanban display', [
                            'bling_id' => $blingId,
                            'numero' => $blingOrder['numero']
                        ]);
                        // Continue processing instead of skipping
                    } else {
                        Log::debug('Order not updated since last sync, skipping', [
                            'bling_id' => $blingId,
                            'numero' => $blingOrder['numero']
                        ]);
                        return ['created' => false, 'updated' => false, 'skipped' => true];
                    }
                }
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
            
            // Track changes before updating
            $changedFields = [];
            $changeTypes = [];
            $oldData = [];
            
            if ($existingOrder) {
                // Capture old values for comparison
                $oldData = [
                    'situacao_valor' => $existingOrder->situacao_valor,
                    'situacao_id' => $existingOrder->situacao_id,
                    'total' => $existingOrder->total,
                    'contato_nome' => $existingOrder->contato_nome,
                    'contato_documento' => $existingOrder->contato_documento,
                    'data' => $existingOrder->data,
                    'data_saida' => $existingOrder->data_saida,
                    'numero_loja' => $existingOrder->numero_loja,
                ];
                
                $existingOrder->update($orderData);
                $order = $existingOrder;
                $created = false;
                $updated = true;
                
                // Detect what changed
                if ($oldData['situacao_valor'] != $order->situacao_valor) {
                    $changedFields[] = 'situacao_valor';
                    $changeTypes[] = 'status_changed';
                }
                
                if ($oldData['situacao_id'] != $order->situacao_id) {
                    $changedFields[] = 'situacao_id';
                    if ($order->situacao_id === 12) {
                        $changeTypes[] = 'cancelled';
                        
                        // Reset all order statuses for cancelled orders
                        if ($order->orderStatus) {
                            $order->orderStatus->update([
                                'picking' => false,
                                'picking_marked_by' => null,
                                'picking_marked_at' => null,
                                'packing' => false,
                                'packing_marked_by' => null,
                                'packing_marked_at' => null,
                                'picked_up' => false,
                                'picked_up_marked_by' => null,
                                'picked_up_marked_at' => null,
                            ]);
                            
                            Log::info('Reset order statuses for cancelled order', [
                                'bling_id' => $blingId,
                                'numero' => $order->numero
                            ]);
                        }
                    } else {
                        $changeTypes[] = 'status_changed';
                    }
                }
                
                if ($oldData['total'] != $order->total) {
                    $changedFields[] = 'total';
                    $changeTypes[] = 'data_updated';
                }
                
                if ($oldData['contato_nome'] != $order->contato_nome) {
                    $changedFields[] = 'contato_nome';
                    $changeTypes[] = 'data_updated';
                }
                
                if ($oldData['contato_documento'] != $order->contato_documento) {
                    $changedFields[] = 'contato_documento';
                    $changeTypes[] = 'data_updated';
                }
                
                if ($oldData['data'] != $order->data) {
                    $changedFields[] = 'data';
                    $changeTypes[] = 'data_updated';
                }
                
                if ($oldData['data_saida'] != $order->data_saida) {
                    $changedFields[] = 'data_saida';
                    $changeTypes[] = 'data_updated';
                }
                
                if ($oldData['numero_loja'] != $order->numero_loja) {
                    $changedFields[] = 'numero_loja';
                    $changeTypes[] = 'data_updated';
                }
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

            // Capture invoice and tracking before sync for change detection
            $oldInvoiceNumber = $order->invoice?->numero ?? null;
            $oldTrackingCode = $order->tracking_code;
            
            // Sync invoices (NFE) and tracking with complete data
            $this->syncInvoicesAndTrackingWithCompleteData($order, $completeOrderData);
            
            // Refresh order to get updated data
            $order->refresh();
            $order->load(['invoice', 'orderStatus']);
            
            // Detect invoice and tracking changes
            $newInvoiceNumber = $order->invoice?->numero ?? null;
            $newTrackingCode = $order->tracking_code;
            
            // Detect invoice changes (added or modified) - for BOTH new and existing orders
            if ($oldInvoiceNumber !== $newInvoiceNumber) {
                $changedFields[] = 'invoice_number';
                if (!$oldInvoiceNumber && $newInvoiceNumber) {
                    $changeTypes[] = 'invoice_added';
                } else if ($oldInvoiceNumber && $newInvoiceNumber && $oldInvoiceNumber !== $newInvoiceNumber) {
                    $changeTypes[] = 'invoice_updated';
                } else if ($oldInvoiceNumber && !$newInvoiceNumber) {
                    $changeTypes[] = 'invoice_removed';
                }
            }
            
            // SPECIAL CASE: If order has invoice but no changes detected, 
            // it might be a case where invoice was added but broadcast was missed
            // Force a broadcast to ensure Kanban displays correctly
            if (empty($changedFields) && $newInvoiceNumber && $created) {
                $changedFields[] = 'invoice_number';
                $changeTypes[] = 'invoice_added';
                Log::info('BlingService: Forcing invoice broadcast for new order with existing invoice', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'invoice_number' => $newInvoiceNumber
                ]);
            }
            
            // Detect tracking changes (added or modified) - for BOTH new and existing orders
            if ($oldTrackingCode !== $newTrackingCode) {
                $changedFields[] = 'tracking_code';
                if (!$oldTrackingCode && $newTrackingCode) {
                    $changeTypes[] = 'tracking_added';
                } else if ($oldTrackingCode && $newTrackingCode && $oldTrackingCode !== $newTrackingCode) {
                    $changeTypes[] = 'tracking_updated';
                } else if ($oldTrackingCode && !$newTrackingCode) {
                    $changeTypes[] = 'tracking_removed';
                }
            }

            DB::commit();

            Log::debug('Order processed successfully', [
                'bling_id' => $blingId,
                'order_id' => $order->id,
                'numero' => $blingOrder['numero'],
                'action' => $created ? 'created' : 'updated',
                'changed_fields' => $changedFields,
                'change_types' => $changeTypes,
                'invoice_state' => [
                    'old_invoice' => $oldInvoiceNumber,
                    'new_invoice' => $newInvoiceNumber,
                    'has_invoice' => !empty($newInvoiceNumber)
                ],
                'tracking_state' => [
                    'old_tracking' => $oldTrackingCode,
                    'new_tracking' => $newTrackingCode,
                    'has_tracking' => !empty($newTrackingCode)
                ]
            ]);

            // Broadcast events only if there are meaningful changes or it's a new order (non-blocking)
            // SPECIAL CASE: Broadcast if order has invoice AND it's a new order (first sync)
            $hasInvoice = !empty($newInvoiceNumber);
            $shouldBroadcast = !empty($changedFields) || $created || ($hasInvoice && $created);
            
            if ($shouldBroadcast) {
                if ($created) {
                    // For new orders, always broadcast OrderCreated
                    $this->safeBroadcastOrderCreated($order);
                } else {
                    // For existing orders, only broadcast if there are actual changes
                    // If no changes detected but has invoice, force invoice_added change type
                    $finalChangeTypes = $changeTypes;
                    $finalChangedFields = $changedFields;
                    
                    if (empty($changedFields) && $hasInvoice && $created) {
                        $finalChangedFields = ['invoice_number'];
                        $finalChangeTypes = ['invoice_added'];
                        Log::info('BlingService: Forcing invoice broadcast for new order with existing invoice', [
                            'bling_id' => $blingId,
                            'numero' => $blingOrder['numero'],
                            'invoice_number' => $newInvoiceNumber
                        ]);
                    }
                    
                    $this->safeBroadcastOrderUpdate($order, $finalChangedFields, $finalChangeTypes);
                }
            }

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
     * Map Bling invoice data to Invoice model
     */
    protected function mapBlingInvoiceToInvoice(array $blingInvoice, int $orderSummaryId): array
    {
        $contato = $blingInvoice['contato'] ?? [];
        $endereco = $contato['endereco'] ?? [];
        $transporte = $blingInvoice['transporte'] ?? [];
        $transportador = $transporte['transportador'] ?? [];
        $etiqueta = $transporte['etiqueta'] ?? [];
        
        return [
            'order_summary_id' => $orderSummaryId,
            'bling_id' => $blingInvoice['id'],
            'tipo' => $blingInvoice['tipo'] ?? null,
            'situacao' => $blingInvoice['situacao'] ?? null,
            'numero' => $blingInvoice['numero'] ?? null,
            'data_emissao' => $this->parseBlingDate($blingInvoice['dataEmissao'] ?? null),
            'data_operacao' => $this->parseBlingDate($blingInvoice['dataOperacao'] ?? null),
            'chave_acesso' => $blingInvoice['chaveAcesso'] ?? null,
            'valor_nota' => (float) ($blingInvoice['valorNota'] ?? 0),
            'valor_frete' => (float) ($blingInvoice['valorFrete'] ?? 0),
            'xml' => $blingInvoice['xml'] ?? null,
            'link_danfe' => $blingInvoice['linkDanfe'] ?? null,
            'link_pdf' => $blingInvoice['linkPDF'] ?? null,
            'optante_simples_nacional' => $blingInvoice['optanteSimplesNacional'] ?? false,
            'numero_pedido_loja' => $blingInvoice['numeroPedidoLoja'] ?? null,
            'serie' => $blingInvoice['serie'] ?? null,
            'contato_id' => $contato['id'] ?? null,
            'contato_nome' => $contato['nome'] ?? null,
            'contato_documento' => $contato['numeroDocumento'] ?? null,
            'contato_ie' => $contato['ie'] ?? null,
            'contato_rg' => $contato['rg'] ?? null,
            'contato_telefone' => $contato['telefone'] ?? null,
            'contato_email' => $contato['email'] ?? null,
            'contato_endereco' => $endereco['endereco'] ?? null,
            'contato_numero' => $endereco['numero'] ?? null,
            'contato_complemento' => $endereco['complemento'] ?? null,
            'contato_bairro' => $endereco['bairro'] ?? null,
            'contato_cep' => $endereco['cep'] ?? null,
            'contato_municipio' => $endereco['municipio'] ?? null,
            'contato_uf' => $endereco['uf'] ?? null,
            'contato_pais' => $endereco['nomePais'] ?? null,
            'natureza_operacao_id' => $blingInvoice['naturezaOperacao']['id'] ?? null,
            'loja_id' => $blingInvoice['loja']['id'] ?? null,
            'vendedor_id' => $blingInvoice['vendedor']['id'] ?? null,
            'frete_por_conta' => $transporte['fretePorConta'] ?? null,
            'transportador_nome' => $transportador['nome'] ?? null,
            'transportador_documento' => $transportador['numeroDocumento'] ?? null,
            'etiqueta_nome' => $etiqueta['nome'] ?? null,
            'etiqueta_endereco' => $etiqueta['endereco'] ?? null,
            'etiqueta_numero' => $etiqueta['numero'] ?? null,
            'etiqueta_complemento' => $etiqueta['complemento'] ?? null,
            'etiqueta_municipio' => $etiqueta['municipio'] ?? null,
            'etiqueta_uf' => $etiqueta['uf'] ?? null,
            'etiqueta_cep' => $etiqueta['cep'] ?? null,
            'etiqueta_bairro' => $etiqueta['bairro'] ?? null,
            'itens' => $blingInvoice['itens'] ?? [],
            'parcelas' => $blingInvoice['parcelas'] ?? [],
            'volumes' => $transporte['volumes'] ?? [],
        ];
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

        // Check if invoice already exists before updateOrCreate
        $existingInvoice = Invoice::where('bling_id', $invoiceId)->first();
        $oldInvoiceNumber = $existingInvoice?->numero ?? null;
        
        // Create or update invoice record in invoices table
        $invoiceData = $this->mapBlingInvoiceToInvoice($completeInvoiceData, $order->id);
        $invoiceData['raw_data'] = $completeInvoiceData;
        
        $invoice = Invoice::updateOrCreate(
            ['bling_id' => $invoiceId],
            $invoiceData
        );

        // Update order timestamp
        $order->update([
            'external_updated_at' => now(),
        ]);

        // Force reload the invoice relationship to ensure it's available for change detection
        $order->unsetRelation('invoice');
        $order->load('invoice');

        // Only record invoice sync in history if invoice was created or updated
        $wasInvoiceCreated = !$existingInvoice;
        $wasInvoiceUpdated = $existingInvoice && ($oldInvoiceNumber !== $invoice->numero);
        
        if (($wasInvoiceCreated || $wasInvoiceUpdated) && class_exists(OrderHistoryService::class)) {
            app(OrderHistoryService::class)->recordInvoiceSynced($order, [
                'invoice_id' => $invoiceId,
                'numero' => $completeInvoiceData['numero'] ?? null,
                'valor_total' => $completeInvoiceData['valorNota'] ?? null,
            ]);
        }
        
        Log::debug('Invoice created/updated', [
            'invoice_id' => $invoice->id,
            'bling_id' => $invoiceId,
            'numero' => $invoice->numero
        ]);

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
                    
                    // Store tracking summary data (full data in tracking table)
                    $order->update([
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                        'service_type' => $completeTrackingData['servico']['nome'] ?? null, // Fix: store service name instead of ID
                        'volume' => $completeTrackingData['dimensao']['peso'] ?? null,
                        'weight' => $completeTrackingData['dimensao']['peso'] ?? null,
                        'external_updated_at' => now(),
                    ]);

                    // Check if tracking already exists before updateOrCreate
                    $existingTracking = \App\Models\Tracking::where('invoice_id', $invoice->id)
                        ->where('volume_id', $volumeId)
                        ->first();
                    $oldTrackingCode = $existingTracking?->codigo_rastreamento ?? null;
                    
                    // Create or update tracking record in tracking table
                    $trackingRecord = \App\Models\Tracking::updateOrCreate(
                        ['invoice_id' => $invoice->id, 'volume_id' => $volumeId],
                        [
                            'codigo_rastreamento' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                            'servico_id' => $completeTrackingData['servico']['id'] ?? null,
                            'descricao' => $completeTrackingData['rastreamento']['descricao'] ?? null,
                            'situacao' => $completeTrackingData['rastreamento']['situacao'] ?? null,
                            'url_rastreamento' => $completeTrackingData['rastreamento']['url'] ?? null,
                            'origem' => $completeTrackingData['origem'] ?? null,
                            'destino' => $completeTrackingData['destino'] ?? null,
                            'ultima_alteracao' => isset($completeTrackingData['rastreamento']['ultimaAlteracao']) 
                                && $completeTrackingData['rastreamento']['ultimaAlteracao'] !== '0000-00-00 00:00:00'
                                && $completeTrackingData['rastreamento']['ultimaAlteracao'] !== ''
                                && $completeTrackingData['rastreamento']['ultimaAlteracao'] !== '-0001-11-30 00:00:00'
                                ? \Carbon\Carbon::parse($completeTrackingData['rastreamento']['ultimaAlteracao']) 
                                : null,
                            'peso' => $completeTrackingData['dimensao']['peso'] ?? null,
                            'altura' => $completeTrackingData['dimensao']['altura'] ?? null,
                            'largura' => $completeTrackingData['dimensao']['largura'] ?? null,
                            'comprimento' => $completeTrackingData['dimensao']['comprimento'] ?? null,
                            'diametro' => $completeTrackingData['dimensao']['diametro'] ?? null,
                            'raw_data' => $completeTrackingData,
                            'last_sync_at' => now(),
                        ]
                    );

                    // Only record tracking fetch in history if tracking was created or updated
                    $wasTrackingCreated = !$existingTracking;
                    $wasTrackingUpdated = $existingTracking && ($oldTrackingCode !== $trackingRecord->codigo_rastreamento);
                    
                    if (($wasTrackingCreated || $wasTrackingUpdated) && class_exists(OrderHistoryService::class)) {
                        app(OrderHistoryService::class)->recordTrackingFetched($order, [
                            'volume_id' => $volumeId,
                            'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                            'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                        ]);
                    }

                    Log::debug('Complete tracking data updated for order', [
                        'order_id' => $order->id,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'volume_id' => $volumeId,
                        'service_name' => $completeTrackingData['servico']['nome'] ?? null,
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

        // Use CarrierService to get proper display name
        return CarrierService::getCarrierDisplay($serviceName);
    }

    /**
     * Parse Bling date safely, returning null for invalid dates
     */
    protected function parseBlingDate(?string $date): ?Carbon
    {
        // Check for invalid or empty dates
        if (empty($date) || 
            $date === '0000-00-00' || 
            $date === '0000-00-00 00:00:00' ||
            $date === '-0001-11-30 00:00:00' ||
            $date === '0000-00-00 00:00:00' ||
            $date === '-0001-11-30') {
            return null;
        }
        
        try {
            $parsed = Carbon::parse($date);
            // Additional check for invalid years
            if ($parsed->year < 1900 || $parsed->year > 2100) {
                return null;
            }
            return $parsed;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Map Bling order data to our OrderSummary model
     */
    protected function mapBlingOrderToOrder(array $blingOrder): array
    {
        $contact = $blingOrder['contato'] ?? [];
        $lojaId = $blingOrder['loja']['id'] ?? 1;
        
        // Map loja_id to system name using centralized service
        $source = SystemMappingService::mapLojaIdToSource($lojaId);

        return [
            'bling_id' => $blingOrder['id'],
            'numero' => $blingOrder['numero'],
            'numero_loja' => $blingOrder['numeroLoja'] ?? $blingOrder['numero'],
            'data' => $this->parseBlingDate($blingOrder['data']) ?? now(),
            'data_saida' => $this->parseBlingDate($blingOrder['dataSaida'] ?? null),
            'data_prevista' => $this->parseBlingDate($blingOrder['dataPrevista'] ?? null),
            'total' => (float) ($blingOrder['total'] ?? 0),
            'total_produtos' => count($blingOrder['itens'] ?? []),
            'contato_id' => $contact['id'] ?? 0,
            'contato_nome' => $contact['nome'] ?? 'N/A',
            'contato_tipo_pessoa' => $contact['tipoPessoa'] ?? 'F',
            'contato_documento' => $contact['numeroDocumento'] ?? null,
            'situacao_id' => $blingOrder['situacao']['id'] ?? 1,
            'situacao_valor' => $blingOrder['situacao']['valor'] ?? 1,
            'loja_id' => $lojaId,
            'source' => $source,
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
            'situacao_id' => $existingOrder->situacao_id,
        ];
        
        $newData = [
            'numero' => $blingOrder['numero'],
            'total' => (float) ($blingOrder['total'] ?? 0),
            'contato_nome' => $contact['nome'] ?? 'N/A',
            'contato_documento' => $contact['numeroDocumento'] ?? null,
            'situacao_valor' => $blingOrder['situacao']['valor'] ?? 1,
            'situacao_id' => $blingOrder['situacao']['id'] ?? 1,
        ];
        
        return $existingData === $newData;
    }

    /**
     * Generate MD5 hash of critical order fields for fast change detection
     * This is a lightweight hash for quick comparisons using list endpoint data
     * 
     * @param array $blingOrder Order data from Bling API (list endpoint)
     * @return string MD5 hash (32 characters)
     */
    public function generateLightweightHash(array $blingOrder): string
    {
        // Campos críticos do endpoint de lista (sem dados completos)
        $criticalData = [
            'id' => $blingOrder['id'],
            'numero' => $blingOrder['numero'],
            'total' => $blingOrder['total'] ?? 0,
            'situacao_id' => $blingOrder['situacao']['id'] ?? 1,
            'situacao_valor' => $blingOrder['situacao']['valor'] ?? 1,
            'dataAlteracao' => $blingOrder['dataAlteracao'] ?? '',
            'contato_nome' => $blingOrder['contato']['nome'] ?? '',
            'contato_documento' => $blingOrder['contato']['numeroDocumento'] ?? '',
            'notaFiscal_id' => $blingOrder['notaFiscal']['id'] ?? null,
        ];
        
        // Ordenar para garantir consistência
        ksort($criticalData);
        
        // Gerar MD5 do JSON
        return md5(json_encode($criticalData));
    }

    /**
     * Generate comprehensive MD5 hash including invoice and tracking data
     * This is the full hash used for complete change detection
     * 
     * @param array $completeOrderData Complete order data including invoice and tracking
     * @return string MD5 hash (32 characters)
     */
    public function generateOrderHash(array $completeOrderData): string
    {
        // Dados críticos do pedido
        $orderData = [
            'id' => $completeOrderData['id'],
            'numero' => $completeOrderData['numero'],
            'total' => $completeOrderData['total'] ?? 0,
            'situacao_id' => $completeOrderData['situacao']['id'] ?? 1,
            'situacao_valor' => $completeOrderData['situacao']['valor'] ?? 1,
            'dataAlteracao' => $completeOrderData['dataAlteracao'] ?? '',
            'contato_nome' => $completeOrderData['contato']['nome'] ?? '',
            'contato_documento' => $completeOrderData['contato']['numeroDocumento'] ?? '',
            'data' => $completeOrderData['data'] ?? '',
            'dataSaida' => $completeOrderData['dataSaida'] ?? '',
            'numeroLoja' => $completeOrderData['numeroLoja'] ?? '',
        ];
        
        // Dados da nota fiscal (se existir)
        $invoiceData = [];
        if (!empty($completeOrderData['notaFiscal']['id'])) {
            $invoiceData = [
                'id' => $completeOrderData['notaFiscal']['id'],
                'numero' => $completeOrderData['notaFiscal']['numero'] ?? null,
                'chaveAcesso' => $completeOrderData['notaFiscal']['chaveAcesso'] ?? null,
                'valorNota' => $completeOrderData['notaFiscal']['valorNota'] ?? null,
                'situacao' => $completeOrderData['notaFiscal']['situacao'] ?? null,
            ];
        }
        
        // Dados de rastreamento (se existir)
        $trackingData = [];
        if (!empty($completeOrderData['transporte']['volumes'])) {
            $volumes = $completeOrderData['transporte']['volumes'];
            if (!empty($volumes[0])) {
                $trackingData = [
                    'volume_id' => $volumes[0]['id'] ?? null,
                    'servico_id' => $volumes[0]['servico']['id'] ?? null,
                    'servico_nome' => $volumes[0]['servico']['nome'] ?? null,
                ];
            }
        }
        
        // Combinar todos os dados
        $criticalData = [
            'order' => $orderData,
            'invoice' => $invoiceData,
            'tracking' => $trackingData,
        ];
        
        // Ordenar para garantir consistência
        ksort($criticalData);
        ksort($criticalData['order']);
        ksort($criticalData['invoice']);
        ksort($criticalData['tracking']);
        
        // Gerar MD5 do JSON
        return md5(json_encode($criticalData));
    }

    /**
     * Get cached order hash
     * 
     * @param int $blingId Bling order ID
     * @return string|null Cached hash or null if not found
     */
    public function getCachedOrderHash(int $blingId): ?string
    {
        $cacheKey = "bling_order_hash_{$blingId}";
        return Cache::get($cacheKey);
    }

    /**
     * Set cached order hash with TTL
     * 
     * @param int $blingId Bling order ID
     * @param string $hash Hash to cache
     * @param int $ttlMinutes TTL in minutes (default: 120 = 2 hours)
     * @return void
     */
    public function setCachedOrderHash(int $blingId, string $hash, int $ttlMinutes = 120): void
    {
        $cacheKey = "bling_order_hash_{$blingId}";
        Cache::put($cacheKey, $hash, now()->addMinutes($ttlMinutes));
        
        Log::debug("Cached order hash", [
            'bling_id' => $blingId,
            'hash' => $hash,
            'ttl_minutes' => $ttlMinutes,
        ]);
    }

    /**
     * Invalidate cached order hash
     * 
     * @param int $blingId Bling order ID
     * @return void
     */
    public function invalidateOrderCache(int $blingId): void
    {
        $cacheKey = "bling_order_hash_{$blingId}";
        Cache::forget($cacheKey);
        
        Log::debug("Invalidated order cache", [
            'bling_id' => $blingId,
        ]);
    }

    /**
     * Clear all cached order hashes
     * 
     * @return int Number of keys cleared
     */
    public function clearAllOrderCache(): int
    {
        // This is a simple implementation
        // In production with Redis, you might want to use SCAN for better performance
        $cleared = 0;
        
        // Get all orders from database to clear their cache
        $orders = \App\Models\OrderSummary::pluck('bling_id');
        
        foreach ($orders as $blingId) {
            $this->invalidateOrderCache($blingId);
            $cleared++;
        }
        
        Log::info("Cleared all order cache", ['count' => $cleared]);
        
        return $cleared;
    }

    /**
     * Get cache statistics
     * 
     * @return array Cache hit/miss statistics
     */
    public function getCacheStats(): array
    {
        // Note: This is a simple implementation
        // For production, consider using dedicated cache stats tracking
        $orders = \App\Models\OrderSummary::limit(100)->pluck('bling_id');
        
        $hits = 0;
        $misses = 0;
        
        foreach ($orders as $blingId) {
            if ($this->getCachedOrderHash($blingId)) {
                $hits++;
            } else {
                $misses++;
            }
        }
        
        return [
            'sampled' => count($orders),
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => count($orders) > 0 ? round(($hits / count($orders)) * 100, 2) : 0,
        ];
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