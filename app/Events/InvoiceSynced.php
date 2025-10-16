<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted after an invoice has been synced from Bling.
 * This is an INTERNAL event used to coordinate job execution.
 * It does NOT broadcast to the frontend.
 */
class InvoiceSynced
{
    use Dispatchable, SerializesModels;

    public Invoice $invoice;
    public bool $wasCreated;

    /**
     * Create a new event instance.
     */
    public function __construct(Invoice $invoice, bool $wasCreated = false)
    {
        $this->invoice = $invoice;
        $this->wasCreated = $wasCreated;
    }
}

