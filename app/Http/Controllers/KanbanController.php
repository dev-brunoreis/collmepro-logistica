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
     * Display the kanban board (empty initial state for async loading).
     */
    public function index(): Response
    {
        // Return empty kanban data for instant page load
        // Orders will be fetched asynchronously via fetchOrders()
        $emptyKanbanData = [
            'pedidos_emitidos' => [],
            'faturamento' => [],
            'checkout' => [],
            'embalagem' => [],
            'etiqueta' => [],
            'coleta_aguardando' => [],
            'coleta_realizada' => [],
        ];

        $emptyStats = [
            'total' => 0,
            'pedidos_emitidos' => 0,
            'faturamento' => 0,
            'checkout' => 0,
            'embalagem' => 0,
            'etiqueta' => 0,
            'coleta_aguardando' => 0,
            'coleta_realizada' => 0,
        ];

        return Inertia::render('Kanban/Index', [
            'kanbanData' => $emptyKanbanData,
            'stats' => $emptyStats,
        ]);
    }

    /**
     * Fetch orders asynchronously for kanban board.
     */
    public function fetchOrders(Request $request)
    {
        $daysBack = $request->input('days_back', 30); // Default: last 30 days
        $loadAll = $request->boolean('load_all', false); // Option to load all orders

        // Build query
        $query = OrderSummary::with([
            'orderStatus' => function($query) {
                $query->with(['pickingMarkedBy', 'packingMarkedBy', 'pickedUpMarkedBy']);
            },
            'invoice',
            'orderData'
        ]);

        // Apply date filter unless loading all
        if (!$loadAll && $daysBack > 0) {
            $query->where('data', '>=', now()->subDays($daysBack));
        }

        $orders = $query->orderBy('data', 'desc')->get();

        // Get total count for all orders (for progress tracking)
        $totalOrdersCount = OrderSummary::count();

        // Organize orders by status
        $kanbanData = $this->organizeOrdersByStatus($orders);

        // Calculate stats
        $stats = $this->getStats($orders);
        $stats['total_all'] = $totalOrdersCount;
        $stats['loaded'] = $orders->count();

        return response()->json([
            'kanbanData' => $kanbanData,
            'stats' => $stats,
        ]);
    }

    /**
     * Update order status.
     */
    public function updateStatus(Request $request, int $orderSummary)
    {
        $request->validate([
            'status' => 'required|in:picking,packing,picked_up,completed',
        ]);

        $status = $request->status;
        $user = $request->user();

        // Find the order
        $order = OrderSummary::with(['orderStatus', 'invoice'])->findOrFail($orderSummary);

        // Get or create order status
        $orderStatus = $order->orderStatus;
        if (!$orderStatus) {
            $orderStatus = $order->orderStatus()->create([
                'picking' => false,
                'packing' => false,
                'picked_up' => false,
            ]);
        }

        // Check if order has invoice
        $hasInvoice = false;
        if ($order->invoice && $order->invoice->numero) {
            $hasInvoice = true;
        } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['numero'])) {
            $hasInvoice = true;
        }

        // Validate sequential flow rules and determine action
        switch ($status) {
            case 'picking':
                $newValue = !$orderStatus->picking; // Toggle
                
                if ($newValue) {
                    // Validation: Cannot mark picking without invoice
                    if (!$hasInvoice) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Não é possível marcar picking sem nota fiscal. O pedido deve estar faturado primeiro.',
                        ], 422);
                    }
                } else {
                    // Validation: Cannot unmark picking if packing is true
                    if ($orderStatus->packing) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Não é possível desmarcar picking enquanto packing estiver marcado.',
                        ], 422);
                    }
                }
                break;

            case 'packing':
                $newValue = !$orderStatus->packing; // Toggle
                
                if ($newValue) {
                    // Validation: Cannot mark packing without picking
                    if (!$orderStatus->picking) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Não é possível marcar packing sem ter picking marcado primeiro.',
                        ], 422);
                    }
                } else {
                    // Validation: Cannot unmark packing if picked_up is true
                    if ($orderStatus->picked_up) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Não é possível desmarcar packing enquanto picked_up estiver marcado.',
                        ], 422);
                    }
                }
                break;

            case 'picked_up':
                $newValue = !$orderStatus->picked_up; // Toggle
                
                if ($newValue) {
                    // Validation: Cannot mark picked_up without packing
                    if (!$orderStatus->packing) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Não é possível marcar picked_up sem ter packing marcado primeiro.',
                        ], 422);
                    }
                    
                    // Validation: Cannot mark picked_up without tracking code
                    $hasTracking = false;
                    if ($order->tracking_code && $order->tracking_code !== '') {
                        $hasTracking = true;
                    } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
                        $hasTracking = true;
                    } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['tracking']) && !empty($order->raw_invoice_data['tracking'])) {
                        $hasTracking = true;
                    }
                    
                    if (!$hasTracking) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Não é possível marcar como coletado sem código de rastreamento.',
                        ], 422);
                    }
                }
                // No validation needed for unmarking picked_up
                break;

            case 'completed':
                // Validation: Must have invoice for completed status
                if (!$hasInvoice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Não é possível completar pedido sem nota fiscal.',
                    ], 422);
                }
                $newValue = true; // Completed always marks everything as true
                break;
        }

        // Update status based on action
        switch ($status) {
            case 'picking':
                if ($newValue) {
                    $orderStatus->markPicking($user);
                } else {
                    $orderStatus->unmarkPicking($user);
                }
                broadcast(new OrderStatusUpdated($order, $user, 'picking', $newValue));
                break;
                
            case 'packing':
                if ($newValue) {
                    $orderStatus->markPacking($user);
                } else {
                    $orderStatus->unmarkPacking($user);
                }
                broadcast(new OrderStatusUpdated($order, $user, 'packing', $newValue));
                break;
                
            case 'picked_up':
                if ($newValue) {
                    $orderStatus->markPickedUp($user);
                } else {
                    $orderStatus->unmarkPickedUp($user);
                }
                broadcast(new OrderStatusUpdated($order, $user, 'picked_up', $newValue));
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
            if ($order->tracking_code && $order->tracking_code !== '') {
                $trackingCode = $order->tracking_code;
            } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
                $firstTracking = $order->invoice->tracking()->first();
                $trackingCode = $firstTracking ? $firstTracking->codigo_rastreamento : null;
            } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['tracking']) && !empty($order->raw_invoice_data['tracking'])) {
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
     * 
     * Priority order:
     * 1. Coleta Realizada: picked_up=true
     * 2. Coleta Aguardando: invoice + picking + packing + tracking_code + !picked_up
     * 3. Etiqueta: invoice + picking + packing + !tracking_code
     * 4. Embalagem: invoice + picking + !packing
     * 5. Checkout: invoice + !picking
     * 6. Faturamento: !invoice
     * 7. Pedidos Emitidos: default
     */
    private function getOrderKanbanStatus(OrderSummary $order): string
    {
        $orderStatus = $order->orderStatus;

        // Check for tracking code (from order_summaries.tracking_code or invoice/raw data)
        $hasTracking = false;
        if ($order->tracking_code && $order->tracking_code !== '') {
            $hasTracking = true;
        } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
            $hasTracking = true;
        } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['tracking']) && !empty($order->raw_invoice_data['tracking'])) {
            $hasTracking = true;
        }

        // Check for invoice number (from invoice table or raw data)
        $hasInvoice = false;
        if ($order->invoice && $order->invoice->numero) {
            $hasInvoice = true;
        } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['numero'])) {
            $hasInvoice = true;
        }

        // 1. Coleta Realizada: picked_up=true
        if ($orderStatus && $orderStatus->picked_up) {
            return 'coleta_realizada';
        }

        // 2. Coleta Aguardando: invoice + picking + packing + tracking_code + !picked_up
        if ($hasInvoice && $orderStatus && $orderStatus->picking && $orderStatus->packing && $hasTracking) {
            return 'coleta_aguardando';
        }

        // 3. Etiqueta: invoice + picking + packing + !tracking_code
        if ($hasInvoice && $orderStatus && $orderStatus->picking && $orderStatus->packing && !$hasTracking) {
            return 'etiqueta';
        }

        // 4. Embalagem: invoice + picking + !packing
        if ($hasInvoice && $orderStatus && $orderStatus->picking && !$orderStatus->packing) {
            return 'embalagem';
        }

        // 5. Checkout: invoice + !picking
        if ($hasInvoice && (!$orderStatus || !$orderStatus->picking)) {
            return 'checkout';
        }

        // 6. Faturamento: !invoice
        if (!$hasInvoice) {
            return 'faturamento';
        }

        // 7. Pedidos Emitidos: default (shouldn't reach here normally)
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
