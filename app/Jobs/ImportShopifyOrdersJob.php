<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Exception;

class ImportShopifyOrdersJob implements ShouldQueue
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
        try {
            Log::info("Starting Shopify orders import for system: {$this->system}");

            $shopifyService = app(ShopifyService::class);
            $orders = $shopifyService->fetchOrders('collme_beauty'); // Use 'collme_beauty' for the service

            if (empty($orders)) {
                Log::info("No orders found for system: {$this->system}");
                return;
            }

            $importedCount = 0;
            $skippedCount = 0;

            foreach ($orders as $orderData) {
                if ($this->importOrder($orderData)) {
                    $importedCount++;
                } else {
                    $skippedCount++;
                }
            }

            Log::info("Shopify import completed for {$this->system}", [
                'imported' => $importedCount,
                'skipped' => $skippedCount,
                'total' => count($orders)
            ]);

        } catch (Exception $e) {
            Log::error("Shopify import failed for {$this->system}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Import a single order.
     */
    private function importOrder(array $orderData): bool
    {
        try {
            // Check if order already exists
            $existingOrder = Order::where('system', 'collme_beauty')
                ->where('order_number', $orderData['number'])
                ->first();

            if ($existingOrder) {
                Log::debug("Order already exists, skipping: {$orderData['number']}");
                return false;
            }

            // Create new order
            $order = Order::create([
                'system' => 'collme_beauty', // Always use collme_beauty for Shopify orders
                'order_number' => $orderData['number'],
                'customer_name' => $orderData['customer_name'],
                'seller_name' => $orderData['seller_name'],
                'order_date' => $this->parseOrderDate($orderData['date_created']),
            ]);

            // Create order status
            $orderStatus = OrderStatus::create([
                'order_id' => $order->id,
                'picking' => false,
                'packing' => false,
                'picked_up' => false,
            ]);

            Log::debug("OrderStatus created", ['order_status_id' => $orderStatus->id, 'order_id' => $order->id]);

            Log::debug("Order imported successfully: {$orderData['number']}");
            return true;

        } catch (Exception $e) {
            Log::error("Failed to import order: " . ($orderData['number'] ?? 'unknown'), [
                'error' => $e->getMessage(),
                'order_data' => $orderData
            ]);
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
    public function failed(\Throwable $exception): void
    {
        Log::error("Shopify import job failed permanently", [
            'system' => $this->system,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
