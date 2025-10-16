<?php

namespace App\Listeners;

use App\Events\OrderSynced;
use App\Jobs\SyncSellerJob;
use Illuminate\Support\Facades\Log;

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
        Log::info('QueueSellerSync: Queuing seller sync', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->numero,
            'was_created' => $event->wasCreated,
        ]);

        // Dispatch seller sync job to the sellers queue
        dispatch(new SyncSellerJob($event->order->id));
    }
}

