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

        // Search filter - improved to include seller name, tracking and NFE number
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('contato_nome', 'like', "%{$search}%")
                  ->orWhere('bling_id', 'like', "%{$search}%")
                  ->orWhere('tracking_code', 'like', "%{$search}%")
                  ->orWhere('seller_name', 'like', "%{$search}%")
                  ->orWhereJsonContains('raw_order_data->vendedor->nome', $search)
                  ->orWhereJsonContains('raw_invoice_data->rastreamento->codigo', $search)
                  ->orWhereJsonContains('raw_invoice_data->numero', $search);
            });
        }

        // System filter (now only 'bling')
        if ($request->filled('system')) {
            $query->where('source', $request->get('system'));
        }
        
        // Source filter (legacy support)
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

        // Tracking status filter
        if ($request->filled('tracking_status')) {
            $trackingStatus = $request->get('tracking_status');
            if ($trackingStatus === 'with_tracking') {
                $query->whereNotNull('tracking_code')
                      ->where('tracking_code', '!=', '');
            } elseif ($trackingStatus === 'without_tracking') {
                $query->where(function ($q) {
                    $q->whereNull('tracking_code')
                      ->orWhere('tracking_code', '');
                });
            } elseif (str_starts_with($trackingStatus, 'descricao_')) {
                // Filter by specific tracking description
                $descricaoHash = str_replace('descricao_', '', $trackingStatus);
                $descriptions = $this->getAllTrackingDescriptions();
                $targetDescription = null;
                
                // Find the actual description text by hash
                foreach ($descriptions as $key => $desc) {
                    if (str_starts_with($key, 'descricao_') && md5($desc) === $descricaoHash) {
                        $targetDescription = $desc;
                        break;
                    }
                }
                
                if ($targetDescription) {
                    $query->where(function ($q) use ($targetDescription) {
                        $q->whereJsonContains('raw_tracking_data->rastreamento->descricao', $targetDescription)
                          ->orWhereJsonContains('raw_invoice_data->rastreamento->descricao', $targetDescription)
                          ->orWhereJsonContains('raw_invoice_data->transporte->rastreamento->descricao', $targetDescription);
                    });
                }
            } elseif (str_starts_with($trackingStatus, 'situacao_')) {
                // Filter by specific tracking situacao (numeric code)
                $situacao = (int) str_replace('situacao_', '', $trackingStatus);
                $query->whereJsonContains('raw_tracking_data->rastreamento->situacao', $situacao);
            }
        }

        // Logistics provider filter
        if ($request->filled('logistics_provider')) {
            $logisticsProvider = $request->get('logistics_provider');
            $query->where(function ($q) use ($logisticsProvider) {
                $q->whereJsonContains('raw_invoice_data->transporte->transportador->nome', $logisticsProvider)
                  ->orWhereJsonContains('raw_tracking_data->servico->nome', $logisticsProvider);
            });
        }

        // Seller filter
        if ($request->filled('seller')) {
            $seller = $request->get('seller');
            $query->where(function ($q) use ($seller) {
                $q->where('seller_name', 'like', "%{$seller}%")
                  ->orWhereJsonContains('raw_order_data->vendedor->nome', $seller);
            });
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
                $trackingCodeExists = !empty($order->raw_tracking_data['rastreamento']['codigo'] ?? null);

                if ($trackingCodeExists) {
                    $logisticsProvider =
                        $order->raw_invoice_data['transporte']['transportador']['nome'] ?? null
                        ?? $order->raw_tracking_data['servico']['nome'] ?? null
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
                    
                    if(isset($order->raw_invoice_data['transporte']['transportador']['nome'])) {
                        $carrier = $order->raw_invoice_data['transporte']['transportador']['nome'];
                    }

                    if(isset($order->raw_invoice_data['numero'])) {
                        $invoiceId = $order->raw_invoice_data['numero'];
                    }
                }
                
                // Get tracking status from raw data
                $trackingStatus = null;
                if ($trackingData && isset($trackingData['rastreamento']['situacao'])) {
                    $statusCode = $trackingData['rastreamento']['situacao'];
                    $trackingStatus = $this->mapTrackingStatus($statusCode);
                }
                
                // Also check invoice data for tracking status
                if (!$trackingStatus && isset($order->raw_invoice_data['rastreamento']['situacao'])) {
                    $statusCode = $order->raw_invoice_data['rastreamento']['situacao'];
                    $trackingStatus = $this->mapTrackingStatus($statusCode);
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
                'search', 'system', 'tracking_status', 'logistics_provider', 'seller', 'source', 'situacao', 'date_from', 'date_to', 'contato', 'data_processed'
            ]),
            'situacoes' => [
                1 => 'Em Aberto',
                2 => 'Em Andamento',
                3 => 'Finalizado',
                4 => 'Cancelado',
            ],
            'systems' => [
                'bling' => 'Bling',
            ],
            'tracking_statuses' => $this->getTrackingStatuses($query),
            'logistics_providers' => $this->getLogisticsProviders($query),
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
     * Get unique tracking statuses from orders
     */
    private function getTrackingStatuses($query)
    {
        $statuses = collect([
            'with_tracking' => 'Com Rastreamento',
            'without_tracking' => 'Sem Rastreamento',
        ]);
        
        try {
            // Simplified version to avoid memory issues
            $trackingDescriptions = collect();
            
            // Get a limited set of orders to avoid memory issues
            $orders = OrderSummary::whereNotNull('raw_tracking_data')
                ->limit(1000)
                ->get();
            
            foreach ($orders as $order) {
                if (isset($order->raw_tracking_data['rastreamento']['descricao'])) {
                    $desc = $order->raw_tracking_data['rastreamento']['descricao'];
                    $key = 'descricao_' . md5($desc);
                    $trackingDescriptions[$key] = $desc;
                }
            }
                
            $statuses = $statuses->merge($trackingDescriptions);
            
            return $statuses->toArray();
        } catch (\Exception $e) {
            // Return default statuses if there's an error
            return $statuses->toArray();
        }
    }

    /**
     * Get all tracking descriptions from orders
     */
    private function getAllTrackingDescriptions()
    {
        $descriptions = collect();
        
        try {
            // 1. From raw_tracking_data
            $trackingData = OrderSummary::whereNotNull('raw_tracking_data')
                ->get()
                ->pluck('raw_tracking_data')
                ->filter()
                ->map(function ($data) {
                    if (is_array($data) && isset($data['rastreamento']['descricao'])) {
                        return $data['rastreamento']['descricao'];
                    }
                    return null;
                })
                ->filter()
                ->unique();
            
            // 2. From raw_invoice_data (rastreamento)
            $invoiceTrackingData = OrderSummary::whereNotNull('raw_invoice_data')
                ->get()
                ->pluck('raw_invoice_data')
                ->filter()
                ->map(function ($data) {
                    if (is_array($data) && isset($data['rastreamento']['descricao'])) {
                        return $data['rastreamento']['descricao'];
                    }
                    return null;
                })
                ->filter()
                ->unique();
            
            // 3. From raw_invoice_data (transporte)
            $invoiceTransportData = OrderSummary::whereNotNull('raw_invoice_data')
                ->get()
                ->pluck('raw_invoice_data')
                ->filter()
                ->map(function ($data) {
                    if (is_array($data) && isset($data['transporte']['rastreamento']['descricao'])) {
                        return $data['transporte']['rastreamento']['descricao'];
                    }
                    return null;
                })
                ->filter()
                ->unique();
            
            // Combine all descriptions
            $allDescriptions = $trackingData
                ->merge($invoiceTrackingData)
                ->merge($invoiceTransportData)
                ->filter()
                ->unique()
                ->sort()
                ->map(function ($descricao) {
                    // Create a safe key from the description
                    $key = 'descricao_' . md5($descricao);
                    return [$key => $descricao];
                })
                ->collapse();
                
            return $allDescriptions->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get unique logistics providers from orders
     */
    private function getLogisticsProviders($query)
    {
        $providers = collect();
        
        try {
            // Simplified version to avoid memory issues
            $invoiceProviders = collect();
            $trackingProviders = collect();
            
            // Get providers from raw_invoice_data with limit
            $invoiceOrders = OrderSummary::whereNotNull('raw_invoice_data')
                ->limit(1000)
                ->get();
                
            foreach ($invoiceOrders as $order) {
                if (isset($order->raw_invoice_data['transporte']['transportador']['nome'])) {
                    $invoiceProviders[] = $order->raw_invoice_data['transporte']['transportador']['nome'];
                }
            }
                
            // Get providers from raw_tracking_data with limit
            $trackingOrders = OrderSummary::whereNotNull('raw_tracking_data')
                ->limit(1000)
                ->get();
                
            foreach ($trackingOrders as $order) {
                if (isset($order->raw_tracking_data['servico']['nome'])) {
                    $trackingProviders[] = $order->raw_tracking_data['servico']['nome'];
                }
            }
                
            $providers = $providers->merge($invoiceProviders)->merge($trackingProviders)->unique()->sort()->values();
            
            return $providers->mapWithKeys(function ($provider) {
                return [$provider => $provider];
            })->toArray();
        } catch (\Exception $e) {
            // Return empty array if there's an error
            return [];
        }
    }

    /**
     * Map tracking status code to human readable text
     */
    private function mapTrackingStatus($statusCode)
    {
        $statusMap = [
            0 => 'Objeto postado',
            1 => 'Objeto em trânsito',
            2 => 'Objeto em trânsito - por favor aguarde',
            3 => 'Objeto saiu para entrega ao destinatário',
            4 => 'Objeto entregue ao destinatário',
            5 => 'Objeto devolvido ao remetente',
            6 => 'Objeto extraviado',
            7 => 'Objeto em trânsito - por favor aguarde',
            8 => 'Objeto em trânsito - por favor aguarde',
            9 => 'Objeto em trânsito - por favor aguarde',
        ];
        
        return $statusMap[$statusCode] ?? 'Status desconhecido';
    }

    /**
     * Build tracking URL based on logistics provider
     */
    private function buildTrackingUrl(string $trackingCode, string $logisticsProvider): string
    {
        // TODO: Implement tracking URL building logic based on provider
        return '';
    }
}
