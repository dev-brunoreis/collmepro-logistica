<?php

namespace App\Listeners;

use App\Events\InvoiceSynced;
use App\Jobs\ExtractTrackingJob;
use Illuminate\Support\Facades\Log;

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
        Log::info('QueueTrackingExtraction: Queuing tracking extraction', [
            'invoice_id' => $event->invoice->id,
            'invoice_number' => $event->invoice->numero,
            'order_id' => $event->invoice->order_summary_id,
            'was_created' => $event->wasCreated,
        ]);

        // Dispatch tracking extraction job to the tracking queue
        dispatch(new ExtractTrackingJob($event->invoice->order_summary_id));
    }
}

