<?php

namespace App\Listeners;

use App\Events\InvoiceSynced;
use App\Jobs\ExtractTrackingJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Listener that queues tracking extraction when an invoice is synced.
 */
class QueueTrackingExtraction
{
    /**
     * Handle the event.
     */
    public function handle(InvoiceSynced $event): void
    {
        $orderId = $event->invoice->order_summary_id;
        $cacheKey = "tracking_extraction_queued_{$orderId}";
        // Use a distinct lock for queuing to avoid colliding with job uniqueness
        $lockKey = "tracking_extraction_queue_lock_{$orderId}";

        // Prevent duplicate job creation with atomic lock
        if (!Cache::lock($lockKey, 10)->get()) {
            Log::info('QueueTrackingExtraction: Skipped (already being processed)', [
                'order_id' => $orderId,
                'invoice_id' => $event->invoice->id,
            ]);
            return;
        }

        try {
            // Check if job was already queued recently (within 1 minute)
            if (Cache::has($cacheKey)) {
                Log::info('QueueTrackingExtraction: Skipped (already queued recently)', [
                    'order_id' => $orderId,
                    'invoice_id' => $event->invoice->id,
                ]);
                return;
            }

            // Check if job already exists in queue
            $existingJob = DB::table('jobs')
                ->where('queue', 'tracking')
                ->where('payload', 'like', '%"displayName":"App\\\\Jobs\\\\ExtractTrackingJob"%')
                ->where('payload', 'like', '%"orderSummaryId":' . $orderId . '%')
                ->first();

            if ($existingJob) {
                Log::info('QueueTrackingExtraction: Skipped (job already exists in queue)', [
                    'order_id' => $orderId,
                    'invoice_id' => $event->invoice->id,
                    'existing_job_id' => $existingJob->id,
                ]);
                return;
            }

            Log::info('QueueTrackingExtraction: Queuing tracking extraction', [
                'invoice_id' => $event->invoice->id,
                'invoice_number' => $event->invoice->numero,
                'order_id' => $orderId,
                'was_created' => $event->wasCreated,
            ]);

            // Dispatch tracking extraction job to the tracking queue
            dispatch(new ExtractTrackingJob($orderId))->onQueue('tracking');

            // Mark as queued to prevent duplicates (1 minute)
            Cache::put($cacheKey, now(), 60);

        } finally {
            // Always release the lock
            Cache::lock($lockKey)->release();
        }
    }
}

