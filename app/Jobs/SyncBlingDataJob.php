<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Invoice;
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

    public ?int $orderId;
    public ?string $orderNumber;
    public int $tries = 3;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $orderId = null, ?string $orderNumber = null)
    {
        $this->orderId = $orderId;
        $this->orderNumber = $orderNumber;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting Bling data sync', [
                'order_id' => $this->orderId,
                'order_number' => $this->orderNumber
            ]);

            $blingService = app(BlingService::class);

            if ($this->orderId) {
                // Sincronizar pedido específico
                $this->syncSpecificOrder($blingService);
            } elseif ($this->orderNumber) {
                // Sincronizar por número do pedido
                $this->syncByOrderNumber($blingService);
            } else {
                // Sincronizar todos os pedidos sem NFE
                $this->syncAllOrdersWithoutInvoice($blingService);
            }

            Log::info('Bling data sync completed successfully');

        } catch (Exception $e) {
            Log::error('Bling data sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'order_id' => $this->orderId,
                'order_number' => $this->orderNumber
            ]);

            throw $e;
        }
    }

    /**
     * Sincroniza um pedido específico
     */
    private function syncSpecificOrder(BlingService $blingService): void
    {
        $order = Order::find($this->orderId);
        
        if (!$order) {
            Log::warning('Order not found for sync', ['order_id' => $this->orderId]);
            return;
        }

        $this->syncOrderWithBling($order, $blingService);
    }

    /**
     * Sincroniza por número do pedido
     */
    private function syncByOrderNumber(BlingService $blingService): void
    {
        $order = Order::where('order_number', $this->orderNumber)->first();
        
        if (!$order) {
            Log::warning('Order not found for sync', ['order_number' => $this->orderNumber]);
            return;
        }

        $this->syncOrderWithBling($order, $blingService);
    }

    /**
     * Sincroniza todos os pedidos sem NFE
     */
    private function syncAllOrdersWithoutInvoice(BlingService $blingService): void
    {
        $batchSize = config('bling.sync.batch_size', 50);
        
        $orders = Order::whereNull('nfe_number')
            ->whereNull('nfe_pdf_url')
            ->limit($batchSize)
            ->get();

        if ($orders->isEmpty()) {
            Log::info('No orders without invoice found for sync');
            return;
        }

        Log::info('Syncing orders without invoice', [
            'count' => $orders->count()
        ]);

        $syncedCount = 0;
        $skippedCount = 0;

        foreach ($orders as $order) {
            if ($this->syncOrderWithBling($order, $blingService)) {
                $syncedCount++;
            } else {
                $skippedCount++;
            }
        }

        Log::info('Bulk sync completed', [
            'synced' => $syncedCount,
            'skipped' => $skippedCount,
            'total' => $orders->count()
        ]);
    }

    /**
     * Sincroniza um pedido específico com o Bling
     */
    private function syncOrderWithBling(Order $order, BlingService $blingService): bool
    {
        try {
            Log::debug('Syncing order with Bling', [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

            // Busca nota fiscal no Bling pelo número do pedido
            $invoice = $blingService->fetchInvoiceByOrderNumber($order->order_number);

            if (!$invoice) {
                Log::debug('No invoice found in Bling for order', [
                    'order_number' => $order->order_number
                ]);
                return false;
            }

            // Valida se a nota é válida
            if (!$blingService->isValidInvoice($invoice)) {
                Log::warning('Invalid invoice found in Bling', [
                    'order_number' => $order->order_number,
                    'invoice_data' => $invoice
                ]);
                return false;
            }

            // Extrai dados da nota fiscal
            $invoiceData = $blingService->extractInvoiceData($invoice);

            // Atualiza o pedido com dados da NFE
            $this->updateOrderWithInvoiceData($order, $invoiceData, $blingService);

            // Cria/atualiza registro de invoice
            $this->createOrUpdateInvoice($order, $invoiceData, $blingService);

            Log::info('Order synced successfully with Bling', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'invoice_number' => $invoiceData['invoice_number']
            ]);

            return true;

        } catch (Exception $e) {
            Log::error('Failed to sync order with Bling', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Atualiza o pedido com dados da nota fiscal
     */
    private function updateOrderWithInvoiceData(Order $order, array $invoiceData, BlingService $blingService): void
    {
        // Usa link_pdf diretamente dos dados extraídos, ou busca via API se não disponível
        $pdfUrl = $invoiceData['link_pdf'] ?? $blingService->getInvoicePdfUrl($invoiceData['bling_id']);

        $updateData = [
            'nfe_number' => $invoiceData['invoice_number'],
            'nfe_pdf_url' => $pdfUrl,
            'nfe_access_key' => $invoiceData['access_key'],
            'nfe_issue_date' => $invoiceData['issue_date'],
            'nfe_value' => $invoiceData['valor_nota'],
        ];

        $order->update($updateData);

        Log::debug('Order updated with invoice data', [
            'order_id' => $order->id,
            'nfe_number' => $invoiceData['invoice_number'],
            'pdf_url' => $pdfUrl ? 'available' : 'not_available',
            'access_key' => $invoiceData['access_key'] ? 'available' : 'not_available',
            'value' => $invoiceData['valor_nota'] ?? 'not_available'
        ]);
    }

    /**
     * Cria ou atualiza registro de invoice
     */
    private function createOrUpdateInvoice(Order $order, array $invoiceData, BlingService $blingService): void
    {
        // Usa link_pdf diretamente dos dados extraídos, ou busca via API se não disponível
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
            'pdf_url' => $pdfUrl ? 'available' : 'not_available',
            'danfe_url' => $invoiceData['link_danfe'] ? 'available' : 'not_available',
            'value' => $invoiceData['valor_nota'] ?? 'not_available'
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
            'order_number' => $this->orderNumber,
            'attempts' => $this->attempts()
        ]);
    }
}
