<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\SyncLog;
use App\Jobs\FetchTrackingDataJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateTrackingStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $syncLog = null;

        try {
            Log::info('Starting tracking status update for all orders with tracking codes');

            // Create sync log
            $syncLog = SyncLog::create([
                'sync_type' => SyncLog::TYPE_TRACKING_UPDATE,
                'status' => SyncLog::STATUS_PROCESSING,
                'started_at' => now(),
            ]);

            // Get orders with tracking code and NFE that are not delivered yet
            // Only update orders where status is not "Entregue" or "Devolvido"
            $orders = Order::whereNotNull('tracking_code')
                ->whereNotNull('nfe_number')
                ->where(function ($query) {
                    $query->whereNull('tracking_status')
                        ->orWhere('tracking_status', '!=', 'Entregue')
                        ->orWhere('tracking_status', 'not like', '%entregue%')
                        ->orWhere('tracking_status', 'not like', '%devolvido%');
                })
                ->orderBy('order_date', 'desc')
                ->limit(50) // Process 50 orders at a time to avoid timeout
                ->get();

            if ($orders->isEmpty()) {
                Log::info('No orders with tracking codes to update');
                $syncLog->markAsSuccess('No orders to update');
                return;
            }

            Log::info("Found {$orders->count()} orders to update tracking status");

            $updatedCount = 0;
            $skippedCount = 0;
            $errorCount = 0;

            foreach ($orders as $order) {
                try {
                    // Dispatch tracking update job for each order
                    FetchTrackingDataJob::dispatchSync($order->id);
                    
                    // Check if tracking was updated
                    $order->refresh();
                    
                    Log::debug("Updated tracking for order {$order->order_number}", [
                        'tracking_status' => $order->tracking_status,
                        'tracking_code' => $order->tracking_code,
                    ]);
                    
                    $updatedCount++;
                    
                    // Small delay to avoid rate limiting
                    usleep(500000); // 0.5 second delay between requests
                    
                } catch (\Exception $e) {
                    Log::warning("Failed to update tracking for order {$order->id}", [
                        'error' => $e->getMessage(),
                    ]);
                    $errorCount++;
                }
            }

            $message = "Updated tracking status for {$updatedCount} orders";
            
            $syncLog->markAsSuccess($message, [
                'updated' => $updatedCount,
                'skipped' => $skippedCount,
                'errors' => $errorCount,
                'total_processed' => $orders->count(),
            ]);

            Log::info($message, [
                'updated' => $updatedCount,
                'errors' => $errorCount,
            ]);

        } catch (\Exception $e) {
            Log::error('Tracking status update job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($syncLog) {
                $syncLog->markAsFailed($e->getMessage(), $e);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('UpdateTrackingStatusJob failed permanently', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return ['tracking', 'update', 'scheduled'];
    }
}

