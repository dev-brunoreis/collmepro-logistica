<?php

namespace App\Events;

use App\Models\OrderSummary;
use App\Models\Seller;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted after seller information has been synced from Bling.
 * This is an INTERNAL event used to coordinate job execution.
 * It does NOT broadcast to the frontend.
 */
class SellerSynced
{
    use Dispatchable, SerializesModels;

    public OrderSummary $order;
    public ?Seller $seller;

    /**
     * Create a new event instance.
     */
    public function __construct(OrderSummary $order, ?Seller $seller = null)
    {
        $this->order = $order;
        $this->seller = $seller;
    }
}

