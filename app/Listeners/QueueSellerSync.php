<?php

namespace App\Listeners;

use App\Events\OrderSynced;
use App\Jobs\SyncSellerJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Listener that queues seller synchronization when an order is synced.
 */
class QueueSellerSync
{
    /**
     * Handle the event.
     */
    public function handle(OrderSynced $event): void
    {
        $orderId = $event->order->id;
        $cacheKey = "seller_sync_queued_{$orderId}";
        $lockKey = "seller_sync_lock_{$orderId}";

        // Prevent duplicate job creation with atomic lock
        if (!Cache::lock($lockKey, 10)->get()) {
            Log::info('QueueSellerSync: Skipped (already being processed)', [
                'order_id' => $orderId,
                'order_number' => $event->order->numero,
            ]);
            return;
        }

        try {
            // Check if job was already queued recently (within 1 minute)
            if (Cache::has($cacheKey)) {
                Log::info('QueueSellerSync: Skipped (already queued recently)', [
                    'order_id' => $orderId,
                    'order_number' => $event->order->numero,
                ]);
                return;
            }

            // Check if job already exists in queue
            $existingJob = DB::table('jobs')
                ->where('queue', 'sellers')
                ->where('payload', 'like', '%"displayName":"App\\\\Jobs\\\\SyncSellerJob"%')
                ->where('payload', 'like', '%"orderSummaryId":' . $orderId . '%')
                ->first();

            if ($existingJob) {
                Log::info('QueueSellerSync: Skipped (job already exists in queue)', [
                    'order_id' => $orderId,
                    'order_number' => $event->order->numero,
                    'existing_job_id' => $existingJob->id,
                ]);
                return;
            }

            Log::info('QueueSellerSync: Queuing seller sync', [
                'order_id' => $orderId,
                'order_number' => $event->order->numero,
                'was_created' => $event->wasCreated,
            ]);

            // Dispatch seller sync job to the sellers queue
            dispatch(new SyncSellerJob($orderId))->onQueue('sellers');

            // Mark as queued to prevent duplicates (1 minute)
            Cache::put($cacheKey, now(), 60);

        } finally {
            // Always release the lock
            Cache::lock($lockKey)->release();
        }
    }
}

