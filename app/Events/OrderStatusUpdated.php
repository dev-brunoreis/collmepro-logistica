<?php

namespace App\Events;

use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OrderSummary $order;
    public User $user;
    public string $action;
    public bool $status;
    public string $actionName;

    /**
     * Create a new event instance.
     */
    public function __construct(
        OrderSummary $order,
        User $user,
        string $action,
        bool $status
    ) {
        $this->order = $order;
        $this->user = $user;
        $this->action = $action;
        $this->status = $status;
        
        $actionNames = [
            'picking' => 'Separado',
            'packing' => 'Embalado',
            'picked_up' => 'Coletado'
        ];
        
        $this->actionName = $actionNames[$action] ?? $action;
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
            new Channel("orders.{$this->order->id}")
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'order' => [
                'id' => $this->order->id,
                'numero' => $this->order->numero,
                'contato_nome' => $this->order->contato_nome,
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ],
            'action' => $this->action,
            'action_name' => $this->actionName,
            'status' => $this->status,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'status.updated';
    }
}
