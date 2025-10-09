<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\BlingService;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\OrderHistoryService;
use App\Models\OrderSummary;
use App\Models\Invoice;
use Carbon\Carbon;

class BlingSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour
    public int $tries = 3;
    public int $backoff = 300; // 5 minutes between retries

    private bool $forceUpdate;
    private ?string $accountType;

    /**
     * Create a new job instance.
     * 
     * @param bool $forceUpdate Force update existing orders
     * @param string|null $accountType Account type to sync ('pro', 'atacadista', or null for all)
     */
    public function __construct(bool $forceUpdate = false, ?string $accountType = null)
    {
        $this->forceUpdate = $forceUpdate;
        $this->accountType = $accountType;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();
        
        Log::info('BlingSyncJob: Starting Bling synchronization', [
            'force_update' => $this->forceUpdate,
            'account_type' => $this->accountType ?? 'all',
            'started_at' => $startTime->toDateTimeString()
        ]);

        try {
            // Determine which services to sync
            $services = $this->getBlingServices();
            
            $totalProcessed = 0;
            $totalCreated = 0;
            $totalUpdated = 0;
            $totalSkipped = 0;
            $totalErrors = 0;

            foreach ($services as $accountType => $blingService) {
                Log::info("BlingSyncJob: Syncing account: {$accountType}");
                
                try {
                    $result = $this->syncAccount($blingService, $accountType);
                    
                    $totalProcessed += $result['processed'];
                    $totalCreated += $result['created'];
                    $totalUpdated += $result['updated'];
                    $totalSkipped += $result['skipped'];
                    $totalErrors += $result['errors'];
                    
                } catch (\Exception $e) {
                    $totalErrors++;
                    Log::error("BlingSyncJob: Error syncing account {$accountType}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $duration = now()->diffInSeconds($startTime);

            Log::info('BlingSyncJob: All synchronizations completed', [
                'accounts_synced' => count($services),
                'total_processed' => $totalProcessed,
                'total_created' => $totalCreated,
                'total_updated' => $totalUpdated,
                'total_skipped' => $totalSkipped,
                'total_errors' => $totalErrors,
                'duration_seconds' => $duration,
                'completed_at' => now()->toDateTimeString()
            ]);

        } catch (\Exception $e) {
            Log::error('BlingSyncJob: Fatal error during synchronization', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_seconds' => now()->diffInSeconds($startTime)
            ]);
            
            throw $e;
        }
    }

    /**
     * Get Bling services to sync based on account type
     */
    private function getBlingServices(): array
    {
        $services = [];

        if ($this->accountType === null) {
            // Sync all accounts
            $services['pro'] = app(CollmeProBlingService::class);
            $services['atacadista'] = app(CollmeAtacadistaBlingService::class);
        } elseif ($this->accountType === 'pro') {
            $services['pro'] = app(CollmeProBlingService::class);
        } elseif ($this->accountType === 'atacadista') {
            $services['atacadista'] = app(CollmeAtacadistaBlingService::class);
        } else {
            throw new \Exception("Invalid account type: {$this->accountType}");
        }

        return $services;
    }

    /**
     * Sync a single Bling account
     */
    private function syncAccount(BlingService $blingService, string $accountType): array
    {
        // Get orders from Bling
        $orders = $blingService->getAllOrders();
        
        if (empty($orders)) {
            Log::info("BlingSyncJob: No orders found to sync for account: {$accountType}");
            return [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        Log::info("BlingSyncJob: Found orders to process for account {$accountType}", [
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
                $result = $this->processOrder($blingService, $blingOrder);
                
                if ($result['created']) {
                    $created++;
                } elseif ($result['updated']) {
                    $updated++;
                } elseif ($result['skipped'] ?? false) {
                    $skipped++;
                }
                
                $processed++;

            } catch (\Exception $e) {
                $errors++;
                Log::error("BlingSyncJob: Error processing order for account {$accountType}", [
                    'bling_id' => $blingOrder['id'] ?? 'unknown',
                    'numero' => $blingOrder['numero'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info("BlingSyncJob: Account {$accountType} sync completed", [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * Process a single Bling order
     */
    private function processOrder(BlingService $blingService, array $blingOrder): array
    {
        $blingId = $blingOrder['id'];
        
        // Check if order already exists
        $existingOrder = OrderSummary::where('bling_id', $blingId)->first();
        
        if ($existingOrder && !$this->forceUpdate) {
            // Check if order data is exactly the same
            if ($this->isOrderDataIdentical($existingOrder, $blingOrder)) {
                Log::debug('BlingSyncJob: Order data is identical, skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero']
                ]);
                return ['created' => false, 'updated' => false, 'skipped' => true];
            }
            
            // Check if order was updated since last sync
            $blingUpdatedAt = Carbon::parse($blingOrder['dataAlteracao'] ?? $blingOrder['data']);
            if ($existingOrder->updated_at && 
                $blingUpdatedAt->lte($existingOrder->updated_at)) {
                Log::debug('BlingSyncJob: Order not updated since last sync, skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero']
                ]);
                return ['created' => false, 'updated' => false, 'skipped' => true];
            }
        }

        DB::beginTransaction();
        
        try {
            // Get complete order data for raw storage
            $completeOrderData = $blingService->getCompleteOrderData($blingId);
            if (!$completeOrderData) {
                throw new \Exception("Failed to fetch complete order data for ID {$blingId}");
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
                app(OrderHistoryService::class)->recordOrderImported($order, 'bling', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'total' => $blingOrder['total'] ?? null,
                ]);
            }

            // Create or update order status if not exists
            if (!$order->orderStatus) {
                \App\Models\OrderStatus::create([
                    'order_summary_id' => $order->id,
                    'picking' => false,
                    'packing' => false,
                    'picked_up' => false,
                ]);
            }

            // Sync invoices (NFE) and tracking with complete data
            $this->syncInvoicesAndTrackingWithCompleteData($blingService, $order, $completeOrderData);

            DB::commit();

            Log::debug('BlingSyncJob: Order processed successfully', [
                'bling_id' => $blingId,
                'order_id' => $order->id,
                'numero' => $blingOrder['numero'],
                'action' => $created ? 'created' : 'updated'
            ]);

            return ['created' => $created, 'updated' => $updated, 'order' => $order];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Sync invoices and tracking for an order with complete data
     */
    private function syncInvoicesAndTrackingWithCompleteData(BlingService $blingService, OrderSummary $order, array $completeOrderData): void
    {
        // Check if order has invoice ID in the complete data
        $invoiceId = $completeOrderData['notaFiscal']['id'] ?? null;
        
        if (!$invoiceId) {
            Log::debug('BlingSyncJob: No invoice ID found in order data', [
                'order_id' => $order->id,
                'numero' => $order->numero
            ]);
            return;
        }

        // Get complete invoice data
        $completeInvoiceData = $blingService->getCompleteInvoiceData($invoiceId);
        
        if (!$completeInvoiceData) {
            Log::debug('BlingSyncJob: Failed to fetch complete invoice data', [
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
        app(OrderHistoryService::class)->recordInvoiceSynced($order, [
            'invoice_id' => $invoiceId,
            'numero' => $completeInvoiceData['numero'] ?? null,
            'valor_total' => $completeInvoiceData['valorTotal'] ?? null,
        ]);

        // Check for tracking data in invoice transport volumes
        $trackingData = null;
        $volumes = $completeInvoiceData['transporte']['volumes'] ?? [];
        
        if (!empty($volumes)) {
            $volumeId = $volumes[0]['id'] ?? null;
            
            if ($volumeId) {
                // Get complete tracking data
                $completeTrackingData = $blingService->getCompleteTrackingData($volumeId);
                
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
                    app(OrderHistoryService::class)->recordTrackingFetched($order, [
                        'volume_id' => $volumeId,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                    ]);

                    Log::debug('BlingSyncJob: Complete tracking data updated for order', [
                        'order_id' => $order->id,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'volume_id' => $volumeId
                    ]);
                }
            }
        }

        Log::debug('BlingSyncJob: Complete data sync finished for order', [
            'order_id' => $order->id,
            'invoice_id' => $invoiceId,
            'has_tracking' => $trackingData !== null
        ]);
    }

    /**
     * Extract logistics provider from tracking data
     */
    private function extractLogisticsProvider(array $trackingData, array $invoiceData = null): ?string
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
    private function mapBlingOrderToOrder(array $blingOrder): array
    {
        $contact = $blingOrder['contato'] ?? [];
        
        return [
            'bling_id' => $blingOrder['id'],
            'numero' => $blingOrder['numero'],
            'numero_loja' => $blingOrder['numeroLoja'] ?? $blingOrder['numero'],
            'data' => $this->parseValidDate($blingOrder['data']),
            'data_saida' => $this->parseValidDate($blingOrder['dataSaida'] ?? null),
            'data_prevista' => $this->parseValidDate($blingOrder['dataPrevista'] ?? null),
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
     * Parse and validate date from Bling API
     * Returns null for invalid dates like "0000-00-00"
     */
    private function parseValidDate(?string $date): ?Carbon
    {
        if (empty($date)) {
            return null;
        }

        // Check for invalid dates that Bling might return
        if (in_array($date, ['0000-00-00', '0000-00-00 00:00:00', ''])) {
            return null;
        }

        try {
            $parsedDate = Carbon::parse($date);
            
            // Additional validation: check if year is reasonable (after 1900)
            if ($parsedDate->year < 1900) {
                return null;
            }
            
            return $parsedDate;
        } catch (\Exception $e) {
            Log::warning('BlingSyncJob: Failed to parse date', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Map Bling invoice data to our Invoice model
     */
    private function mapBlingInvoiceToInvoice(array $blingInvoice, int $orderId): array
    {
        return [
            'order_id' => $orderId,
            'bling_id' => $blingInvoice['id'],
            'numero' => $blingInvoice['numero'],
            'serie' => $blingInvoice['serie'] ?? null,
            'data_emissao' => Carbon::parse($blingInvoice['dataEmissao']),
            'valor_total' => (float) ($blingInvoice['valorTotal'] ?? 0),
            'chave_acesso' => $blingInvoice['chaveAcesso'] ?? null,
            'link_danfe' => $blingInvoice['linkDanfe'] ?? null,
            'status' => $blingInvoice['situacao'] ?? 'unknown',
        ];
    }

    /**
     * Check if order data is identical to existing order
     */
    private function isOrderDataIdentical(OrderSummary $existingOrder, array $blingOrder): bool
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
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BlingSyncJob: Job failed permanently', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'force_update' => $this->forceUpdate
        ]);
    }
}
