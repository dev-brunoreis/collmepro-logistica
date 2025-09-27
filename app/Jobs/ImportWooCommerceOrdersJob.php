<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\OrderStatus;
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
        try {
            Log::info("Starting WooCommerce orders import for system: {$this->system}");

            $wooCommerceService = app(WooCommerceService::class);
            $orders = $wooCommerceService->fetchOrders($this->system);

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
            $existingOrder = Order::where('system', $this->system)
                ->where('order_number', $orderData['number'])
                ->first();

            if ($existingOrder) {
                Log::debug("Order already exists, skipping: {$orderData['number']}");
                return false;
            }

            // Create new order
            $order = Order::create([
                'system' => $this->system,
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
            Log::error("Failed to import order: {$orderData['number']}", [
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
