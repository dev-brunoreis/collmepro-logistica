<?php

namespace App\Jobs;

use App\Models\OrderSummary;
use App\Traits\TrackingLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RefreshTrackingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrackingLogger;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;
    public array $backoff = [60, 300]; // 1min, 5min

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        $this->onQueue('default');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $startTime = now();
        
        $this->logTrackingInfo('Starting periodic tracking refresh', [
            'started_at' => $startTime->toDateTimeString(),
        ]);

        try {
            // Query orders from last 30 days with invoice and tracking code
            $orders = OrderSummary::whereNotNull('tracking_code')
                ->whereHas('invoice')
                ->where('created_at', '>=', now()->subDays(30))
                ->notCancelled()
                ->get();

            $totalOrders = $orders->count();
            $enqueuedCount = 0;
            $skippedCount = 0;

            $this->logTrackingInfo('Found orders for tracking refresh', [
                'total_orders' => $totalOrders,
            ]);

            foreach ($orders as $order) {
                try {
                    // Check if job already exists in queue to avoid duplicates
                    $existingJob = DB::table('jobs')
                        ->where('queue', 'tracking')
                        ->where('payload', 'like', '%"displayName":"App\\\\Jobs\\\\ExtractTrackingJob"%')
                        ->where('payload', 'like', '%"orderSummaryId":' . $order->id . '%')
                        ->first();

                    if ($existingJob) {
                        $skippedCount++;
                        continue;
                    }

                    // Dispatch tracking extraction job
                    dispatch(new ExtractTrackingJob($order->id))->onQueue('tracking');
                    $enqueuedCount++;

                } catch (\Exception $e) {
                    $this->logTrackingError('Failed to enqueue tracking job for order', [
                        'order_id' => $order->id,
                        'order_number' => $order->numero,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $duration = now()->diffInSeconds($startTime);

            $this->logTrackingInfo('Tracking refresh completed', [
                'total_orders' => $totalOrders,
                'enqueued' => $enqueuedCount,
                'skipped' => $skippedCount,
                'duration_seconds' => $duration,
                'completed_at' => now()->toDateTimeString(),
            ]);

        } catch (\Exception $e) {
            $this->logTrackingError('Tracking refresh failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->logTrackingError('Tracking refresh job failed permanently', [
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}

