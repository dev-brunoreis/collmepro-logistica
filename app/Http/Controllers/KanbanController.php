<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
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
            },
            'invoice',
            'orderData'
        ])
            ->orderBy('data', 'desc')
            ->get();

        $kanbanData = $this->organizeOrdersByStatus($orders);
        
        // Add fake data for testing
        $kanbanData = $this->addFakeData($kanbanData);

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
            
            $orderData = [
                'id' => $order->id,
                'bling_id' => $order->bling_id,
                'numero' => $order->numero,
                'contato_nome' => $order->contato_nome,
                'total' => $order->total,
                'data' => $order->data ? $order->data->format('d/m/Y H:i') : null,
                'source' => $order->source,
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

        // If no status, it's a new order - goes to pedidos_emitidos
        if (!$orderStatus) {
            return 'pedidos_emitidos';
        }

        // If picked up, goes to coleta_realizada
        if ($orderStatus->picked_up) {
            return 'coleta_realizada';
        }

        // If packing is done but not picked up, goes to coleta_aguardando
        if ($orderStatus->packing) {
            return 'coleta_aguardando';
        }

        // If picking is done but not packing, goes to embalagem
        if ($orderStatus->picking) {
            return 'embalagem';
        }

        // If has invoice but no status, goes to faturamento
        if ($order->invoice && $order->invoice->numero) {
            return 'faturamento';
        }

        // If has tracking code but no status, goes to etiqueta
        if ($order->invoice && $order->invoice->tracking) {
            return 'etiqueta';
        }

        // Default to pedidos_emitidos
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

    /**
     * Add fake data for testing the Kanban board.
     */
    private function addFakeData(array $kanbanData): array
    {
        // Fake data for Orders Issued - Collme Pro
        $kanbanData['pedidos_emitidos'][] = [
            'id' => 9991,
            'bling_id' => 1052,
            'numero' => '1052',
            'contato_nome' => 'Maria Silva',
            'total' => 89.90,
            'data' => '15/01/2025 14:30',
            'source' => 'Collme Pro',
            'invoice_number' => null,
            'items_quantity' => 2,
            'tracking_code' => null,
            'order_status' => null,
        ];

        // Fake data for Orders Issued - Collme Beauty
        $kanbanData['pedidos_emitidos'][] = [
            'id' => 9992,
            'bling_id' => 126,
            'numero' => '126',
            'contato_nome' => 'Ana Costa',
            'total' => 156.80,
            'data' => '15/01/2025 15:45',
            'source' => 'Collme Beauty',
            'invoice_number' => null,
            'items_quantity' => 3,
            'tracking_code' => null,
            'order_status' => null,
        ];

        $kanbanData['pedidos_emitidos'][] = [
            'id' => 9993,
            'bling_id' => 127,
            'numero' => '127',
            'contato_nome' => 'João Santos',
            'total' => 234.50,
            'data' => '15/01/2025 16:20',
            'source' => 'Collme Beauty',
            'invoice_number' => null,
            'items_quantity' => 1,
            'tracking_code' => null,
            'order_status' => null,
        ];

        $kanbanData['pedidos_emitidos'][] = [
            'id' => 9994,
            'bling_id' => 128,
            'numero' => '128',
            'contato_nome' => 'Carla Oliveira',
            'total' => 78.90,
            'data' => '15/01/2025 17:10',
            'source' => 'Collme Beauty',
            'invoice_number' => null,
            'items_quantity' => 2,
            'tracking_code' => null,
            'order_status' => null,
        ];

        // Fake data for Invoicing / NF Issuance
        $kanbanData['faturamento'][] = [
            'id' => 9995,
            'bling_id' => 124,
            'numero' => '124',
            'contato_nome' => 'Pedro Lima',
            'total' => 198.70,
            'data' => '15/01/2025 13:15',
            'source' => 'Collme Beauty',
            'invoice_number' => 'NF-2025-001',
            'items_quantity' => 4,
            'tracking_code' => null,
            'order_status' => null,
        ];

        $kanbanData['faturamento'][] = [
            'id' => 9996,
            'bling_id' => 1054,
            'numero' => '1054',
            'contato_nome' => 'Lucia Ferreira',
            'total' => 312.40,
            'data' => '15/01/2025 14:00',
            'source' => 'Collme Pro',
            'invoice_number' => 'NF-2025-002',
            'items_quantity' => 2,
            'tracking_code' => null,
            'order_status' => null,
        ];

        // Fake data for Checkout (Picking)
        $kanbanData['checkout'][] = [
            'id' => 9997,
            'bling_id' => 1051,
            'numero' => '1051',
            'contato_nome' => 'Roberto Alves',
            'total' => 145.60,
            'data' => '15/01/2025 12:30',
            'source' => 'Collme Pro',
            'invoice_number' => 'NF-2025-003',
            'items_quantity' => 3,
            'tracking_code' => null,
            'order_status' => [
                'picking' => true,
                'packing' => false,
                'picked_up' => false,
                'picking_marked_by' => 'John Doe',
                'picking_marked_at' => '2025-01-15T12:30:00Z',
            ],
        ];

        // Fake data for Collection - Completed
        $kanbanData['coleta_realizada'][] = [
            'id' => 9998,
            'bling_id' => 1053,
            'numero' => '1053',
            'contato_nome' => 'Fernanda Rocha',
            'total' => 267.80,
            'data' => '15/01/2025 11:45',
            'source' => 'Collme Pro',
            'invoice_number' => 'NF-2025-004',
            'items_quantity' => 2,
            'tracking_code' => 'BR123456789',
            'order_status' => [
                'picking' => true,
                'packing' => true,
                'picked_up' => true,
                'picked_up_marked_by' => 'Jane Smith',
                'picked_up_marked_at' => '2025-01-15T11:45:00Z',
            ],
        ];

        $kanbanData['coleta_realizada'][] = [
            'id' => 9999,
            'bling_id' => 125,
            'numero' => '125',
            'contato_nome' => 'Marcos Pereira',
            'total' => 189.30,
            'data' => '15/01/2025 10:20',
            'source' => 'Collme Beauty',
            'invoice_number' => 'NF-2025-005',
            'items_quantity' => 1,
            'tracking_code' => 'BR987654321',
            'order_status' => [
                'picking' => true,
                'packing' => true,
                'picked_up' => true,
                'picked_up_marked_by' => 'Mike Johnson',
                'picked_up_marked_at' => '2025-01-15T10:20:00Z',
            ],
        ];

        return $kanbanData;
    }
}
