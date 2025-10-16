<?php

namespace App\Events;

use App\Models\OrderSummary;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted after an order has been synced from Bling.
 * This is an INTERNAL event used to coordinate job execution.
 * It does NOT broadcast to the frontend.
 */
class OrderSynced
{
    use Dispatchable, SerializesModels;

    public OrderSummary $order;
    public bool $wasCreated;
    public array $changedFields;

    /**
     * Create a new event instance.
     */
    public function __construct(OrderSummary $order, bool $wasCreated = false, array $changedFields = [])
    {
        $this->order = $order;
        $this->wasCreated = $wasCreated;
        $this->changedFields = $changedFields;
    }
}

