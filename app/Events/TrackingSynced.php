<?php

namespace App\Events;

use App\Models\OrderSummary;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted after tracking information has been extracted and synced.
 * This is an INTERNAL event used to coordinate job execution.
 * It does NOT broadcast to the frontend.
 */
class TrackingSynced
{
    use Dispatchable, SerializesModels;

    public OrderSummary $order;
    public ?string $trackingCode;

    /**
     * Create a new event instance.
     */
    public function __construct(OrderSummary $order, ?string $trackingCode = null)
    {
        $this->order = $order;
        $this->trackingCode = $trackingCode;
    }
}

