<?php

namespace App\Events;

use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OrderSummary $order;
    public User $user;

    /**
     * Create a new event instance.
     */
    public function __construct(OrderSummary $order, User $user)
    {
        $this->order = $order;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('orders'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        // Load relationships if not already loaded
        $this->order->loadMissing(['orderStatus']);

        // Load invoice relationship if not already loaded
        $this->order->loadMissing(['invoice']);

        return [
            'order' => [
                'id' => $this->order->id,
                'bling_id' => $this->order->bling_id,
                'numero' => $this->order->numero,
                'contato_nome' => $this->order->contato_nome,
                'total' => $this->order->total,
                'data' => $this->order->data,
                'source' => $this->order->source,
                'invoice_number' => $this->order->invoice?->numero ?? null,
                'items_quantity' => $this->order->total_produtos ?? 0,
                'tracking_code' => $this->order->tracking_code,
                'order_status' => $this->order->orderStatus ? [
                    'picking' => $this->order->orderStatus->picking,
                    'packing' => $this->order->orderStatus->packing,
                    'picked_up' => $this->order->orderStatus->picked_up,
                    'picking_marked_by' => $this->order->orderStatus->picking_marked_by,
                    'picking_marked_at' => $this->order->orderStatus->picking_marked_at,
                    'packing_marked_by' => $this->order->orderStatus->packing_marked_by,
                    'packing_marked_at' => $this->order->orderStatus->packing_marked_at,
                    'picked_up_marked_by' => $this->order->orderStatus->picked_up_marked_by,
                    'picked_up_marked_at' => $this->order->orderStatus->picked_up_marked_at,
                ] : null,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.created';
    }
}

