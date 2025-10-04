<?php

namespace App\Http\Controllers;

use App\Models\OrderSummary;
use App\Jobs\BlingSyncJob;
use App\Http\Controllers\PermissionController;
use App\Services\OrderHistoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OrderController extends Controller
{
    protected OrderHistoryService $orderHistoryService;

    public function __construct(OrderHistoryService $orderHistoryService)
    {
        $this->orderHistoryService = $orderHistoryService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = OrderSummary::with(['orderStatus']);

        // Search filter
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('contato_nome', 'like', "%{$search}%")
                  ->orWhere('bling_id', 'like', "%{$search}%")
                  ->orWhere('tracking_code', 'like', "%{$search}%");
            });
        }

        // Source filter (now only 'bling')
        if ($request->filled('source')) {
            $query->where('source', $request->get('source'));
        }

        // Situacao filter
        if ($request->filled('situacao')) {
            $query->where('situacao_valor', $request->get('situacao'));
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('data', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('data', '<=', $request->get('date_to'));
        }

        // Contato filter
        if ($request->filled('contato')) {
            $query->where('contato_nome', 'like', "%{$request->get('contato')}%");
        }

        // Data processed filter
        if ($request->filled('data_processed')) {
            $query->where('data_processed', $request->get('data_processed') === '1');
        }

        // Allow custom pagination size, default to 100
        $perPage = $request->get('per_page', 100);
        $perPage = min($perPage, 500); // Max 500 per page to prevent performance issues
        
        $orders = $query->orderBy('data', 'desc')->paginate($perPage)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders->through(function ($order) {
                // Get tracking data from raw_tracking_data or direct fields
                $trackingData = $order->raw_tracking_data;
                $hasTrackingCode = !empty($order->tracking_code);
                $trackingCode = $order->tracking_code;
               
                $logisticsProvider = null;
                
                // Improved logic for determining logistics provider
                $trackingCodeExists = !empty($order->raw_tracking_data['rastreamento']['codigo']);

                if ($trackingCodeExists) {
                    $logisticsProvider =
                        $order->raw_invoice_data['transporte']['transportador']['nome']
                            ?? $order->raw_tracking_data['servico']['nome']
                            ?? '-';
                } else {
                    $logisticsProvider = '-';
                }

                $serviceType = $order->service_type;
                
                $trackingUrl = null;
                $carrier = null;
                $invoiceId = null;

                if ($trackingCode && $logisticsProvider) {
                    $trackingData = $order->raw_invoice_data;

                    if(isset($trackingData['rastreamento']['url'])) {
                        $trackingUrl = $trackingData['rastreamento']['url'];
                    }
                    
                    if(isset($order['raw_invoice_data']['transporte']['transportador']['nome'])) {
                        $carrier = $order['raw_invoice_data']['transporte']['transportador']['nome'];
                    }

                    if(isset($order['raw_invoice_data']['numero'])) {
                        $invoiceId = $order['raw_invoice_data']['numero'];
                    }
                }
                
                // Get tracking status from raw data
                $trackingStatus = null;
                if ($trackingData && isset($trackingData['rastreamento']['situacao'])) {
                    $trackingStatus = $trackingData['rastreamento']['situacao'];
                }
                
                return [
                    'id' => $order->id,
                    'bling_id' => $order->bling_id,
                    'numero' => $order->numero,
                    'numero_loja' => $order->numero_loja,
                    'data' => $order->data,
                    'data_saida' => $order->data_saida,
                    'data_prevista' => $order->data_prevista,
                    'total_produtos' => $order->total_produtos,
                    'total' => $order->total,
                    'contato_id' => $order->contato_id,
                    'contato_nome' => $order->contato_nome,
                    'contato_tipo_pessoa' => $order->contato_tipo_pessoa,
                    'contato_documento' => $order->contato_documento,
                    'situacao_id' => $order->situacao_id,
                    'situacao_valor' => $order->situacao_valor,
                    'loja_id' => $order->loja_id,
                    'source' => $order->source,
                    'data_processed' => $order->data_processed,
                    'external_updated_at' => $order->external_updated_at,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'raw_order_data' => $order->raw_order_data,
                    'raw_invoice_data' => $order->raw_invoice_data,
                    'raw_tracking_data' => $order->raw_tracking_data,
                    'logistics_provider' => $logisticsProvider,
                    'service_type' => $serviceType,
                    'volume' => $order->volume,
                    'weight' => $order->weight,
                    'order_status' => $order->orderStatus,
                    'tracking_data' => $trackingData,
                    'has_tracking_code' => $hasTrackingCode,
                    'tracking_code' => $trackingCode,
                    'tracking_url' => $trackingUrl,
                    'tracking_status' => $trackingStatus,
                    'invoice' => $order->raw_invoice_data,
                    'tracking' => $order->raw_tracking_data
                ];
            }),
            'filters' => $request->only([
                'search', 'source', 'situacao', 'date_from', 'date_to', 'contato', 'data_processed'
            ]),
            'situacoes' => [
                1 => 'Em Aberto',
                2 => 'Em Andamento',
                3 => 'Finalizado',
                4 => 'Cancelado',
            ],
            'sources' => [
                'bling' => 'Bling',
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(OrderSummary $order)
    {
        $order->load(['orderData', 'invoice', 'orderStatus']);
        
        // Load tracking data if invoice exists
        $trackingData = null;
        if ($order->invoice) {
            $trackingData = $order->invoice->tracking()->get();
        }
        
        // Load order history
        $orderHistory = $this->orderHistoryService->getOrderHistory($order);
        
        // Load status history (if exists)
        $statusHistory = collect([]);

        // Extract tracking information from raw data
        $trackingCode = $order->tracking_code;

        // Improved logic for determining logistics provider
        $trackingCodeExists = !empty($order->raw_tracking_data['rastreamento']['codigo']);

        if ($trackingCodeExists) {
            $logisticsProvider =
                $order->raw_invoice_data['transporte']['transportador']['nome']
                    ?? $order->raw_tracking_data['servico']['nome']
                    ?? '-';
        } else {
            $logisticsProvider = '-';
        }        

        $trackingUrl = null;
        
        if ($trackingCode && $logisticsProvider && $order->raw_tracking_data) {
            $trackingUrl = $order->raw_tracking_data['rastreamento']['url'] ?? null;
        }
        
        return Inertia::render('Orders/Show', [
            'order' => [
                'id' => $order->id,
                'bling_id' => $order->bling_id,
                'numero' => $order->numero,
                'numero_loja' => $order->numero_loja,
                'data' => $order->data,
                'data_saida' => $order->data_saida,
                'data_prevista' => $order->data_prevista,
                'total_produtos' => $order->total_produtos,
                'total' => $order->total,
                'contato_id' => $order->contato_id,
                'contato_nome' => $order->contato_nome,
                'contato_tipo_pessoa' => $order->contato_tipo_pessoa,
                'contato_documento' => $order->contato_documento,
                'situacao_id' => $order->situacao_id,
                'situacao_valor' => $order->situacao_valor,
                'loja_id' => $order->loja_id,
                'source' => $order->source,
                'data_processed' => $order->data_processed,
                'external_updated_at' => $order->external_updated_at,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'order_status' => $order->orderStatus,
                
                // New Bling fields
                'tracking_code' => $trackingCode,
                'logistics_provider' => $logisticsProvider,
                'service_type' => $order->service_type,
                'volume' => $order->volume,
                'weight' => $order->weight,
                'tracking_url' => $trackingUrl,
                
                // Raw data from Bling API
                'raw_order_data' => $order->raw_order_data,
                'raw_invoice_data' => $order->raw_invoice_data,
                'raw_tracking_data' => $order->raw_tracking_data,
                
                // Legacy fields for compatibility
                'order_data' => $order->orderData,
                'invoice' => $order->invoice,
                'nota_fiscal_id' => $order->orderData ? $order->orderData->nota_fiscal_id : null,
                'invoice_numero' => $order->invoice ? $order->invoice->numero : null,
                'invoice_chave_acesso' => $order->invoice ? $order->invoice->chave_acesso : null,
                'raw_data' => $order->orderData ? $order->orderData->raw_data : null,
                'invoice_raw_data' => $order->invoice ? $order->invoice->raw_data : null,
                'tracking_data' => $trackingData,
                'summary_data' => $order->toArray(),
                'order_history' => $orderHistory,
            ],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OrderSummary $order)
    {
        $request->validate([
            'action' => 'required|in:picking,packing,picked_up',
            'status' => 'required|boolean',
        ]);

        $action = $request->input('action');
        $status = $request->input('status');
        $user = Auth::user();

        // Get or create order status
        $orderStatus = $order->orderStatus ?? $order->orderStatus()->create([
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        // Update the specific status
        $updateData = [
            $action => $status,
        ];

        if ($status) {
            // Marking as completed
            $updateData["{$action}_marked_by"] = $user->name;
            $updateData["{$action}_marked_at"] = now();
        } else {
            // Unmarking
            $updateData["{$action}_marked_by"] = null;
            $updateData["{$action}_marked_at"] = null;
        }

        $orderStatus->update($updateData);

        // Record status change in history
        $this->orderHistoryService->recordStatusChange($order, $action, $status, $user);

        $actionNames = [
            'picking' => 'Separado',
            'packing' => 'Embalado',
            'picked_up' => 'Coletado'
        ];

        $actionName = $actionNames[$action];
        $message = $status ? "{$actionName} marcado como concluído" : "{$actionName} desmarcado";

        return back()->with('success', $message);
    }

    /**
     * Manually sync orders from Bling.
     */
    public function syncBling(Request $request)
    {
        // Check permissions - user must have orders.import permission
        $user = Auth::user();
        $userRole = $user->role ?? 'sales';
        if (!in_array($userRole, ['admin', 'manager'])) {
            return back()->with('error', 'Você não tem permissão para importar pedidos.');
        }

        try {
            // Dispatch Bling sync job
            Queue::push(new BlingSyncJob());

            return back()->with('success', "Sincronização com Bling iniciada. Os pedidos serão atualizados em alguns minutos.");

        } catch (\Exception $e) {
            return back()->with('error', 'Erro ao iniciar a sincronização: ' . $e->getMessage());
        }
    }

    /**
     * Build tracking URL based on logistics provider
     */
    private function buildTrackingUrl(string $trackingCode, string $logisticsProvider): string
    {
        
    }
}
