<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Jobs\ImportWooCommerceOrdersJob;
use App\Jobs\ImportShopifyOrdersJob;
use App\Jobs\SyncBlingDataJob;
use App\Jobs\SyncAllBlingDataJob;
use App\Jobs\FetchTrackingDataJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
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
                    'tracking_url' => $order->tracking_url,
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
                'collmepro_atacadista' => 'Collmepro Atacadista',
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
        $order->load(['orderStatus.pickingMarkedBy', 'orderStatus.packingMarkedBy', 'orderStatus.pickedUpMarkedBy', 'invoices', 'labels', 'syncLogs']);
        
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

        // Format sync logs
        $syncLogs = $order->syncLogs->map(function ($log) {
            return [
                'id' => $log->id,
                'sync_type' => $log->sync_type,
                'status' => $log->status,
                'message' => $log->message,
                'error_message' => $log->error_message,
                'started_at' => $log->started_at?->format('d/m/Y H:i:s'),
                'completed_at' => $log->completed_at?->format('d/m/Y H:i:s'),
                'duration_ms' => $log->duration_ms,
                'attempts' => $log->attempts,
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
                'nfe_access_key' => $order->nfe_access_key,
                'nfe_issue_date' => $order->nfe_issue_date,
                'nfe_value' => $order->nfe_value,
                'tracking_code' => $order->tracking_code,
                'logistics_provider' => $order->logistics_provider,
                'tracking_status' => $order->tracking_status,
                'tracking_url' => $order->tracking_url,
                'order_status' => $order->orderStatus,
                'invoices' => $order->invoices,
                'labels' => $order->labels,
                'status_history' => $statusHistory,
                'sync_logs' => $syncLogs,
                'raw_data' => $order->toArray(), // Dados brutos do pedido
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

        // Check permissions - user must have orders.status.update permission
        if (!$user->hasPermission('orders.status.update')) {
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
     * Manually fetch orders from external systems.
     */
    public function fetchManual(Request $request)
    {
        // Check permissions - user must have orders.import permission
        $user = Auth::user();
        if (!$user->hasPermission('orders.import')) {
            return back()->with('error', 'Você não tem permissão para importar pedidos.');
        }

        try {
            $importedSystems = [];
            
            // Dispatch WooCommerce import jobs
            Queue::push(new ImportWooCommerceOrdersJob('collmepro'));
            $importedSystems[] = 'Collmepro';
            
            Queue::push(new ImportWooCommerceOrdersJob('collmepro_atacadista'));
            $importedSystems[] = 'Collmepro Atacadista';
            
            // Dispatch Shopify import job
            Queue::push(new ImportShopifyOrdersJob('collme_beauty'));
            $importedSystems[] = 'Collme Beauty';

            $systemsList = implode(', ', $importedSystems);
            return back()->with('success', "Importação iniciada para os sistemas: {$systemsList}. Os pedidos serão atualizados em alguns segundos.");

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar a importação: ' . $e->getMessage());
        }
    }

    /**
     * Sincronizar dados com Bling para todos os pedidos sem NFE
     */
    public function syncBlingData(Request $request)
    {
        // Check permissions - user must have orders.import permission
        $user = Auth::user();
        if (!$user->hasPermission('orders.import')) {
            return back()->with('error', 'Você não tem permissão para sincronizar dados com o Bling.');
        }

        try {
            // Dispatch job para sincronização em lote
            Queue::push(new SyncAllBlingDataJob());

            return back()->with('success', 'Sincronização com Bling iniciada. Os dados serão atualizados em alguns minutos.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar sincronização com Bling: ' . $e->getMessage());
        }
    }

    /**
     * Sincronizar dados com Bling para um pedido específico
     */
    public function syncBlingDataForOrder(Request $request, Order $order)
    {
        // Check permissions - user must have orders.import permission
        $user = Auth::user();
        if (!$user->hasPermission('orders.import')) {
            return back()->with('error', 'Você não tem permissão para sincronizar dados com o Bling.');
        }

        try {
            // Dispatch job para sincronização específica
            Queue::push(new SyncBlingDataJob($order->id));

            return back()->with('success', "Sincronização com Bling iniciada para o pedido {$order->order_number}.");

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar sincronização com Bling: ' . $e->getMessage());
        }
    }

    /**
     * Importar pedidos e sincronizar com Bling em sequência
     */
    public function importAndSync(Request $request)
    {
        // Check permissions - user must have orders.import permission
        $user = Auth::user();
        if (!$user->hasPermission('orders.import')) {
            return back()->with('error', 'Você não tem permissão para importar pedidos e sincronizar com Bling.');
        }

        try {
            $importedSystems = [];
            
            // Dispatch WooCommerce import jobs
            Queue::push(new ImportWooCommerceOrdersJob('collmepro'));
            $importedSystems[] = 'Collmepro';
            
            Queue::push(new ImportWooCommerceOrdersJob('collmepro_atacadista'));
            $importedSystems[] = 'Collmepro Atacadista';
            
            // Dispatch Shopify import job
            Queue::push(new ImportShopifyOrdersJob('collme_beauty'));
            $importedSystems[] = 'Collme Beauty';

            // Dispatch Bling sync job (será executado após os imports)
            Queue::push(new SyncAllBlingDataJob());

            $systemsList = implode(', ', $importedSystems);
            return back()->with('success', "Importação e sincronização iniciadas para os sistemas: {$systemsList}. Os dados serão atualizados em alguns minutos.");

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar importação e sincronização: ' . $e->getMessage());
        }
    }

    /**
     * Refresh tracking status for a specific order (async call)
     */
    public function refreshTracking(Request $request, Order $order)
    {
        // Check permissions - user must have orders.view permission
        $user = Auth::user();
        if (!$user->hasPermission('orders.view')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        try {
            // Check if order has NFE number (required for tracking)
            if (!$order->nfe_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order does not have NFE yet',
                    'tracking_status' => null
                ], 200);
            }

            // Dispatch tracking job to fetch latest status
            FetchTrackingDataJob::dispatchSync($order->id);

            // Reload order to get updated tracking info
            $order->refresh();

            return response()->json([
                'success' => true,
                'tracking_code' => $order->tracking_code,
                'logistics_provider' => $order->logistics_provider,
                'tracking_status' => $order->tracking_status,
                'last_updated' => now()->toIso8601String()
            ]);

        } catch (\Exception $e) {
            Log::error('Error refreshing tracking for order', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error fetching tracking status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download PDF da nota fiscal
     */
    public function downloadInvoice(Request $request, Order $order)
    {
        // Check permissions - user must have orders.view permission
        $user = Auth::user();
        if (!$user->hasPermission('orders.view')) {
            return back()->with('error', 'Você não tem permissão para visualizar pedidos.');
        }

        try {
            // Busca a invoice mais recente do pedido
            $invoice = $order->invoices()->latest()->first();
            
            if (!$invoice) {
                return back()->with('error', 'Nota fiscal não encontrada para este pedido.');
            }

            if (!$invoice->invoice_pdf_url) {
                return back()->with('error', 'PDF da nota fiscal não está disponível. Tente sincronizar novamente.');
            }

            // Se a URL for um link direto, redireciona
            if (filter_var($invoice->invoice_pdf_url, FILTER_VALIDATE_URL)) {
                return redirect($invoice->invoice_pdf_url);
            }

            // Se for um caminho local, faz download
            if (file_exists($invoice->invoice_pdf_url)) {
                return response()->download($invoice->invoice_pdf_url, "NFE-{$invoice->invoice_number}.pdf");
            }

            return back()->with('error', 'Arquivo PDF não encontrado.');

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao baixar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get system display name.
     */
    private function getSystemDisplayName(string $system): string
    {
        return match ($system) {
            'collmepro' => 'Collmepro',
            'collmepro_atacadista' => 'Collmepro Atacadista',
            'collme_beauty' => 'Collme Beauty',
            default => ucfirst(str_replace('_', ' ', $system)),
        };
    }
}
