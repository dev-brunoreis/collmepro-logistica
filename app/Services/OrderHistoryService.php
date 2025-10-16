<?php

namespace App\Services;

use App\Models\OrderHistory;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class OrderHistoryService
{
    /**
     * Record an event in the order history
     */
    public function recordEvent(
        OrderSummary $order,
        string $eventType,
        string $description = null,
        array $metadata = [],
        User $user = null,
        string $workerName = null
    ): OrderHistory {
        $user = $user ?? Auth::user();

        return OrderHistory::create([
            'order_summary_id' => $order->id,
            'event_type' => $eventType,
            'event_description' => $description,
            'user_id' => $user?->id,
            'worker_name' => $workerName,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Record a worker event (generic)
     */
    public function recordWorkerEvent(
        OrderSummary $order,
        string $eventType,
        string $workerName,
        string $description = null,
        array $metadata = []
    ): OrderHistory {
        return $this->recordEvent(
            $order,
            $eventType,
            $description,
            array_merge($metadata, ['worker_name' => $workerName]),
            null,
            $workerName
        );
    }

    /**
     * Record a worker success event
     */
    public function recordWorkerSuccess(
        OrderSummary $order,
        string $eventType,
        string $workerName,
        string $description,
        array $metadata = []
    ): OrderHistory {
        return $this->recordWorkerEvent(
            $order,
            $eventType,
            $workerName,
            $description,
            array_merge($metadata, ['success' => true])
        );
    }

    /**
     * Record a worker failure event
     */
    public function recordWorkerFailure(
        OrderSummary $order,
        string $eventType,
        string $workerName,
        string $description,
        string $error,
        array $metadata = []
    ): OrderHistory {
        return $this->recordWorkerEvent(
            $order,
            $eventType,
            $workerName,
            $description,
            array_merge($metadata, [
                'success' => false,
                'error' => $error,
            ])
        );
    }

    /**
     * Record a worker attempt event
     */
    public function recordWorkerAttempt(
        OrderSummary $order,
        string $workerName,
        string $workerType,
        int $attempt = 1,
        array $metadata = []
    ): OrderHistory {
        return $this->recordWorkerEvent(
            $order,
            OrderHistory::EVENT_WORKER_ATTEMPT,
            $workerName,
            "Iniciando processamento: {$workerType}",
            array_merge($metadata, [
                'worker_type' => $workerType,
                'attempt' => $attempt,
            ])
        );
    }

    /**
     * Record order import event
     */
    public function recordOrderImported(OrderSummary $order, string $source, array $metadata = [], string $workerName = null): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_ORDER_IMPORTED,
            "Pedido importado do {$source}",
            array_merge($metadata, ['source' => $source]),
            null,
            $workerName
        );
    }

    /**
     * Record order sync completed (updates)
     */
    public function recordOrderSyncCompleted(OrderSummary $order, array $changes, array $metadata = []): OrderHistory
    {
        $changesList = implode(', ', $changes);
        return $this->recordWorkerSuccess(
            $order,
            OrderHistory::EVENT_ORDER_UPDATED,
            'Orion Worker (Order)',
            "Pedido atualizado: {$changesList}",
            array_merge($metadata, ['changes' => $changes])
        );
    }

    /**
     * Record status change event
     */
    public function recordStatusChange(
        OrderSummary $order,
        string $statusType,
        bool $statusValue,
        User $user = null
    ): OrderHistory {
        $statusNames = [
            'picking' => 'Separado',
            'packing' => 'Embalado',
            'picked_up' => 'Coletado',
        ];

        $statusName = $statusNames[$statusType] ?? $statusType;
        $action = $statusValue ? 'marcado como concluído' : 'desmarcado';
        $description = "Status \"{$statusName}\" {$action}";

        return $this->recordEvent(
            $order,
            "status_{$statusType}",
            $description,
            [
                'status_type' => $statusType,
                'status' => $statusValue,
                'status_name' => $statusName,
            ],
            $user
        );
    }

    /**
     * Record invoice sync event
     */
    public function recordInvoiceSynced(OrderSummary $order, array $metadata = [], string $workerName = null): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_INVOICE_SYNCED,
            'NFE sincronizada com Bling',
            array_merge($metadata, ['sync_type' => 'invoice']),
            null,
            $workerName ?? 'Andromeda Worker (Invoice)'
        );
    }

    /**
     * Record tracking fetch event
     */
    public function recordTrackingFetched(OrderSummary $order, array $metadata = [], string $workerName = null): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_TRACKING_FETCHED,
            'Dados de rastreamento obtidos',
            array_merge($metadata, ['sync_type' => 'tracking']),
            null,
            $workerName ?? 'Nova Worker (Tracking)'
        );
    }

    /**
     * Record seller sync event
     */
    public function recordSellerSynced(OrderSummary $order, array $metadata = []): OrderHistory
    {
        return $this->recordWorkerSuccess(
            $order,
            OrderHistory::EVENT_SELLER_SYNCED,
            'Quasar Worker (Seller)',
            'Vendedor sincronizado com sucesso',
            array_merge($metadata, ['sync_type' => 'seller'])
        );
    }

    /**
     * Record note added event
     */
    public function recordNoteAdded(OrderSummary $order, string $note, User $user = null): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_NOTE_ADDED,
            'Nota adicionada ao pedido',
            ['note' => $note],
            $user
        );
    }

    /**
     * Record tracking update event
     */
    public function recordTrackingUpdated(OrderSummary $order, array $metadata = []): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_TRACKING_UPDATED,
            'Rastreamento atualizado',
            array_merge($metadata, ['sync_type' => 'tracking_update'])
        );
    }

    /**
     * Record manual sync event
     */
    public function recordManualSync(OrderSummary $order, string $syncType, User $user = null): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_MANUAL_SYNC,
            "Sincronização manual executada ({$syncType})",
            ['sync_type' => $syncType],
            $user
        );
    }

    /**
     * Record order update event
     */
    public function recordOrderUpdated(OrderSummary $order, array $changes, User $user = null): OrderHistory
    {
        return $this->recordEvent(
            $order,
            OrderHistory::EVENT_ORDER_UPDATED,
            'Pedido atualizado',
            ['changes' => $changes],
            $user
        );
    }

    /**
     * Get order history with formatted data
     */
    public function getOrderHistory(OrderSummary $order, int $limit = 50): \Illuminate\Support\Collection
    {
        return $order->getAllOrderHistory()
            ->with('user')
            ->recent()
            ->limit($limit)
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'event_type' => $history->event_type,
                    'event_description' => $history->event_description,
                    'formatted_description' => $history->formatted_description,
                    'event_icon' => $history->event_icon,
                    'event_color' => $history->event_color,
                    'user_name' => $history->user?->name ?? ($history->worker_name ?? 'Sistema'),
                    'user_username' => $history->user?->username ?? 'system',
                    'worker_name' => $history->worker_name,
                    'worker_display' => $history->worker_display,
                    'metadata' => $history->metadata,
                    'created_at' => $history->created_at,
                    'formatted_created_at' => $history->created_at->format('d/m/Y H:i:s'),
                ];
            });
    }

    /**
     * Get order history by event type
     */
    public function getOrderHistoryByEventType(OrderSummary $order, string $eventType): \Illuminate\Database\Eloquent\Collection
    {
        return $order->getAllOrderHistory()
            ->with('user')
            ->byEventType($eventType)
            ->recent()
            ->get();
    }

    /**
     * Get order history by date range
     */
    public function getOrderHistoryByDateRange(
        OrderSummary $order,
        \Carbon\Carbon $startDate,
        \Carbon\Carbon $endDate
    ): \Illuminate\Database\Eloquent\Collection {
        return $order->getAllOrderHistory()
            ->with('user')
            ->byDateRange($startDate, $endDate)
            ->recent()
            ->get();
    }
}
