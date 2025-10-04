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

    /**
     * Create a new job instance.
     */
    public function __construct(bool $forceUpdate = false)
    {
        $this->forceUpdate = $forceUpdate;
    }

    /**
     * Execute the job.
     */
    public function handle(BlingService $blingService): void
    {
        $orderHistoryService = app(OrderHistoryService::class);
        $startTime = now();
        
        Log::info('BlingSyncJob: Starting Bling synchronization', [
            'force_update' => $this->forceUpdate,
            'started_at' => $startTime->toDateTimeString()
        ]);

        try {
            // Test connection first
            $connectionTest = $blingService->testConnection();
            if (!$connectionTest['success']) {
                throw new \Exception("Bling API connection failed: {$connectionTest['error']}");
            }

            Log::info('BlingSyncJob: API connection successful');

            // Get orders from Bling
            $orders = $blingService->getAllOrders();
            
            if (empty($orders)) {
                Log::info('BlingSyncJob: No orders found to sync');
                return;
            }

            Log::info('BlingSyncJob: Found orders to process', [
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
                    Log::error('BlingSyncJob: Error processing order', [
                        'bling_id' => $blingOrder['id'] ?? 'unknown',
                        'numero' => $blingOrder['numero'] ?? 'unknown',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $duration = now()->diffInSeconds($startTime);

            Log::info('BlingSyncJob: Synchronization completed', [
                'processed' => $processed,
                'created' => $created,
                'updated' => $updated,
                'skipped' => $skipped,
                'errors' => $errors,
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
