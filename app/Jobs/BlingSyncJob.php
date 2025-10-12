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
use App\Services\SystemMappingService;
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
        // Get orders from Bling with incremental sync option
        $orders = $blingService->getAllOrders([], $this->incrementalSync);
        
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
     * Process a single Bling order with multi-level optimization
     */
    private function processOrder(BlingService $blingService, array $blingOrder): array
    {
        $blingId = $blingOrder['id'];
        
        // Check if order already exists
        $existingOrder = OrderSummary::where('bling_id', $blingId)->first();
        
        // NEW ORDER: Fast path for new orders
        if (!$existingOrder) {
            Log::debug('BlingSyncJob: New order detected, will create', [
                'bling_id' => $blingId,
                'numero' => $blingOrder['numero']
            ]);
            // Continue to full processing (no optimizations for new orders)
        } elseif (!$this->forceUpdate) {
            // EXISTING ORDER: Multi-level optimization
            
            // LEVEL 1: Skip very old orders in incremental sync
            if ($this->incrementalSync) {
                $orderDate = Carbon::parse($blingOrder['data']);
                if ($orderDate->lt(now()->subDays(30))) {
                    Log::debug('BlingSyncJob: Order too old for incremental sync, skipping', [
                        'bling_id' => $blingId,
                        'numero' => $blingOrder['numero'],
                        'order_date' => $orderDate->toDateString(),
                        'cutoff_date' => now()->subDays(30)->toDateString()
                    ]);
                    return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'too_old'];
                }
            }
            
            // LEVEL 2: Quick cache check with lightweight hash
            $lightweightHash = $blingService->generateLightweightHash($blingOrder);
            $cachedHash = $blingService->getCachedOrderHash($blingId);
            
            if ($cachedHash === $lightweightHash) {
                Log::debug('BlingSyncJob: Cache hit - hash identical, skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'hash' => $lightweightHash
                ]);
                return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'cache_hit'];
            }
            
            // LEVEL 3: Database hash check (if cache missed but DB has it)
            if ($existingOrder->bling_data_hash === $lightweightHash) {
                Log::debug('BlingSyncJob: DB hash matches lightweight hash, updating cache and skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'hash' => $lightweightHash
                ]);
                // Update cache for next time
                $blingService->setCachedOrderHash($blingId, $lightweightHash);
                return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'db_hash_match'];
            }
            
            Log::info('BlingSyncJob: Hash difference detected, need to fetch complete data', [
                'bling_id' => $blingId,
                'numero' => $blingOrder['numero'],
                'cached_hash' => $cachedHash,
                'lightweight_hash' => $lightweightHash,
                'db_hash' => $existingOrder->bling_data_hash
            ]);
            
            // Check if order was updated since last sync
            $blingUpdatedAt = Carbon::parse($blingOrder['dataAlteracao'] ?? $blingOrder['data']);
            $shouldSkip = $existingOrder->updated_at && 
                          $blingUpdatedAt->lte($existingOrder->updated_at);
            
            Log::info('BlingSyncJob: Checking skip condition', [
                'bling_id' => $blingId,
                'numero' => $blingOrder['numero'],
                'shouldSkip' => $shouldSkip,
                'blingUpdatedAt' => $blingUpdatedAt->toDateTimeString(),
                'existingUpdatedAt' => $existingOrder->updated_at->toDateTimeString()
            ]);
            
            // NOVA LÓGICA: Sempre verificar mudanças críticas mesmo se não houver mudança de data
            if ($shouldSkip) {
                Log::info('BlingSyncJob: Checking for changes in order that should be skipped', [
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
                    Log::info('BlingSyncJob: Order has changes, forcing sync', [
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
                        $this->criticalChangesDetected[$blingId] = true;
                        Log::info('BlingSyncJob: Critical invoice change detected', [
                            'bling_id' => $blingId,
                            'numero' => $blingOrder['numero'],
                            'has_invoice' => [$existingHasInvoice, $currentHasInvoice]
                        ]);
                    }
                    
                    // Não skip - continua para atualizar
                } else {
                    Log::debug('BlingSyncJob: Order not updated since last sync, skipping', [
                        'bling_id' => $blingId,
                        'numero' => $blingOrder['numero']
                    ]);
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
                Log::debug('BlingSyncJob: Comprehensive hash identical, updating cache and skipping', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'hash' => $comprehensiveHash
                ]);
                
                // Update cache with comprehensive hash for next time
                $blingService->setCachedOrderHash($blingId, $comprehensiveHash);
                
                return ['created' => false, 'updated' => false, 'skipped' => true, 'skip_reason' => 'comprehensive_hash_match'];
            }
            
            if ($hasCriticalChanges) {
                Log::info('BlingSyncJob: Critical changes detected, forcing sync despite identical hash', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'hash' => $comprehensiveHash
                ]);
            }
            
            if ($needsKanbanVerification) {
                Log::info('BlingSyncJob: Order has invoice, forcing sync to verify Kanban display', [
                    'bling_id' => $blingId,
                    'numero' => $blingOrder['numero'],
                    'hash' => $comprehensiveHash
                ]);
            }
            
            Log::info('BlingSyncJob: Comprehensive hash changed, processing order update', [
                'bling_id' => $blingId,
                'numero' => $blingOrder['numero'],
                'old_hash' => $existingOrder->bling_data_hash,
                'new_hash' => $comprehensiveHash
            ]);
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

            // Capture invoice and tracking before sync for change detection
            $oldInvoiceNumber = $order->invoice?->numero ?? null;
            $oldTrackingCode = $order->tracking_code;
            
            // Sync invoices (NFE) and tracking with complete data
            $this->syncInvoicesAndTrackingWithCompleteData($blingService, $order, $completeOrderData);
            
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
                Log::info('BlingSyncJob: Forcing invoice broadcast for new order with existing invoice', [
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

            Log::debug('BlingSyncJob: Order processed successfully', [
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

            // Get system user for broadcasting events
            $systemUser = \App\Models\User::whereIn('role', ['admin', 'manager'])
                ->orderByRaw("FIELD(role, 'admin', 'manager')")
                ->first();
            
            // Fallback to any user if no admin/manager exists
            if (!$systemUser) {
                $systemUser = \App\Models\User::first();
            }

            // Broadcast events only if there are meaningful changes or it's a new order
            // SPECIAL CASE: Broadcast if order has invoice AND it's a new order (first sync)
            $hasInvoice = !empty($newInvoiceNumber);
            $shouldBroadcast = !empty($changedFields) || $created || ($hasInvoice && $created);
            
            if ($systemUser && $shouldBroadcast) {
                if ($created) {
                    // For new orders, always broadcast OrderCreated
                    event(new \App\Events\OrderCreated($order, $systemUser));
                    Log::debug('BlingSyncJob: OrderCreated event dispatched', [
                        'order_id' => $order->id,
                        'user_id' => $systemUser->id,
                        'has_invoice' => !empty($newInvoiceNumber),
                        'has_tracking' => !empty($newTrackingCode)
                    ]);
                } else {
                    // For existing orders, only broadcast if there are actual changes
                    // If no changes detected but has invoice, force invoice_added change type
                    $finalChangeTypes = $changeTypes;
                    $finalChangedFields = $changedFields;
                    
                    if (empty($changedFields) && $hasInvoice && $created) {
                        $finalChangedFields = ['invoice_number'];
                        $finalChangeTypes = ['invoice_added'];
                        Log::info('BlingSyncJob: Forcing invoice broadcast for new order with existing invoice', [
                            'bling_id' => $blingId,
                            'numero' => $blingOrder['numero'],
                            'invoice_number' => $newInvoiceNumber
                        ]);
                    }
                    
                    event(new \App\Events\OrderUpdated($order, $systemUser, $finalChangedFields, $finalChangeTypes));
                    Log::debug('BlingSyncJob: OrderUpdated event dispatched', [
                        'order_id' => $order->id,
                        'user_id' => $systemUser->id,
                        'changed_fields' => $finalChangedFields,
                        'change_types' => $finalChangeTypes,
                        'has_invoice' => !empty($newInvoiceNumber),
                        'has_tracking' => !empty($newTrackingCode),
                        'forced_invoice_broadcast' => empty($changedFields) && $hasInvoice && $created
                    ]);
                }
            }
            
            if (!$systemUser) {
                Log::warning('BlingSyncJob: Cannot dispatch order events: no users found in system', ['order_id' => $order->id]);
            }

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

        // Record invoice sync in history
        app(OrderHistoryService::class)->recordInvoiceSynced($order, [
            'invoice_id' => $invoiceId,
            'numero' => $completeInvoiceData['numero'] ?? null,
            'valor_total' => $completeInvoiceData['valorNota'] ?? null,
        ]);
        
        Log::debug('BlingSyncJob: Invoice created/updated', [
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

                    // Create or update tracking record in tracking table
                    \App\Models\Tracking::updateOrCreate(
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

                    // Record tracking fetch in history
                    app(OrderHistoryService::class)->recordTrackingFetched($order, [
                        'volume_id' => $volumeId,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                    ]);

                    Log::debug('BlingSyncJob: Complete tracking data updated for order', [
                        'order_id' => $order->id,
                        'tracking_code' => $completeTrackingData['rastreamento']['codigo'] ?? null,
                        'volume_id' => $volumeId,
                        'service_name' => $completeTrackingData['servico']['nome'] ?? null,
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
