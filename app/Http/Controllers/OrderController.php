<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Order::with(['orderStatus', 'invoices', 'labels']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('seller_name', 'like', "%{$search}%")
                  ->orWhere('tracking_code', 'like', "%{$search}%");
            });
        }

        // System filter
        if ($request->filled('system')) {
            $query->where('system', $request->get('system'));
        }


        // Tracking status filter
        if ($request->filled('tracking_status')) {
            $query->where('tracking_status', $request->get('tracking_status'));
        }

        // Logistics provider filter
        if ($request->filled('logistics_provider')) {
            $query->where('logistics_provider', $request->get('logistics_provider'));
        }

        // Seller filter
        if ($request->filled('seller')) {
            $query->where('seller_name', 'like', "%{$request->get('seller')}%");
        }

        // Picking status filter
        if ($request->filled('picking_status')) {
            $query->whereHas('orderStatus', function ($q) use ($request) {
                $q->where('picking', $request->get('picking_status') === 'true');
            });
        }

        // Packing status filter
        if ($request->filled('packing_status')) {
            $query->whereHas('orderStatus', function ($q) use ($request) {
                $q->where('packing', $request->get('packing_status') === 'true');
            });
        }

        // Picked up status filter
        if ($request->filled('picked_up_status')) {
            $query->whereHas('orderStatus', function ($q) use ($request) {
                $q->where('picked_up', $request->get('picked_up_status') === 'true');
            });
        }

        $orders = $query->orderBy('order_date', 'desc')->paginate(25)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders->through(function ($order) {
                return [
                    'id' => $order->id,
                    'system' => $order->system,
                    'system_display_name' => $this->getSystemDisplayName($order->system),
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'seller_name' => $order->seller_name,
                    'order_date' => $order->order_date,
                    'formatted_order_date' => $order->formatted_order_date,
                    'nfe_number' => $order->nfe_number,
                    'nfe_pdf_url' => $order->nfe_pdf_url,
                    'tracking_code' => $order->tracking_code,
                    'logistics_provider' => $order->logistics_provider,
                    'tracking_status' => $order->tracking_status,
                    'has_nfe' => $order->hasNfe(),
                    'has_tracking_code' => $order->hasTrackingCode(),
                    'order_status' => $order->orderStatus ? [
                        'picking' => $order->orderStatus->picking,
                        'packing' => $order->orderStatus->packing,
                        'picked_up' => $order->orderStatus->picked_up,
                        'picking_marked_by' => $order->orderStatus->pickingMarkedBy?->name,
                        'packing_marked_by' => $order->orderStatus->packingMarkedBy?->name,
                        'picked_up_marked_by' => $order->orderStatus->pickedUpMarkedBy?->name,
                        'picking_marked_at' => $order->orderStatus->picking_marked_at,
                        'packing_marked_at' => $order->orderStatus->packing_marked_at,
                        'picked_up_marked_at' => $order->orderStatus->picked_up_marked_at,
                    ] : null,
                    'latest_invoice' => $order->getLatestInvoice(),
                    'latest_label' => $order->getLatestLabel(),
                ];
            }),
            'filters' => $request->only([
                'search', 'system', 
                'tracking_status', 'logistics_provider', 'seller',
                'picking_status', 'packing_status', 'picked_up_status'
            ]),
            'systems' => [
                'collmepro' => 'Collmepro',
                'collmepro_wholesale' => 'Collmepro Atacadista',
                'collme_beauty' => 'Collme Beauty', // Preparado para Shopify
            ],
            'tracking_statuses' => [
                'label_issued' => 'Etiqueta Emitida',
                'dispatched' => 'Despachado',
                'in_transit' => 'Em Trânsito',
                'delivered' => 'Entregue',
            ],
            'logistics_providers' => [
                'correios_pac' => 'Correios PAC',
                'correios_sedex' => 'Correios SEDEX',
                'total_express' => 'Total Express',
                'melhor_envio' => 'Melhor Envio',
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        $order->load(['orderStatus.pickingMarkedBy', 'orderStatus.packingMarkedBy', 'orderStatus.pickedUpMarkedBy', 'invoices', 'labels']);
        
        // Load status history
        $statusHistory = \App\Models\OrderStatusHistory::with('user')
            ->where('order_id', $order->id)
            ->orderBy('changed_at', 'desc')
            ->get()
            ->map(function ($history) {
                return [
                    'id' => $history->id,
                    'status_type' => $history->status_type,
                    'status_value' => $history->status_value,
                    'user_name' => $history->user->name,
                    'user_username' => $history->user->username,
                    'changed_at' => $history->changed_at,
                    'formatted_changed_at' => $history->changed_at->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'system' => $order->system,
                'system_display_name' => $this->getSystemDisplayName($order->system),
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'seller_name' => $order->seller_name,
                'order_date' => $order->order_date,
                'formatted_order_date' => $order->formatted_order_date,
                'nfe_number' => $order->nfe_number,
                'nfe_pdf_url' => $order->nfe_pdf_url,
                'tracking_code' => $order->tracking_code,
                'logistics_provider' => $order->logistics_provider,
                'tracking_status' => $order->tracking_status,
                'order_status' => $order->orderStatus,
                'invoices' => $order->invoices,
                'labels' => $order->labels,
                'status_history' => $statusHistory,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        $validated = $request->validate([
            'action' => 'required|in:picking,packing,picked_up',
            'status' => 'required|boolean',
        ]);

        if (!$order->orderStatus) {
            return back()->with('error', 'Status do pedido não encontrado.');
        }

        $user = Auth::user();
        $action = $validated['action'];
        $status = $validated['status'];

        // Check permissions - only Admin and Manager can update status
        if (!in_array($user->role, ['admin', 'manager'])) {
            return back()->with('error', 'Você não tem permissão para alterar o status do pedido.');
        }

        if ($action === 'picking') {
            if ($status) {
                $order->orderStatus->markPicking($user);
                $message = 'Picking marcado como concluído.';
            } else {
                $order->orderStatus->unmarkPicking($user);
                $message = 'Picking desmarcado.';
            }
        } elseif ($action === 'packing') {
            if ($status) {
                $order->orderStatus->markPacking($user);
                $message = 'Packing marcado como concluído.';
            } else {
                $order->orderStatus->unmarkPacking($user);
                $message = 'Packing desmarcado.';
            }
        } elseif ($action === 'picked_up') {
            if ($status) {
                $order->orderStatus->markPickedUp($user);
                $message = 'Pedido marcado como retirado.';
            } else {
                $order->orderStatus->unmarkPickedUp($user);
                $message = 'Status de retirada desmarcado.';
            }
        }

        return back()->with('success', $message);
    }

    /**
     * Get system display name.
     */
    private function getSystemDisplayName(string $system): string
    {
        return match ($system) {
            'collmepro' => 'Collmepro',
            'collmepro_wholesale' => 'Collmepro Atacadista',
            'collme_beauty' => 'Collme Beauty',
            default => ucfirst(str_replace('_', ' ', $system)),
        };
    }
}
