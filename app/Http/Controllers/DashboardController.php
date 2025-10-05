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
        // Get recent orders with status
        $orders = OrderSummary::with(['orderStatus'])
            ->orderBy('data', 'desc')
            ->limit(50)
            ->get();

        // Calculate metrics
        $metrics = $this->calculateMetrics($orders);

        // Get orders by status
        $ordersByStatus = $this->getOrdersByStatus($orders);

        // Get recent activity (orders with status changes)
        $recentActivity = $this->getRecentActivity($orders);

        return Inertia::render('Dashboard', [
            'metrics' => $metrics,
            'ordersByStatus' => $ordersByStatus,
            'recentActivity' => $recentActivity,
        ]);
    }

    /**
     * Calculate dashboard metrics.
     */
    private function calculateMetrics($orders): array
    {
        $total = $orders->count();
        $pending = $orders->filter(fn($order) => !$order->orderStatus)->count();
        $picking = $orders->filter(fn($order) => $order->orderStatus?->picking)->count();
        $packing = $orders->filter(fn($order) => $order->orderStatus?->packing)->count();
        $pickedUp = $orders->filter(fn($order) => $order->orderStatus?->picked_up)->count();
        $completed = $orders->filter(fn($order) => $order->orderStatus?->isCompleted())->count();

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
}
