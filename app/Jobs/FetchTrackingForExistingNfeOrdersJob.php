<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\BlingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class FetchTrackingForExistingNfeOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 600; // 10 minutos

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting fetch tracking for existing NFE orders');

            $batchSize = config('bling.sync.batch_size', 20); // Menor para não sobrecarregar
            
            // Busca pedidos que têm NFE mas não têm tracking
            $orders = Order::whereNotNull('nfe_number')
                ->whereNull('tracking_code')
                ->limit($batchSize)
                ->get();

            if ($orders->isEmpty()) {
                Log::info('No orders found with NFE but without tracking');
                return;
            }

            Log::info('Found orders with NFE but without tracking', [
                'count' => $orders->count()
            ]);

            $blingService = app(BlingService::class);
            $processed = 0;
            $updated = 0;
            $errors = 0;

            foreach ($orders as $order) {
                try {
                    Log::debug('Processing order for tracking', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'nfe_number' => $order->nfe_number
                    ]);

                    // Busca a nota fiscal básica por número do pedido
                    $invoice = $blingService->fetchInvoiceByOrderNumber($order->order_number);

                    if (!$invoice) {
                        Log::debug('No invoice found for order', [
                            'order_number' => $order->order_number
                        ]);
                        $processed++;
                        continue;
                    }

                    // Busca detalhes completos da nota fiscal por ID
                    $fullInvoice = $blingService->fetchInvoiceById($invoice['id']);

                    if (!$fullInvoice) {
                        Log::debug('Could not fetch full invoice details', [
                            'order_number' => $order->order_number,
                            'invoice_id' => $invoice['id']
                        ]);
                        $processed++;
                        continue;
                    }

                    // Extrai dados da nota fiscal completa incluindo informações de transporte
                    $invoiceData = $blingService->extractInvoiceData($fullInvoice);
                    $transportInfo = $invoiceData['transport'] ?? [];

                    if (empty($transportInfo) || !isset($transportInfo['logistics_provider'])) {
                        Log::debug('No transport info found in invoice', [
                            'order_number' => $order->order_number
                        ]);
                        $processed++;
                        continue;
                    }

                    // Atualiza o pedido com informações de transporte
                    $updateData = [];

                    if (!empty($transportInfo['logistics_provider'])) {
                        $updateData['logistics_provider'] = $transportInfo['logistics_provider'];
                    }

                    // Tenta buscar código de rastreamento real via objeto logístico
                    $trackingCode = $transportInfo['tracking_code'] ?? null;
                    
                    if (!empty($transportInfo['logistics_object_id'])) {
                        Log::debug('Fetching detailed tracking info for logistics object', [
                            'order_number' => $order->order_number,
                            'logistics_object_id' => $transportInfo['logistics_object_id']
                        ]);
                        
                        $logisticsObject = $blingService->fetchLogisticsObjectById($transportInfo['logistics_object_id']);
                        
                        if ($logisticsObject) {
                            $trackingData = $blingService->extractTrackingData($logisticsObject);
                            
                            if (!empty($trackingData['tracking_code'])) {
                                $trackingCode = $trackingData['tracking_code'];
                                
                                Log::info('Detailed tracking info retrieved from logistics object', [
                                    'order_id' => $order->id,
                                    'order_number' => $order->order_number,
                                    'logistics_object_id' => $transportInfo['logistics_object_id'],
                                    'tracking_code' => $trackingCode,
                                    'status' => $trackingData['status'] ?? null,
                                    'description' => $trackingData['description'] ?? null,
                                    'service' => $trackingData['service'] ?? null
                                ]);
                            }
                        }
                    }
                    
                    if (!empty($trackingCode)) {
                        $updateData['tracking_code'] = $trackingCode;
                    }

                    // Adiciona URL de rastreamento se disponível
                    if (!empty($trackingData['tracking_url'])) {
                        $updateData['tracking_url'] = $trackingData['tracking_url'];
                    }

                    // Adiciona status de rastreamento se disponível
                    if (!empty($trackingData['description'])) {
                        $updateData['tracking_status'] = $trackingData['description'];
                    }

                    if (!empty($updateData)) {
                        $order->update($updateData);
                        $updated++;

                        Log::info('Order tracking info updated via job', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'logistics_provider' => $transportInfo['logistics_provider'],
                            'tracking_code' => $trackingCode ?? 'not_found',
                            'tracking_url' => $trackingData['tracking_url'] ?? 'not_found',
                            'tracking_status' => $trackingData['description'] ?? 'not_found'
                        ]);
                    }

                    $processed++;

                } catch (Exception $e) {
                    $errors++;
                    Log::error('Failed to fetch tracking info for order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Fetch tracking for existing NFE orders completed', [
                'processed' => $processed,
                'updated' => $updated,
                'errors' => $errors
            ]);

        } catch (Exception $e) {
            Log::error('FetchTrackingForExistingNfeOrdersJob failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FetchTrackingForExistingNfeOrdersJob failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}