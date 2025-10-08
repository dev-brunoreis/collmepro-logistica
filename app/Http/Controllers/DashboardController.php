<?php

namespace App\Http\Controllers;

use App\Models\OrderSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard.
     */
    public function index(): Response
    {
        // Get recent orders with status (same structure as KanbanController)
        $orders = OrderSummary::with([
            'orderStatus' => function($query) {
                $query->with(['pickingMarkedBy', 'packingMarkedBy', 'pickedUpMarkedBy']);
            },
            'invoice',
            'orderData'
        ])
            ->orderBy('data', 'desc')
            ->get();

        // Calculate metrics
        $metrics = $this->calculateMetrics($orders);

        // Get orders by status
        $ordersByStatus = $this->getOrdersByStatus($orders);

        // Get recent activity (orders with status changes)
        $recentActivity = $this->getRecentActivity($orders);

        // Get Kanban data
        $kanbanData = $this->getKanbanData($orders);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'ordersByStatus' => $ordersByStatus,
            'recentActivity' => $recentActivity,
            'kanbanData' => $kanbanData,
        ]);
    }

    /**
     * Calculate dashboard metrics (using same logic as Kanban).
     */
    private function calculateMetrics($orders): array
    {
        $total = $orders->count();
        
        // Use same logic as Kanban for consistency
        $pending = 0;
        $picking = 0;
        $packing = 0;
        $pickedUp = 0;
        $completed = 0;
        
        foreach ($orders as $order) {
            $orderStatus = $order->orderStatus;
            
            if (!$orderStatus) {
                $pending++;
            } else {
                if ($orderStatus->picked_up) {
                    $pickedUp++;
                } elseif ($orderStatus->packing) {
                    $packing++;
                } elseif ($orderStatus->picking) {
                    $picking++;
                } else {
                    $pending++;
                }
                
                if ($orderStatus->isCompleted()) {
                    $completed++;
                }
            }
        }

        return [
            'total_orders' => $total,
            'pending_orders' => $pending,
            'picking_orders' => $picking,
            'packing_orders' => $packing,
            'picked_up_orders' => $pickedUp,
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
}
