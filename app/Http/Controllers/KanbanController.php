<?php

namespace App\Http\Controllers;

use App\Models\OrderSummary;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class KanbanController extends Controller
{
    /**
     * Display the kanban board.
     */
    public function index(): Response
    {
        $orders = OrderSummary::with([
            'orderStatus' => function($query) {
                $query->with(['pickingMarkedBy', 'packingMarkedBy', 'pickedUpMarkedBy']);
            }
        ])
            ->orderBy('data', 'desc')
            ->get();

        $kanbanData = $this->organizeOrdersByStatus($orders);

        // Debug: find orders with movement history
        $ordersWithHistory = $orders->filter(function($order) {
            return $order->orderStatus && (
                $order->orderStatus->picking_marked_by ||
                $order->orderStatus->packing_marked_by ||
                $order->orderStatus->picked_up_marked_by
            );
        });

        Log::info('Orders with movement history found:', [
            'total_orders' => $orders->count(),
            'orders_with_history' => $ordersWithHistory->count(),
        ]);

        if ($ordersWithHistory->count() > 0) {
            $sampleOrder = $ordersWithHistory->first();
            Log::info('Sample order WITH history:', [
                'order_id' => $sampleOrder->id,
                'order_numero' => $sampleOrder->numero,
                'picking_marked_by' => $sampleOrder->orderStatus?->pickingMarkedBy?->name,
                'picking_marked_by_id' => $sampleOrder->orderStatus?->picking_marked_by,
                'picking_marked_at' => $sampleOrder->orderStatus?->picking_marked_at,
                'packing_marked_by' => $sampleOrder->orderStatus?->packingMarkedBy?->name,
                'packing_marked_by_id' => $sampleOrder->orderStatus?->packing_marked_by,
                'packing_marked_at' => $sampleOrder->orderStatus?->packing_marked_at,
                'picked_up_marked_by' => $sampleOrder->orderStatus?->pickedUpMarkedBy?->name,
                'picked_up_marked_by_id' => $sampleOrder->orderStatus?->picked_up_marked_by,
                'picked_up_marked_at' => $sampleOrder->orderStatus?->picked_up_marked_at,
            ]);
        } else {
            Log::info('No orders found with movement history');
        }

        return Inertia::render('Kanban/Index', [
            'kanbanData' => $kanbanData,
            'stats' => $this->getStats($orders),
        ]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, OrderSummary $order)
    {
        $request->validate([
            'status' => 'required|in:picking,packing,picked_up,completed',
        ]);

        $status = $request->status;
        $user = $request->user();

        // Get or create order status
        $orderStatus = $order->orderStatus ?? OrderStatus::create([
            'order_summary_id' => $order->id,
        ]);

        // Update status based on action
        switch ($status) {
            case 'picking':
                $orderStatus->markPicking($user);
                break;
            case 'packing':
                $orderStatus->markPacking($user);
                break;
            case 'picked_up':
                $orderStatus->markPickedUp($user);
                break;
            case 'completed':
                // Mark all as completed
                $orderStatus->markPicking($user);
                $orderStatus->markPacking($user);
                $orderStatus->markPickedUp($user);
                break;
        }


        return response()->json([
            'success' => true,
            'message' => 'Status atualizado com sucesso',
        ]);
    }

    /**
     * Organize orders by status for kanban display.
     */
    private function organizeOrdersByStatus($orders): array
    {
        $kanbanData = [
            'aguardando' => [],
            'separado' => [],
            'embalado' => [],
            'coletado' => [],
            'sucesso' => [],
        ];

        foreach ($orders as $order) {
            $status = $this->getOrderKanbanStatus($order);
            $kanbanData[$status][] = [
                'id' => $order->id,
                'bling_id' => $order->bling_id,
                'numero' => $order->numero,
                'contato_nome' => $order->contato_nome,
                'total' => $order->total,
                'data' => $order->data ? $order->data->format('d/m/Y H:i') : null,
                'source' => $order->source,
                'order_status' => $order->orderStatus ? [
                    'picking' => $order->orderStatus->picking,
                    'packing' => $order->orderStatus->packing,
                    'picked_up' => $order->orderStatus->picked_up,
                    'picking_marked_by' => $order->orderStatus->pickingMarkedBy?->name,
                    'picking_marked_at' => $order->orderStatus->picking_marked_at?->toISOString(),
                    'packing_marked_by' => $order->orderStatus->packingMarkedBy?->name,
                    'packing_marked_at' => $order->orderStatus->packing_marked_at?->toISOString(),
                    'picked_up_marked_by' => $order->orderStatus->pickedUpMarkedBy?->name,
                    'picked_up_marked_at' => $order->orderStatus->picked_up_marked_at?->toISOString(),
                ] : null,
            ];
        }

        return $kanbanData;
    }

    /**
     * Get the kanban status for an order.
     */
    private function getOrderKanbanStatus(OrderSummary $order): string
    {
        $orderStatus = $order->orderStatus;

        if (!$orderStatus) {
            return 'aguardando';
        }

        if ($orderStatus->isCompleted()) {
            return 'sucesso';
        }

        if ($orderStatus->picked_up) {
            return 'coletado';
        }

        if ($orderStatus->packing) {
            return 'embalado';
        }

        if ($orderStatus->picking) {
            return 'separado';
        }

        return 'aguardando';
    }

    /**
     * Get statistics for the dashboard.
     */
    private function getStats($orders): array
    {
        $stats = [
            'total' => $orders->count(),
            'aguardando' => 0,
            'separado' => 0,
            'embalado' => 0,
            'coletado' => 0,
            'sucesso' => 0,
        ];

        foreach ($orders as $order) {
            $status = $this->getOrderKanbanStatus($order);
            $stats[$status]++;
        }

        return $stats;
    }
}
