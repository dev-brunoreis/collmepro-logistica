<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Services\BlingService;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\OrderHistoryService;
use App\Services\SystemMappingService;
use App\Models\OrderSummary;
use App\Models\Invoice;
use App\Traits\BlingSyncLogger;
use App\Traits\BroadcastsOrderUpdates;
use App\Events\BlingSyncStatusUpdated;
use Carbon\Carbon;

class BlingSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BlingSyncLogger, BroadcastsOrderUpdates;

    public int $timeout = 3600; // 1 hour
    public int $tries = 5;
    public array $backoff = [60, 300, 600, 1800]; // 1min, 5min, 10min, 30min

    private bool $forceUpdate;
    private ?string $accountType;
    private array $criticalChangesDetected = [];
    private bool $incrementalSync;

    /**
     * Create a new job instance.
     * 
     * @param bool $forceUpdate Force update existing orders
     * @param string|null $accountType Account type to sync ('pro', 'atacadista', or null for all)
     * @param bool $incrementalSync Use incremental sync (last 48h) instead of full sync
     */
    public function __construct(bool $forceUpdate = false, ?string $accountType = null, bool $incrementalSync = true)
    {
        $this->forceUpdate = $forceUpdate;
        $this->accountType = $accountType;
        $this->incrementalSync = $incrementalSync;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();
        
        // Update sync status: starting
        $this->updateSyncStatus(true, $startTime);
        
        $this->logBlingInfo('Starting Bling synchronization', [
            'force_update' => $this->forceUpdate,
            'account_type' => $this->accountType ?? 'all',
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
                $this->logBlingInfo("Syncing account: {$accountType}");
                
                try {
                    $result = $this->syncAccount($blingService, $accountType);
                    
                    $totalProcessed += $result['processed'];
                    $totalCreated += $result['created'];
                    $totalUpdated += $result['updated'];
                    $totalSkipped += $result['skipped'];
                    $totalErrors += $result['errors'];
                    
                } catch (\Exception $e) {
                    $totalErrors++;
                    $this->logBlingError("Error syncing account {$accountType}", [
                        'error' => $e->getMessage(),
                        'file' => $e->getFile() . ':' . $e->getLine(),
                    ]);
                }
            }

            $duration = now()->diffInSeconds($startTime);

            $this->logSyncSummary('all_accounts', [
                'accounts_synced' => count($services),
                'processed' => $totalProcessed,
                'created' => $totalCreated,
                'updated' => $totalUpdated,
                'skipped' => $totalSkipped,
                'errors' => $totalErrors,
                'duration_seconds' => $duration,
            ]);

            // Update sync status: completed
            $this->updateSyncStatus(false, now());

        } catch (\Exception $e) {
            $this->logBlingError('Fatal error during synchronization', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
                'duration_seconds' => now()->diffInSeconds($startTime)
            ]);
            
            // Update sync status: failed
            $this->updateSyncStatus(false, now());
            
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
        // Get orders from Bling with incremental sync option
        $orders = $blingService->getAllOrders([], $this->incrementalSync);
        
        if (empty($orders)) {
            $this->logBlingInfo("No orders found: {$accountType}");
            return [
                'processed' => 0,
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        $this->logBlingInfo("Found orders: {$accountType}", ['count' => count($orders)]);

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
                $this->logBlingError("Error processing order", [
                    'account' => $accountType,
                    'bling_id' => $blingOrder['id'] ?? 'unknown',
                    'numero' => $blingOrder['numero'] ?? 'unknown',
                    'error' => $e->getMessage(),
                    'file' => $e->getFile() . ':' . $e->getLine(),
                ]);
            }
        }

        $this->logSyncSummary($accountType, [
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
     * Process a single Bling order with multi-level optimization
     */
    private function processOrder(BlingService $blingService, array $blingOrder): array
    {
        $blingId = $blingOrder['id'];
        
        // Check if order already exists
        $existingOrder = OrderSummary::where('bling_id', $blingId)->first();
        
        // NEW ORDER: Fast path for new orders
        if (!$existingOrder) {
            $this->logBlingDebug("New order: #{$blingOrder['numero']}", ['bling_id' => $blingId]);
            // Continue to full processing (no optimizations for new orders)
        } elseif (!$this->forceUpdate) {
            // EXISTING ORDER: Multi-level optimization
            
            // LEVEL 1: Skip very old orders in incremental sync
            if ($this->incrementalSync) {
                $orderDate = Carbon::parse($blingOrder['data']);
                // if ($orderDate->lt(now()->subDays(30))) {
                //     $this->logBlingDebug("Order too old, skipping: #{$blingOrder['numero']}", ['bling_id' => $blingId]);
                //     return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'too_old'];
                // }
            }
            
            // LEVEL 2: Quick cache check with lightweight hash
            $lightweightHash = $blingService->generateLightweightHash($blingOrder);
            $cachedHash = $blingService->getCachedOrderHash($blingId);
            
            if ($cachedHash === $lightweightHash) {
                return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'cache_hit'];
            }
            
            // LEVEL 3: Database hash check (if cache missed but DB has it)
            if ($existingOrder->bling_data_hash === $lightweightHash) {
                // Update cache for next time
                $blingService->setCachedOrderHash($blingId, $lightweightHash);
                return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'db_hash_match'];
            }
            
            $this->logBlingDebug("Hash changed: #{$blingOrder['numero']}", ['bling_id' => $blingId]);
            
            // Check if order was updated since last sync
            $blingUpdatedAt = Carbon::parse($blingOrder['dataAlteracao'] ?? $blingOrder['data']);
            $shouldSkip = $existingOrder->updated_at && 
                          $blingUpdatedAt->lte($existingOrder->updated_at);
            
            // NOVA LÓGICA: Sempre verificar mudanças críticas mesmo se não houver mudança de data
            if ($shouldSkip) {
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
                    // Mark critical changes detected (especially invoice changes)
                    if ($currentHasInvoice !== $existingHasInvoice) {
                        $this->criticalChangesDetected[$blingId] = true;
                        $this->logBlingInfo("Invoice change detected: #{$blingOrder['numero']}", [
                            'bling_id' => $blingId,
                            'had_invoice' => $existingHasInvoice,
                            'has_invoice' => $currentHasInvoice,
                        ]);
                    }
                    
                    // Não skip - continua para atualizar
                } else {
                    return ['created' => false, 'updated' => false, 'skipped' => true];
                }
            }
        }

        // LEVEL 4: Fetch complete order data and generate comprehensive hash
        $completeOrderData = $blingService->getCompleteOrderData($blingId);
        if (!$completeOrderData) {
            throw new \Exception("Failed to fetch complete order data for ID {$blingId}");
        }
        
        // Generate comprehensive hash including invoice and tracking
        $comprehensiveHash = $blingService->generateOrderHash($completeOrderData);
        
        // LEVEL 5: Final hash comparison with complete data
        if ($existingOrder && !$this->forceUpdate) {
            // Check if we detected critical changes earlier that require processing
            $hasCriticalChanges = false;
            if (isset($this->criticalChangesDetected[$blingId])) {
                $hasCriticalChanges = $this->criticalChangesDetected[$blingId];
            }
            
            // SPECIAL CHECK: Always process orders with invoices to ensure Kanban displays correctly
            // Even if hash is identical, we need to verify the frontend has correct data
            $hasInvoice = !empty($completeOrderData['notaFiscal']['id']);
            $needsKanbanVerification = $hasInvoice;
            
            if ($existingOrder->bling_data_hash === $comprehensiveHash && !$hasCriticalChanges && !$needsKanbanVerification) {
                // Update cache with comprehensive hash for next time
                $blingService->setCachedOrderHash($blingId, $comprehensiveHash);
                
                return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'comprehensive_hash_match'];
            }
            
            if ($hasCriticalChanges || $needsKanbanVerification) {
                $this->logBlingInfo("Updating order: #{$blingOrder['numero']}", [
                    'bling_id' => $blingId,
                    'critical_changes' => $hasCriticalChanges,
                    'has_invoice' => $needsKanbanVerification,
                ]);
            }
        }

        DB::beginTransaction();
        
        try {
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
                
                // Update hash after saving (using comprehensive hash already generated)
                $order->bling_data_hash = $comprehensiveHash;
                $order->save();
                
                // Update cache with new hash
                $blingService->setCachedOrderHash($blingId, $comprehensiveHash);
                
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
                            
                            $this->logBlingInfo("Order cancelled, reset statuses: #{$order->numero}", ['bling_id' => $blingId]);
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
                
                // Set hash for new order (using comprehensive hash already generated)
                $order->bling_data_hash = $comprehensiveHash;
                $order->save();
                
                // Cache the hash for new order
                $blingService->setCachedOrderHash($blingId, $comprehensiveHash);
                
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

            DB::commit();

            // Log apenas se houver mudanças significativas
            if ($created || !empty($changedFields)) {
                $this->logOrderAction(
                    $created ? 'created' : 'updated',
                    $blingId,
                    $blingOrder['numero'],
                    [
                        'changed_fields' => implode(',', $changedFields),
                    ]
                );
            }

            // Broadcast events to frontend (for immediate Kanban update) - non-blocking
            if ($created) {
                // For new orders, always broadcast OrderCreated
                $this->safeBroadcastOrderCreated($order);
            } else if (!empty($changedFields)) {
                // For existing orders, only broadcast if there are actual changes
                $this->safeBroadcastOrderUpdate($order, $changedFields, $changeTypes);
            }

            // EVENT-DRIVEN: Emit OrderSynced to trigger specialized jobs
            // This will queue: invoice sync, tracking extraction, and seller sync
            event(new \App\Events\OrderSynced($order, $created, $changedFields));

            return ['created' => $created, 'updated' => $updated, 'order' => $order];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Map Bling invoice data to Invoice model
     */
    private function mapBlingInvoiceToInvoice(array $blingInvoice, int $orderSummaryId): array
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
            'data_emissao' => $this->parseValidDate($blingInvoice['dataEmissao'] ?? null),
            'data_operacao' => $this->parseValidDate($blingInvoice['dataOperacao'] ?? null),
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
    private function syncInvoicesAndTrackingWithCompleteData(BlingService $blingService, OrderSummary $order, array $completeOrderData): void
    {
        // Check if order has invoice ID in the complete data
        $invoiceId = $completeOrderData['notaFiscal']['id'] ?? null;
        
        if (!$invoiceId) {
            return;
        }

        // Get complete invoice data
        $completeInvoiceData = $blingService->getCompleteInvoiceData($invoiceId);
        
        if (!$completeInvoiceData) {
            $this->logBlingWarning("Failed to fetch invoice data", [
                'order_id' => $order->id,
                'invoice_id' => $invoiceId
            ]);
            return;
        }

        // Check if invoice already exists before updateOrCreate
        $existingInvoice = \App\Models\Invoice::where('bling_id', $invoiceId)->first();
        $oldInvoiceNumber = $existingInvoice?->numero ?? null;
        
        // Create or update invoice record in invoices table
        $invoiceData = $this->mapBlingInvoiceToInvoice($completeInvoiceData, $order->id);
        $invoiceData['raw_data'] = $completeInvoiceData;
        
        $invoice = \App\Models\Invoice::updateOrCreate(
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
        
        if ($wasInvoiceCreated || $wasInvoiceUpdated) {
            app(OrderHistoryService::class)->recordInvoiceSynced($order, [
                'invoice_id' => $invoiceId,
                'numero' => $completeInvoiceData['numero'] ?? null,
                'valor_total' => $completeInvoiceData['valorNota'] ?? null,
            ]);
        }

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
                    
                    if ($wasTrackingCreated || $wasTrackingUpdated) {
                        app(OrderHistoryService::class)->recordTrackingFetched($order, [
                            'volume_id' => $volumeId,
                            'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                            'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                        ]);
                    }
                }
            }
        }
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
        $lojaId = $blingOrder['loja']['id'] ?? 1;
        
        // Map loja_id to system name using centralized service
        $source = SystemMappingService::mapLojaIdToSource($lojaId);
        
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
            'loja_id' => $lojaId,
            'source' => $source,
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
     * Capture seller information for pro and atacadista orders
     */
    private function captureSeller(OrderSummary $order): void
    {
        // Only capture seller for pro and atacadista orders
        $source = strtolower($order->source);
        if (!in_array($source, ['pro', 'atacadista'])) {
            return;
        }

        // Extract WooCommerce order ID from numero_loja
        if (empty($order->numero_loja)) {
            return;
        }

        $parts = explode('_', $order->numero_loja);
        $woocommerceOrderId = $parts[0] ?? null;

        if (!$woocommerceOrderId) {
            return;
        }

        try {
            // Fetch seller from WooCommerce
            $woocommerceService = app(\App\Services\WooCommerceService::class);
            $sellerData = $woocommerceService->getOrderSeller($woocommerceOrderId, $source);

            if (!$sellerData || empty($sellerData['id'])) {
                return;
            }

            // Find or create seller
            $seller = \App\Models\Seller::firstOrCreate(
                ['woocommerce_id' => $sellerData['id']],
                [
                    'name' => $sellerData['name'] ?? 'Unknown',
                    'email' => $sellerData['email'],
                ]
            );

            // Sync the relationship (will not duplicate if already exists)
            $order->seller()->syncWithoutDetaching([$seller->id]);

            $this->logBlingDebug("Seller captured: {$seller->name}", [
                'order_id' => $order->id,
                'seller_id' => $seller->id,
                'woocommerce_order_id' => $woocommerceOrderId,
            ]);

        } catch (\Exception $e) {
            // Log warning but continue processing
            $this->logBlingWarning('Failed to capture seller', [
                'order_id' => $order->id,
                'woocommerce_order_id' => $woocommerceOrderId,
                'source' => $source,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        $this->logBlingError('Job failed permanently', [
            'error' => $exception->getMessage(),
            'file' => $exception->getFile() . ':' . $exception->getLine(),
            'force_update' => $this->forceUpdate
        ]);
        
        // Update sync status: failed
        $this->updateSyncStatus(false, now());
    }

    /**
     * Update sync status in cache and broadcast event
     */
    private function updateSyncStatus(bool $isRunning, Carbon $lastSyncAt, Carbon $nextSyncAt = null): void
    {
        $statusData = [
            'is_running' => $isRunning,
            'last_sync_at' => $isRunning ? null : $lastSyncAt->toISOString(), // Only set when not running
            'account_type' => $this->accountType ?? 'all',
            'updated_at' => now()->toISOString(),
        ];

        // Store in cache for 2 hours
        Cache::put('bling_sync_status', $statusData, now()->addHours(2));

        // Broadcast event
        event(new BlingSyncStatusUpdated(
            $isRunning,
            $isRunning ? null : $lastSyncAt->toISOString(),
            null, // Frontend will calculate next sync time
            $this->accountType ?? 'all'
        ));

        $this->logBlingInfo('Sync status updated', [
            'is_running' => $isRunning,
            'account_type' => $this->accountType ?? 'all',
        ]);
    }
}
