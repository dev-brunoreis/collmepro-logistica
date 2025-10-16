<?php

namespace App\Http\Controllers;

use App\Models\OrderSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard (empty initial state for async loading).
     */
    public function index(): Response
    {
        // Return empty initial state for instant page load
        // Data will be fetched asynchronously via fetchDashboardData()
        $emptyMetrics = [
            'total_orders' => 0,
            'completed_orders' => 0,
        ];

        $emptyOrdersByStatus = [
            'pending' => [],
            'picking' => [],
            'packing' => [],
            'picked_up' => [],
            'completed' => [],
        ];

        $emptyKanbanData = [
            'pedidos_emitidos' => [],
            'faturamento' => [],
            'checkout' => [],
            'embalagem' => [],
            'etiqueta' => [],
            'coleta_aguardando' => [],
            'coleta_realizada' => [],
        ];

        return Inertia::render('Dashboard', [
            'metrics' => $emptyMetrics,
            'ordersByStatus' => $emptyOrdersByStatus,
            'recentActivity' => [],
            'kanbanData' => $emptyKanbanData,
        ]);
    }

    /**
     * Fetch dashboard data asynchronously.
     */
    public function fetchDashboardData(Request $request)
    {
        $daysBack = $request->input('days_back', 7); // Default: last 7 days
        $loadAll = $request->boolean('load_all', false); // Option to load all orders

        // Build query
        $query = OrderSummary::with([
            'orderStatus' => function($query) {
                $query->with(['pickingMarkedBy', 'packingMarkedBy', 'pickedUpMarkedBy']);
            },
            'invoice',
            'orderData'
        ])
        ->notCancelled(); // Filter out cancelled orders

        // Apply date filter unless loading all
        if (!$loadAll && $daysBack > 0) {
            $query->where('data', '>=', now()->subDays($daysBack));
        }

        $orders = $query->orderBy('data', 'desc')->get();

        // Get total count for all orders (for progress tracking)
        $totalOrdersCount = OrderSummary::count();

        // Calculate metrics
        $metrics = $this->calculateMetrics($orders);
        $metrics['total_all'] = $totalOrdersCount;
        $metrics['loaded'] = $orders->count();

        // Get orders by status
        $ordersByStatus = $this->getOrdersByStatus($orders);

        // Get recent activity
        $recentActivity = $this->getRecentActivity($orders);

        // Get Kanban data (using new structure)
        $kanbanData = $this->getKanbanDataNew($orders);

        return response()->json([
            'metrics' => $metrics,
            'ordersByStatus' => $ordersByStatus,
            'recentActivity' => $recentActivity,
            'kanbanData' => $kanbanData,
        ]);
    }

    /**
     * Calculate dashboard metrics (simplified for 2 cards).
     */
    private function calculateMetrics($orders): array
    {
        $total = $orders->count();
        
        // Count completed orders (picked_up = true)
        $completed = $orders->filter(function($order) {
            return $order->orderStatus && $order->orderStatus->picked_up;
        })->count();

        return [
            'total_orders' => $total,
            'completed_orders' => $completed,
        ];
    }

    /**
     * Get orders organized by status.
     */
    private function getOrdersByStatus($orders): array
    {
        return [
            'pending' => $orders->filter(fn($order) => !$order->orderStatus)->values(),
            'picking' => $orders->filter(fn($order) => 
                $order->orderStatus?->picking && 
                !$order->orderStatus?->packing
            )->values(),
            'packing' => $orders->filter(fn($order) => 
                $order->orderStatus?->packing && 
                !$order->orderStatus?->picked_up
            )->values(),
            'picked_up' => $orders->filter(fn($order) => 
                $order->orderStatus?->picked_up && 
                !$order->orderStatus?->isCompleted()
            )->values(),
            'completed' => $orders->filter(fn($order) => 
                $order->orderStatus?->isCompleted()
            )->values(),
        ];
    }

    /**
     * Get recent activity (orders with status changes).
     */
    private function getRecentActivity($orders): array
    {
        return $orders->filter(function($order) {
            return $order->orderStatus && (
                $order->orderStatus->picking_marked_by ||
                $order->orderStatus->packing_marked_by ||
                $order->orderStatus->picked_up_marked_by
            );
        })->map(function($order) {
            $status = $order->orderStatus;
            $lastAction = null;
            $lastUser = null;
            $lastTime = null;

            // Find the most recent action
            if ($status->picked_up_marked_at) {
                $lastAction = 'picked_up';
                $lastUser = $status->picked_up_marked_by;
                $lastTime = $status->picked_up_marked_at;
            } elseif ($status->packing_marked_at) {
                $lastAction = 'packing';
                $lastUser = $status->packing_marked_by;
                $lastTime = $status->packing_marked_at;
            } elseif ($status->picking_marked_at) {
                $lastAction = 'picking';
                $lastUser = $status->picking_marked_by;
                $lastTime = $status->picking_marked_at;
            }

            return [
                'id' => $order->id,
                'numero' => $order->numero,
                'contato_nome' => $order->contato_nome,
                'last_action' => $lastAction,
                'last_user' => $lastUser,
                'last_time' => $lastTime,
                'status' => $status,
            ];
        })->sortByDesc('last_time')->take(10)->values()->toArray();
    }

    /**
     * Get Kanban data organized by status (same structure as KanbanController).
     */
    private function getKanbanData($orders): array
    {
        $kanbanData = [
            'aguardando' => [],
            'separado' => [],
            'embalado' => [],
            'coletado' => [],
        ];

        foreach ($orders as $order) {
            $status = $this->getOrderKanbanStatus($order);
            
            // Get invoice number
            $invoiceNumber = null;
            if ($order->invoice && $order->invoice->numero) {
                $invoiceNumber = $order->invoice->numero;
            }
            
            // Get items quantity
            $itemsQuantity = 0;
            if ($order->orderData && $order->orderData->itens) {
                $itemsQuantity = count($order->orderData->itens);
            } elseif ($order->raw_order_data && isset($order->raw_order_data['itens'])) {
                $itemsQuantity = count($order->raw_order_data['itens']);
            } elseif ($order->invoice && $order->invoice->itens) {
                $itemsQuantity = count($order->invoice->itens);
            }
            
            $kanbanData[$status][] = [
                'id' => $order->id,
                'bling_id' => $order->bling_id,
                'numero' => $order->numero,
                'contato_nome' => $order->contato_nome,
                'total' => $order->total,
                'data' => $order->data ? $order->data->format('d/m/Y H:i') : null,
                'source' => $order->source,
                'invoice_number' => $invoiceNumber,
                'items_quantity' => $itemsQuantity,
                'order_status' => $order->orderStatus ? [
                    'picking' => $order->orderStatus->picking,
                    'packing' => $order->orderStatus->packing,
                    'picked_up' => $order->orderStatus->picked_up,
                    'picking_marked_by' => $order->orderStatus->pickingMarkedBy?->name ?? ($order->orderStatus->picking_marked_by ? 'Usuário ID: ' . $order->orderStatus->picking_marked_by : null),
                    'picking_marked_at' => $order->orderStatus->picking_marked_at?->toISOString(),
                    'packing_marked_by' => $order->orderStatus->packingMarkedBy?->name ?? ($order->orderStatus->packing_marked_by ? 'Usuário ID: ' . $order->orderStatus->packing_marked_by : null),
                    'packing_marked_at' => $order->orderStatus->packing_marked_at?->toISOString(),
                    'picked_up_marked_by' => $order->orderStatus->pickedUpMarkedBy?->name ?? ($order->orderStatus->picked_up_marked_by ? 'Usuário ID: ' . $order->orderStatus->picked_up_marked_by : null),
                    'picked_up_marked_at' => $order->orderStatus->picked_up_marked_at?->toISOString(),
                ] : null,
            ];
        }

        return $kanbanData;
    }

    /**
     * Get the kanban status for an order (same logic as KanbanController).
     */
    private function getOrderKanbanStatus($order): string
    {
        $orderStatus = $order->orderStatus;

        if (!$orderStatus) {
            return 'aguardando';
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
     * Get Kanban data organized by new status structure (pedidos_emitidos, faturamento, etc).
     */
    private function getKanbanDataNew($orders): array
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
            $status = $this->getOrderKanbanStatusNew($order);
            
            // Get invoice number
            $invoiceNumber = null;
            if ($order->invoice && $order->invoice->numero) {
                $invoiceNumber = $order->invoice->numero;
            }
            
            // Get items quantity
            $itemsQuantity = 0;
            if ($order->orderData && $order->orderData->itens) {
                $itemsQuantity = count($order->orderData->itens);
            } elseif ($order->raw_order_data && isset($order->raw_order_data['itens'])) {
                $itemsQuantity = count($order->raw_order_data['itens']);
            } elseif ($order->invoice && $order->invoice->itens) {
                $itemsQuantity = count($order->invoice->itens);
            }

            // Get tracking code
            $trackingCode = null;
            if ($order->tracking_code && $order->tracking_code !== '') {
                $trackingCode = $order->tracking_code;
            } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
                $firstTracking = $order->invoice->tracking()->first();
                $trackingCode = $firstTracking ? $firstTracking->codigo_rastreamento : null;
            }
            
            // Source is already mapped correctly from SystemMappingService
            $source = $order->source;
            
            $trackingUrl = null;
            if ($order->invoice && $order->invoice->tracking()->count() > 0) {
                $firstTracking = $order->invoice->tracking()->first();
                $trackingUrl = $firstTracking != ''? $firstTracking->url_rastreamento : null;
            }

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
                'tracking_url' => $trackingUrl,
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
     * Get the kanban status for an order (new structure).
     * 
     * Priority order:
     * 1. Coleta Realizada: picked_up=true
     * 2. Coleta Aguardando: invoice + picking + packing + tracking_code + !picked_up
     * 3. Etiqueta: invoice + picking + packing + !tracking_code
     * 4. Embalagem: invoice + picking + !packing
     * 5. Pedidos Emitidos: !invoice
     * 6. Faturamento: invoice + !picking
     * 7. Pedidos Emitidos: default
     */
    private function getOrderKanbanStatusNew($order): string
    {
        $orderStatus = $order->orderStatus;

        // Check for tracking code (from order_summaries.tracking_code or invoice/raw data)
        $hasTracking = false;
        if ($order->tracking_code && $order->tracking_code !== '') {
            $hasTracking = true;
        } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
            // Check if any tracking record has a valid codigo_rastreamento
            $hasTracking = $order->invoice->tracking()->whereNotNull('codigo_rastreamento')
                ->where('codigo_rastreamento', '!=', '')->exists();
        }

        // Check for invoice number
        $hasInvoice = false;
        if ($order->invoice && $order->invoice->numero) {
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

        // 5. Pedidos Emitidos: !invoice
        if (!$hasInvoice) {
            return 'pedidos_emitidos';
        }

        // 6. Faturamento: invoice + !picking
        if ($hasInvoice && (!$orderStatus || !$orderStatus->picking)) {
            return 'faturamento';
        }

        // 7. Pedidos Emitidos: default (shouldn't reach here normally)
        return 'pedidos_emitidos';
    }

    /**
     * Get current Bling sync status
     */
    public function getSyncStatus()
    {
        $syncStatus = Cache::get('bling_sync_status');
        
        if (!$syncStatus) {
            // Return default status if not found
            $syncStatus = [
                'is_running' => false,
                'last_sync_at' => null,
                'account_type' => 'all',
                'updated_at' => now()->toISOString(),
            ];
        }
        
        return response()->json($syncStatus);
    }
}
