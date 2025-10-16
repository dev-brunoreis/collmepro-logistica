<?php

namespace App\Listeners;

use App\Events\OrderSynced;
use App\Jobs\SyncInvoiceJob;
use Illuminate\Support\Facades\Log;

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
        Log::info('QueueInvoiceSync: Queuing invoice sync', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->numero,
            'was_created' => $event->wasCreated,
        ]);

        // Dispatch invoice sync job to the invoices queue
        dispatch(new SyncInvoiceJob($event->order->id));
    }
}

