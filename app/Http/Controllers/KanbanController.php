<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Models\OrderSummary;
use App\Models\OrderStatus;
use App\Models\User;
use App\Services\SystemMappingService;
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
            },
            'invoice',
            'orderData'
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
                broadcast(new OrderStatusUpdated($order, $user, 'picking', true));
                break;
            case 'packing':
                $orderStatus->markPacking($user);
                broadcast(new OrderStatusUpdated($order, $user, 'packing', true));
                break;
            case 'picked_up':
                $orderStatus->markPickedUp($user);
                broadcast(new OrderStatusUpdated($order, $user, 'picked_up', true));
                break;
            case 'completed':
                // Mark all as completed
                $orderStatus->markPicking($user);
                $orderStatus->markPacking($user);
                $orderStatus->markPickedUp($user);
                // Broadcast each status update
                broadcast(new OrderStatusUpdated($order, $user, 'picking', true));
                broadcast(new OrderStatusUpdated($order, $user, 'packing', true));
                broadcast(new OrderStatusUpdated($order, $user, 'picked_up', true));
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
            'pedidos_emitidos' => [],
            'faturamento' => [],
            'checkout' => [],
            'embalagem' => [],
            'etiqueta' => [],
            'coleta_aguardando' => [],
            'coleta_realizada' => [],
        ];

        foreach ($orders as $order) {
            $status = $this->getOrderKanbanStatus($order);
            
            // Get invoice number
            $invoiceNumber = null;
            if ($order->invoice && $order->invoice->numero) {
                $invoiceNumber = $order->invoice->numero;
            } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['numero'])) {
                $invoiceNumber = $order->raw_invoice_data['numero'];
            }
            
            // Get items quantity
            $itemsQuantity = 0;
            if ($order->orderData && $order->orderData->itens) {
                $itemsQuantity = count($order->orderData->itens);
            } elseif ($order->raw_order_data && isset($order->raw_order_data['itens'])) {
                $itemsQuantity = count($order->raw_order_data['itens']);
            } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['itens'])) {
                $itemsQuantity = count($order->raw_invoice_data['itens']);
            }

            // Get tracking code
            $trackingCode = null;
            if ($order->invoice && $order->invoice->tracking) {
                $trackingCode = $order->invoice->tracking;
            } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['tracking'])) {
                $trackingCode = $order->raw_invoice_data['tracking'];
            }
            
            // Source is already mapped correctly from SystemMappingService
            $source = $order->source;
            
            $orderData = [
                'id' => $order->id,
                'bling_id' => $order->bling_id,
                'numero' => $order->numero,
                'contato_nome' => $order->contato_nome,
                'total' => $order->total,
                'data' => $order->data ? $order->data->format('d/m/Y H:i') : null,
                'source' => $source,
                'invoice_number' => $invoiceNumber,
                'items_quantity' => $itemsQuantity,
                'tracking_code' => $trackingCode,
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

            $kanbanData[$status][] = $orderData;
        }

        return $kanbanData;
    }

    /**
     * Get the kanban status for an order.
     */
    private function getOrderKanbanStatus(OrderSummary $order): string
    {
        $orderStatus = $order->orderStatus;

        // If picked up, goes to coleta_realizada
        if ($orderStatus && $orderStatus->picked_up) {
            return 'coleta_realizada';
        }

        // If packing is done but not picked up, goes to coleta_aguardando
        if ($orderStatus && $orderStatus->packing && !$orderStatus->picked_up) {
            return 'coleta_aguardando';
        }

        // If picking is done but not packing, goes to embalagem
        if ($orderStatus && $orderStatus->picking && !$orderStatus->packing) {
            return 'embalagem';
        }

        // If picking is done but no packing status, goes to checkout
        if ($orderStatus && $orderStatus->picking) {
            return 'checkout';
        }

        // Check for invoice number (from invoice table or raw data)
        $hasInvoice = false;
        if ($order->invoice && $order->invoice->numero) {
            $hasInvoice = true;
        } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['numero'])) {
            $hasInvoice = true;
        }

        // If has invoice but no picking status, goes to faturamento
        if ($hasInvoice && (!$orderStatus || !$orderStatus->picking)) {
            return 'faturamento';
        }

        // Check for tracking code (from invoice table or raw data)
        $hasTracking = false;
        if ($order->invoice && $order->invoice->tracking) {
            $hasTracking = true;
        } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['tracking'])) {
            $hasTracking = true;
        }

        // If has tracking code but no picking status, goes to etiqueta
        if ($hasTracking && (!$orderStatus || !$orderStatus->picking)) {
            return 'etiqueta';
        }

        // Default to pedidos_emitidos for new orders
        return 'pedidos_emitidos';
    }

    /**
     * Get statistics for the dashboard.
     */
    private function getStats($orders): array
    {
        $stats = [
            'total' => $orders->count(),
            'pedidos_emitidos' => 0,
            'faturamento' => 0,
            'checkout' => 0,
            'embalagem' => 0,
            'etiqueta' => 0,
            'coleta_aguardando' => 0,
            'coleta_realizada' => 0,
        ];

        foreach ($orders as $order) {
            $status = $this->getOrderKanbanStatus($order);
            $stats[$status]++;
        }

        return $stats;
    }

}
