<?php

namespace App\Events;

use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public OrderSummary $order;
    public User $user;
    public array $changedFields;
    public array $changeTypes;

    /**
     * Create a new event instance.
     */
    public function __construct(
        OrderSummary $order,
        User $user,
        array $changedFields = [],
        array $changeTypes = []
    ) {
        $this->order = $order;
        $this->user = $user;
        $this->changedFields = $changedFields;
        $this->changeTypes = $changeTypes;
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
        // Load all necessary relationships
        $this->order->loadMissing(['orderStatus', 'invoice']);

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
                'invoice_id' => $this->order->invoice?->id ?? null,
                'items_quantity' => $this->order->total_produtos ?? 0,
                'tracking_code' => $this->order->tracking_code,
                'tracking_url' => $this->getTrackingUrl(),
                'logistics_provider' => $this->order->logistics_provider,
                'service_type' => $this->order->service_type,
                'situacao_id' => $this->order->situacao_id,
                'situacao_valor' => $this->order->situacao_valor,
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
            'changed_fields' => $this->changedFields,
            'change_types' => $this->changeTypes,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get tracking URL based on tracking code and provider
     */
    private function getTrackingUrl(): ?string
    {
        if (!$this->order->tracking_code) {
            return null;
        }

        // Get from invoice tracking if available
        if ($this->order->invoice) {
            $tracking = $this->order->invoice->tracking()->first();
            if ($tracking && $tracking->url_rastreamento) {
                return $tracking->url_rastreamento;
            }
        }

        // Fallback to basic tracking URL based on provider
        $code = $this->order->tracking_code;
        $provider = strtolower($this->order->logistics_provider ?? '');
        $serviceType = strtolower($this->order->service_type ?? '');

        // Correios
        if (str_contains($provider, 'correios') || 
            str_contains($serviceType, 'pac') || 
            str_contains($serviceType, 'sedex')) {
            return "https://rastreamento.correios.com.br/app/index.php?objeto={$code}";
        }

        // Total Express
        if (str_contains($provider, 'total') || str_contains($serviceType, 'total')) {
            return "https://www.totalexpress.com.br/tracking/{$code}";
        }

        // Melhor Envio
        if (str_contains($provider, 'melhor') || str_contains($serviceType, 'melhor')) {
            return "https://melhorenvio.com.br/rastreio/{$code}";
        }

        return null;
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'order.updated';
    }
}

