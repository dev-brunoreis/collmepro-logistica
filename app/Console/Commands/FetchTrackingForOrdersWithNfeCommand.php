<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BlingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FetchTrackingForOrdersWithNfeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:fetch-tracking-for-nfe 
                            {--limit=10 : Number of orders to process}
                            {--order-id= : Process specific order ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch tracking information for orders that have NFE but no tracking code';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $limit = $this->option('limit');
        $orderId = $this->option('order-id');

        $this->info('Fetching tracking information for orders with NFE...');

        try {
            $blingService = app(BlingService::class);

            $query = Order::whereNotNull('nfe_number')
                ->whereNull('tracking_code');

            if ($orderId) {
                $query->where('id', $orderId);
            }

            $orders = $query->limit($limit)->get();

            if ($orders->isEmpty()) {
                $this->info('No orders found with NFE but without tracking code.');
                return 0;
            }

            $this->info("Found {$orders->count()} orders to process.");

            $processed = 0;
            $updated = 0;
            $errors = 0;

            $progressBar = $this->output->createProgressBar($orders->count());
            $progressBar->start();

            foreach ($orders as $order) {
                try {
                    $this->line("\nProcessing order: {$order->order_number} (NFE: {$order->nfe_number})");

                    // Busca a nota fiscal básica por número do pedido
                    $invoice = $blingService->fetchInvoiceByOrderNumber($order->order_number);

                    if (!$invoice) {
                        $this->warn("  No invoice found for order {$order->order_number}");
                        $processed++;
                        $progressBar->advance();
                        continue;
                    }

                    // Busca detalhes completos da nota fiscal por ID
                    $fullInvoice = $blingService->fetchInvoiceById($invoice['id']);

                    if (!$fullInvoice) {
                        $this->warn("  Could not fetch full invoice details for order {$order->order_number}");
                        $processed++;
                        $progressBar->advance();
                        continue;
                    }

                // Extrai dados da nota fiscal completa incluindo informações de transporte
                $invoiceData = $blingService->extractInvoiceData($fullInvoice);
                $transportInfo = $invoiceData['transport'] ?? [];

                if (empty($transportInfo) || !isset($transportInfo['logistics_provider'])) {
                    $this->warn("  No transport info found in invoice for order {$order->order_number}");
                    $processed++;
                    $progressBar->advance();
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
                    $this->line("  🔍 Fetching detailed tracking info for logistics object: {$transportInfo['logistics_object_id']}");
                    
                    $logisticsObject = $blingService->fetchLogisticsObjectById($transportInfo['logistics_object_id']);
                    
                    if ($logisticsObject) {
                        $trackingData = $blingService->extractTrackingData($logisticsObject);
                        
                        if (!empty($trackingData['tracking_code'])) {
                            $trackingCode = $trackingData['tracking_code'];
                            $this->line("  📦 Found tracking code: {$trackingCode}");
                            
                            // Log additional tracking details
                            Log::info('Detailed tracking info retrieved', [
                                'order_id' => $order->id,
                                'order_number' => $order->order_number,
                                'logistics_object_id' => $transportInfo['logistics_object_id'],
                                'tracking_code' => $trackingCode,
                                'status' => $trackingData['status'] ?? null,
                                'description' => $trackingData['description'] ?? null,
                                'service' => $trackingData['service'] ?? null
                            ]);
                        } else {
                            $this->warn("  No tracking code found in logistics object details");
                        }
                    } else {
                        $this->warn("  Could not fetch logistics object details");
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

                    $logisticsProvider = $transportInfo['logistics_provider'];
                    $trackingDisplay = $trackingCode ?? 'N/A';
                    $trackingUrl = $trackingData['tracking_url'] ?? 'N/A';
                    $trackingStatus = $trackingData['description'] ?? 'N/A';

                    $this->info("  ✅ Updated with transport: {$logisticsProvider} - Tracking: {$trackingDisplay}");
                    $this->info("  🔗 Tracking URL: {$trackingUrl}");
                    $this->info("  📊 Status: {$trackingStatus}");

                    Log::info('Order transport info updated via command', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'tracking_code' => $trackingDisplay,
                        'logistics_provider' => $logisticsProvider,
                        'tracking_url' => $trackingUrl,
                        'tracking_status' => $trackingStatus
                    ]);
                } else {
                    $this->warn("  No transport data to update for order {$order->order_number}");
                }

                    $processed++;

                } catch (\Exception $e) {
                    $errors++;
                    $this->error("  ❌ Error processing order {$order->order_number}: {$e->getMessage()}");
                    
                    Log::error('Failed to fetch transport info for order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage()
                    ]);
                }

                $progressBar->advance();
            }

            $progressBar->finish();

            $this->newLine(2);
            $this->info("Processing completed!");
            $this->info("  📊 Processed: {$processed} orders");
            $this->info("  ✅ Updated: {$updated} orders");
            $this->info("  ❌ Errors: {$errors} orders");

            return 0;

        } catch (\Exception $e) {
            $this->error("Command failed: {$e->getMessage()}");
            Log::error('FetchTrackingForOrdersWithNfeCommand failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
