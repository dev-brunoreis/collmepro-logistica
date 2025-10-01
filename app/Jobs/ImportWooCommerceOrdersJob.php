<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\SyncLog;
use App\Services\WooCommerceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportWooCommerceOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $system;
    public int $tries = 3;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct(string $system)
    {
        $this->system = $system;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $syncLog = null;
        
        try {
            Log::info("Starting WooCommerce orders import for system: {$this->system}");

            // Cria log de sincronização geral
            $syncLog = SyncLog::create([
                'sync_type' => SyncLog::TYPE_ORDER_IMPORT,
                'system' => $this->system,
                'status' => SyncLog::STATUS_PROCESSING,
                'started_at' => now(),
                'job_id' => $this->job ? $this->job->getJobId() : null,
            ]);

            $wooCommerceService = app(WooCommerceService::class);
            $orders = $wooCommerceService->fetchOrders($this->system);

            if (empty($orders)) {
                Log::info("No orders found for system: {$this->system}");
                $syncLog->markAsSuccess('No orders found to import');
                return;
            }

            // 🔥 PROCESSA TODOS OS PEDIDOS NOVOS
            // Cada pedido dispara seu próprio fluxo: Pedido → NFE → Rastreio
            $importedCount = 0;
            $skippedCount = 0;

            foreach ($orders as $orderData) {
                // Verifica se já existe
                $existingOrder = Order::where('system', $this->system)
                    ->where('order_number', $orderData['number'])
                    ->first();

                if ($existingOrder) {
                    $skippedCount++;
                    continue;
                }

                // Importa o pedido e dispara cadeia de sync
                if ($this->importOrder($orderData)) {
                    $importedCount++;
                }
            }

            $message = $importedCount > 0 
                ? "Imported {$importedCount} order(s) successfully" 
                : "No new orders to import (all exist)";
            
            $syncLog->markAsSuccess($message, [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'total_found' => count($orders),
            ]);

            Log::info("WooCommerce import completed for {$this->system}", [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'total' => count($orders)
            ]);

        } catch (Exception $e) {
            Log::error("WooCommerce import failed for {$this->system}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($syncLog) {
                $syncLog->markAsFailed($e->getMessage(), $e);
            }

            throw $e;
        }
    }

    /**
     * Import a single order.
     */
    private function importOrder(array $orderData): bool
    {
        $orderSyncLog = null;
        
        try {
            // Check if order already exists
            $existingOrder = Order::where('system', $this->system)
                ->where('order_number', $orderData['number'])
                ->first();

            if ($existingOrder) {
                Log::debug("Order already exists, skipping: {$orderData['number']}");
                return false;
            }

            // Cria log de importação para este pedido específico
            $orderSyncLog = SyncLog::create([
                'sync_type' => SyncLog::TYPE_ORDER_IMPORT,
                'system' => $this->system,
                'status' => SyncLog::STATUS_PROCESSING,
                'started_at' => now(),
                'external_id' => $orderData['number'],
                'request_data' => $orderData,
            ]);

            // Create new order
            $order = Order::create([
                'system' => $this->system,
                'order_number' => $orderData['number'],
                'customer_name' => $orderData['customer_name'],
                'seller_name' => $orderData['seller_name'],
                'order_date' => $this->parseOrderDate($orderData['date_created']),
            ]);

            // Atualiza o log com o ID do pedido criado
            $orderSyncLog->update(['order_id' => $order->id]);

            // Create order status
            $orderStatus = OrderStatus::create([
                'order_id' => $order->id,
                'picking' => false,
                'packing' => false,
                'picked_up' => false,
            ]);

            Log::debug("OrderStatus created", ['order_status_id' => $orderStatus->id, 'order_id' => $order->id]);

            // Marca log como sucesso
            $orderSyncLog->markAsSuccess("Pedido importado com sucesso", [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            // 🔥 ENCADEIA: Dispara job para buscar NFE logo após importar o pedido
            SyncBlingDataJob::dispatch($order->id)
                ->delay(now()->addSeconds(3)); // Aguarda 3 segundos antes de buscar NFE

            Log::debug("Order imported successfully and NFE sync job dispatched: {$orderData['number']}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to import order: {$orderData['number']}", [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);

            if ($orderSyncLog) {
                $orderSyncLog->markAsFailed($e->getMessage(), $e);
            }

            return false;
        }
    }

    /**
     * Parse order date from API format.
     */
    private function parseOrderDate(string $dateString): \DateTime
    {
        try {
            return new \DateTime($dateString);
        } catch (Exception $e) {
            Log::warning("Invalid date format, using current time: {$dateString}");
            return new \DateTime();
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(Exception $exception): void
    {
        Log::error("WooCommerce import job failed permanently for {$this->system}", [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['woocommerce', 'import', $this->system];
    }
}
