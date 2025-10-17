<?php

namespace App\Jobs;

use App\Events\InvoiceSynced;
use App\Models\Invoice;
use App\Models\OrderHistory;
use App\Models\OrderSummary;
use App\Services\BlingService;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\OrderHistoryService;
use App\Traits\InvoiceLogger;
use App\Traits\BroadcastsOrderUpdates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, InvoiceLogger, BroadcastsOrderUpdates;

    public int $timeout = 60;
    public int $tries = 3;
    public array $backoff = [60, 300]; // 1min, 5min

    private int $orderSummaryId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderSummaryId)
    {
        $this->orderSummaryId = $orderSummaryId;
        $this->onQueue('invoices');
    }

    /**
     * Get unique ID for idempotency.
     */
    public function uniqueId(): string
    {
        return "sync-invoice-{$this->orderSummaryId}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = "invoice_synced_{$this->orderSummaryId}";
        $lockKey = "invoice_sync_lock_{$this->orderSummaryId}";

        // Idempotency check: Skip if processed recently (within 5 minutes)
        if (Cache::has($cacheKey)) {
            $this->logInvoiceInfo('Invoice sync skipped (already processed recently)', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        // Additional idempotency check: Use atomic lock to prevent concurrent processing
        if (!Cache::lock($lockKey, 60)->get()) {
            $this->logInvoiceInfo('Invoice sync skipped (already being processed)', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        $order = OrderSummary::find($this->orderSummaryId);

        if (!$order) {
            $this->logInvoiceError('Order not found for invoice sync', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        $this->logInvoiceInfo('Starting invoice sync', [
            'order_id' => $order->id,
            'order_number' => $order->numero,
            'bling_id' => $order->bling_id,
        ]);

        // Record worker attempt in history
        app(OrderHistoryService::class)->recordWorkerAttempt(
            $order,
            'Andromeda Worker (Invoice)',
            'invoice',
            $this->attempts(),
            [
                'order_id' => $order->id,
                'bling_id' => $order->bling_id,
            ]
        );

        try {
            // Get the appropriate Bling service based on order source
            $blingService = $this->getBlingService($order->source);

            // Get complete order data to check for invoice
            $completeOrderData = $blingService->getCompleteOrderData($order->bling_id);

            if (!$completeOrderData) {
                $this->logInvoiceWarning('Failed to fetch complete order data', [
                    'order_id' => $order->id,
                    'bling_id' => $order->bling_id,
                ]);
                return;
            }

            // Check if order has invoice
            $invoiceId = $completeOrderData['notaFiscal']['id'] ?? null;

            if (!$invoiceId) {
                $this->logInvoiceDebug('Order has no invoice yet', [
                    'order_id' => $order->id,
                ]);
                // Mark as processed to avoid constant re-checking
                Cache::put($cacheKey, now(), 300); // 5 minutes
                return;
            }

            // Fetch complete invoice data
            $completeInvoiceData = $blingService->getCompleteInvoiceData($invoiceId);

            if (!$completeInvoiceData) {
                $this->logInvoiceWarning('Failed to fetch invoice data', [
                    'order_id' => $order->id,
                    'invoice_id' => $invoiceId,
                ]);
                return;
            }

            // Check if invoice already exists
            $existingInvoice = Invoice::where('bling_id', $invoiceId)->first();
            $wasCreated = !$existingInvoice;
            $oldInvoiceNumber = $existingInvoice?->numero ?? null;

            // Map and save invoice data
            $invoiceData = $this->mapBlingInvoiceToInvoice($completeInvoiceData, $order->id);
            $invoiceData['raw_data'] = $completeInvoiceData;

            DB::transaction(function () use ($invoiceId, $invoiceData, $order, $wasCreated, $oldInvoiceNumber, &$invoice) {
                $invoice = Invoice::updateOrCreate(
                    ['bling_id' => $invoiceId],
                    $invoiceData
                );

                // Update order timestamp
                $order->update(['external_updated_at' => now()]);

                // Force reload invoice relationship
                $order->unsetRelation('invoice');
                $order->load('invoice');

                // Record in history if created or updated
                $wasUpdated = !$wasCreated && ($oldInvoiceNumber !== $invoice->numero);

                if ($wasCreated || $wasUpdated) {
                    app(OrderHistoryService::class)->recordInvoiceSynced($order, [
                        'invoice_id' => $invoiceId,
                        'numero' => $invoiceData['numero'] ?? null,
                        'valor_total' => $invoiceData['valor_nota'] ?? null,
                    ]);
                }
            });

            // Broadcast OrderUpdated to frontend (non-blocking)
            $this->safeBroadcastOrderUpdate($order, ['invoice_number'], ['invoice_added']);

            // Emit internal event for tracking extraction
            event(new InvoiceSynced($invoice, $wasCreated));

            // Mark as processed (1 hour)
            Cache::put($cacheKey, now(), 3600);

            $this->logInvoiceInfo('Invoice sync completed', [
                'order_id' => $order->id,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice->numero,
                'was_created' => $wasCreated,
            ]);

        } catch (\Exception $e) {
            $this->logInvoiceError('Invoice sync failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            throw $e;
        } finally {
            // Always release the lock
            Cache::lock($lockKey)->release();
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        DB::table('failed_sync_jobs')->insert([
            'order_summary_id' => $this->orderSummaryId,
            'job_type' => 'invoice_sync',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'payload' => json_encode([
                'order_summary_id' => $this->orderSummaryId,
            ]),
            'failed_at' => now(),
            'resolved' => false,
        ]);

        $this->logInvoiceError('Invoice sync job failed permanently', [
            'order_id' => $this->orderSummaryId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);

        // Record failure in history
        try {
            $order = OrderSummary::find($this->orderSummaryId);
            if ($order) {
                app(OrderHistoryService::class)->recordWorkerFailure(
                    $order,
                    OrderHistory::EVENT_INVOICE_SYNC_FAILED,
                    'Andromeda Worker (Invoice)',
                    'Falha ao sincronizar NFE após múltiplas tentativas',
                    $exception->getMessage(),
                    [
                        'attempts' => $this->attempts(),
                        'job_type' => 'invoice_sync',
                    ]
                );
            }
        } catch (\Exception $e) {
            // Ignore history recording errors in failed()
        }
    }

    /**
     * Get appropriate Bling service based on source.
     */
    private function getBlingService(string $source): BlingService
    {
        return match ($source) {
            'Collmepro Atacadista' => app(CollmeAtacadistaBlingService::class),
            default => app(CollmeProBlingService::class),
        };
    }

    /**
     * Map Bling invoice data to Invoice model.
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
     * Parse valid date or return null.
     */
    private function parseValidDate(?string $date): ?Carbon
    {
        if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

}

