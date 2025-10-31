<?php

namespace App\Listeners;

use App\Events\OrderSynced;
use App\Jobs\SyncInvoiceJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Listener that queues invoice synchronization when an order is synced.
 */
class QueueInvoiceSync
{
    /**
     * Handle the event.
     */
    public function handle(OrderSynced $event): void
    {
        $orderId = $event->order->id;
        $cacheKey = "invoice_sync_queued_{$orderId}";
        // Use a distinct lock key for queuing to avoid colliding with the processing job lock
        $lockKey = "invoice_sync_queue_lock_{$orderId}";

        // Prevent duplicate job creation with atomic lock
        if (!Cache::lock($lockKey, 10)->get()) {
            Log::info('QueueInvoiceSync: Skipped (already being processed)', [
                'order_id' => $orderId,
                'order_number' => $event->order->numero,
            ]);
            return;
        }

        try {
            // Check if job was already queued recently (within 1 minute)
            if (Cache::has($cacheKey)) {
                Log::info('QueueInvoiceSync: Skipped (already queued recently)', [
                    'order_id' => $orderId,
                    'order_number' => $event->order->numero,
                ]);
                return;
            }

            // Check if job already exists in queue
            $existingJob = DB::table('jobs')
                ->where('queue', 'invoices')
                ->where('payload', 'like', '%"displayName":"App\\\\Jobs\\\\SyncInvoiceJob"%')
                ->where('payload', 'like', '%"orderSummaryId":' . $orderId . '%')
                ->first();

            if ($existingJob) {
                Log::info('QueueInvoiceSync: Skipped (job already exists in queue)', [
                    'order_id' => $orderId,
                    'order_number' => $event->order->numero,
                    'existing_job_id' => $existingJob->id,
                ]);
                return;
            }

            // Check queue size before enqueueing to prevent overflow
            $queueSize = DB::table('jobs')
                ->where('queue', 'invoices')
                ->count();

            // If queue is too large (>2000 jobs), skip to prevent further overload
            // This prevents the queue from growing indefinitely
            if ($queueSize > 2000) {
                Log::warning('QueueInvoiceSync: Skipped (queue too large)', [
                    'order_id' => $orderId,
                    'order_number' => $event->order->numero,
                    'queue_size' => $queueSize,
                    'threshold' => 2000,
                ]);
                return;
            }

            Log::info('QueueInvoiceSync: Queuing invoice sync', [
                'order_id' => $orderId,
                'order_number' => $event->order->numero,
                'was_created' => $event->wasCreated,
                'queue_size' => $queueSize,
            ]);

            // Dispatch invoice sync job to the invoices queue with small delay for throttling
            // Add progressive delay based on queue size to smooth out load spikes
            $delaySeconds = min($queueSize * 0.01, 5); // Max 5 seconds delay
            dispatch(new SyncInvoiceJob($orderId))
                ->onQueue('invoices')
                ->delay(now()->addSeconds($delaySeconds));

            // Mark as queued to prevent duplicates (1 minute)
            Cache::put($cacheKey, now(), 60);

        } finally {
            // Always release the lock
            Cache::lock($lockKey)->release();
        }
    }
}

