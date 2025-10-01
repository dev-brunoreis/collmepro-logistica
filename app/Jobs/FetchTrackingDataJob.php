<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SyncLog;
use App\Services\BlingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class FetchTrackingDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $orderId;
    public int $tries = 3;
    public int $timeout = 60;

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
                Log::warning('Order not found for tracking fetch', ['order_id' => $this->orderId]);
                return;
            }

            Log::info('Starting tracking fetch for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number
            ]);

            // Cria log de sincronização de rastreio
            $syncLog = SyncLog::create([
                'order_id' => $order->id,
                'sync_type' => SyncLog::TYPE_TRACKING_FETCH,
                'status' => SyncLog::STATUS_PROCESSING,
                'started_at' => now(),
                'external_id' => $order->order_number,
            ]);

            // Verifica se tem NFE
            if (!$order->nfe_number) {
                $message = 'Order does not have NFE yet, cannot fetch tracking';
                Log::debug($message, ['order_id' => $order->id]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Verifica se já tem tracking
            if ($order->tracking_code) {
                $message = 'Order already has tracking code, skipping';
                Log::debug($message, [
                    'order_id' => $order->id,
                    'tracking_code' => $order->tracking_code
                ]);
                $syncLog->markAsSkipped($message);
                return;
            }

            $blingService = app(BlingService::class);

            // Busca a nota fiscal completa
            $invoice = $blingService->fetchInvoiceByOrderNumber($order->order_number);

            if (!$invoice) {
                $message = 'Invoice not found in Bling';
                Log::debug($message, ['order_number' => $order->order_number]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Busca detalhes completos da nota fiscal
            $fullInvoice = $blingService->fetchInvoiceById($invoice['id']);

            if (!$fullInvoice) {
                $message = 'Could not fetch full invoice details';
                Log::debug($message, [
                    'order_number' => $order->order_number,
                    'invoice_id' => $invoice['id']
                ]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Extrai dados da nota fiscal incluindo transporte
            $invoiceData = $blingService->extractInvoiceData($fullInvoice);
            $transportInfo = $invoiceData['transport'] ?? [];

            if (empty($transportInfo)) {
                $message = 'No transport info found in invoice';
                Log::debug($message, ['order_number' => $order->order_number]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Tenta buscar código de rastreamento via objeto logístico
            $updateData = [];
            $trackingCode = $transportInfo['tracking_code'] ?? null;

            if (!empty($transportInfo['logistics_object_id'])) {
                Log::debug('Fetching tracking from logistics object', [
                    'order_id' => $order->id,
                    'logistics_object_id' => $transportInfo['logistics_object_id']
                ]);

                $logisticsObject = $blingService->fetchLogisticsObjectById($transportInfo['logistics_object_id']);

                if ($logisticsObject) {
                    $trackingData = $blingService->extractTrackingData($logisticsObject);

                    if (!empty($trackingData['tracking_code'])) {
                        $trackingCode = $trackingData['tracking_code'];
                        
                        if (!empty($trackingData['tracking_url'])) {
                            $updateData['tracking_url'] = $trackingData['tracking_url'];
                        }
                        
                        if (!empty($trackingData['description'])) {
                            $updateData['tracking_status'] = $trackingData['description'];
                        }
                    }
                }
            }

            if (empty($trackingCode)) {
                $message = 'No tracking code found in transport info';
                Log::debug($message, ['order_number' => $order->order_number]);
                $syncLog->markAsSkipped($message);
                return;
            }

            // Atualiza o pedido com informações de rastreamento
            $updateData['tracking_code'] = $trackingCode;
            
            if (!empty($transportInfo['logistics_provider'])) {
                $updateData['logistics_provider'] = $transportInfo['logistics_provider'];
            }

            $order->update($updateData);

            $syncLog->markAsSuccess('Rastreio buscado com sucesso', [
                'tracking_code' => $trackingCode,
                'logistics_provider' => $transportInfo['logistics_provider'] ?? null,
                'has_tracking_url' => isset($updateData['tracking_url']),
                'has_status' => isset($updateData['tracking_status']),
            ]);

            Log::info('Tracking fetch completed successfully', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'tracking_code' => $trackingCode,
                'logistics_provider' => $transportInfo['logistics_provider'] ?? null,
            ]);

        } catch (Exception $e) {
            Log::error('Tracking fetch failed', [
                'order_id' => $this->orderId,
                'error' => $e->getMessage()
            ]);

            if ($syncLog) {
                $syncLog->markAsFailed($e->getMessage(), $e);
            }

            // Não lança exceção para não bloquear a fila
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FetchTrackingDataJob failed permanently', [
            'error' => $exception->getMessage(),
            'order_id' => $this->orderId,
            'attempts' => $this->attempts()
        ]);
    }
}
