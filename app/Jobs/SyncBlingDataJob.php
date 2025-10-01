<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Invoice;
use App\Models\SyncLog;
use App\Services\BlingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class SyncBlingDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public int $tries = 3;
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderId)
    {
        $this->orderId = $orderId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $syncLog = null;
        
        try {
        $order = Order::find($this->orderId);
        
        if (!$order) {
                Log::warning('Order not found for Bling sync', ['order_id' => $this->orderId]);
            return;
        }

            Log::info('Starting Bling NFE sync for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

            // Cria log de sincronização de NFE
            $syncLog = SyncLog::create([
                'order_id' => $order->id,
                'sync_type' => SyncLog::TYPE_INVOICE_SYNC,
                'status' => SyncLog::STATUS_PROCESSING,
                'started_at' => now(),
                'external_id' => $order->order_number,
            ]);

            // Verifica se já tem NFE autorizada (status 6) E rastreio
            // Se tiver NFE mas for status 5, reprocessa para verificar se foi autorizada
            $invoice = $order->invoices()->first();
            $shouldReprocess = $invoice && $invoice->status === 5;
            
            // Pula apenas se já tem NFE autorizada (status 6) COM rastreio
            if ($order->nfe_number && $order->tracking_code && !$shouldReprocess) {
                $message = 'Order already has authorized NFE with tracking, skipping sync';
                Log::debug($message, [
                    'order_id' => $order->id,
                    'nfe_number' => $order->nfe_number,
                    'tracking_code' => $order->tracking_code
                ]);
                $syncLog->markAsSkipped($message);
                return;
            }
            
            if ($shouldReprocess) {
                Log::info('Reprocessing invoice to check if authorized', [
                    'order_id' => $order->id,
                    'nfe_number' => $order->nfe_number,
                    'current_status' => $invoice->status
                ]);
            }

            $blingService = app(BlingService::class);

            // Busca nota fiscal no Bling pelo número do pedido
            $invoiceBasic = $blingService->fetchInvoiceByOrderNumber($order->order_number);

            if (!$invoiceBasic) {
                $message = 'No invoice found in Bling for this order yet';
                Log::debug($message, ['order_number' => $order->order_number]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Valida se a nota é válida
            if (!$blingService->isValidInvoice($invoiceBasic)) {
                $message = 'Invalid invoice found in Bling';
                Log::warning($message, [
                    'order_number' => $order->order_number,
                    'invoice_data' => $invoiceBasic
                ]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Busca dados COMPLETOS da invoice (com transporte) usando o ID
            $invoiceFull = $blingService->fetchInvoiceById($invoiceBasic['id']);
            
            if (!$invoiceFull) {
                Log::warning('Could not fetch full invoice details', [
                    'order_number' => $order->order_number,
                    'invoice_id' => $invoiceBasic['id']
                ]);
                // Usa dados básicos se não conseguir buscar completos
                $invoiceFull = $invoiceBasic;
            }

            // Extrai dados da nota fiscal (com transporte se disponível)
            $invoiceData = $blingService->extractInvoiceData($invoiceFull);

            // Atualiza o pedido com dados da NFE
            $this->updateOrderWithInvoiceData($order, $invoiceData, $blingService);

            // Cria/atualiza registro de invoice
            $this->createOrUpdateInvoice($order, $invoiceData, $blingService);

            // Marca log como sucesso
            $syncLog->markAsSuccess('Nota fiscal sincronizada com sucesso', [
                'invoice_number' => $invoiceData['invoice_number'],
                'has_danfe' => $invoiceData['status'] === 6,
                'has_transport_info' => !empty($invoiceData['transport']),
            ]);

            Log::info('Bling NFE sync completed successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'invoice_number' => $invoiceData['invoice_number'],
            ]);

            // 🔥 ENCADEIA: Se tem DANFE (status 6) e transporte, dispara job de rastreio
            // Status 6 = NFE autorizada com DANFE, pronta para transporte
            if ($invoiceData['status'] === 6 && !empty($invoiceData['transport'])) {
                Log::info('Invoice has DANFE and transport info, dispatching tracking job', [
                    'order_id' => $order->id,
                ]);
                
                FetchTrackingDataJob::dispatch($order->id)
                    ->delay(now()->addSeconds(5)); // Aguarda 5 segundos antes de buscar rastreio
            } else {
                Log::debug('Invoice not ready for tracking yet', [
                    'order_id' => $order->id,
                    'invoice_status' => $invoiceData['status'],
                    'has_transport' => !empty($invoiceData['transport']),
                ]);
            }

        } catch (Exception $e) {
            Log::error('Bling NFE sync failed', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage()
            ]);

            if ($syncLog) {
                $syncLog->markAsFailed($e->getMessage(), $e);
            }

            // Não lança exceção para não bloquear a fila, apenas registra o erro
            // O retry será feito pelo schedule se necessário
        }
    }

    /**
     * Atualiza o pedido com dados da nota fiscal
     */
    private function updateOrderWithInvoiceData(Order $order, array $invoiceData, BlingService $blingService): void
    {
        $pdfUrl = $invoiceData['link_pdf'] ?? $blingService->getInvoicePdfUrl($invoiceData['bling_id']);

        $updateData = [
            'nfe_number' => $invoiceData['invoice_number'],
            'nfe_pdf_url' => $pdfUrl,
            'nfe_access_key' => $invoiceData['access_key'],
            'nfe_issue_date' => $invoiceData['issue_date'],
            'nfe_value' => $invoiceData['valor_nota'],
        ];

        // Se tem informações de transporte, já atualiza também
        if (!empty($invoiceData['transport'])) {
            $transport = $invoiceData['transport'];
            
            if (!empty($transport['logistics_provider'])) {
                $updateData['logistics_provider'] = $transport['logistics_provider'];
            }
            
            if (!empty($transport['tracking_code'])) {
                $updateData['tracking_code'] = $transport['tracking_code'];
            }
        }

        $order->update($updateData);

        Log::debug('Order updated with invoice data', [
            'order_id' => $order->id,
            'nfe_number' => $invoiceData['invoice_number'],
            'has_transport_info' => !empty($invoiceData['transport']),
        ]);
    }

    /**
     * Cria ou atualiza registro de invoice
     */
    private function createOrUpdateInvoice(Order $order, array $invoiceData, BlingService $blingService): void
    {
        $pdfUrl = $invoiceData['link_pdf'] ?? $blingService->getInvoicePdfUrl($invoiceData['bling_id']);

        $invoice = Invoice::updateOrCreate(
            [
                'order_id' => $order->id,
                'invoice_number' => $invoiceData['invoice_number']
            ],
            [
                'invoice_number' => $invoiceData['invoice_number'],
                'invoice_pdf_url' => $pdfUrl,
                'danfe_url' => $invoiceData['link_danfe'],
                'access_key' => $invoiceData['access_key'],
                'issued_at' => $invoiceData['issue_date'],
                'operation_date' => $invoiceData['operation_date'],
                'status' => $invoiceData['status'],
                'invoice_type' => $invoiceData['type'],
                'invoice_value' => $invoiceData['valor_nota'],
                'freight_value' => $invoiceData['valor_frete'],
                'series' => $invoiceData['serie'],
                'raw_data' => json_encode($invoiceData['raw_data'])
            ]
        );

        Log::debug('Invoice record created/updated', [
            'invoice_id' => $invoice->id,
            'order_id' => $order->id,
            'invoice_number' => $invoiceData['invoice_number'],
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncBlingDataJob failed permanently', [
            'error' => $exception->getMessage(),
            'order_id' => $this->orderId,
            'attempts' => $this->attempts()
        ]);
    }
}
