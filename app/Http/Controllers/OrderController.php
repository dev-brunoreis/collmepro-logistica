<?php

namespace App\Http\Controllers;

use App\Events\OrderStatusUpdated;
use App\Models\OrderSummary;
use App\Jobs\BlingSyncJob;
use App\Http\Controllers\PermissionController;
use App\Services\CarrierService;
use App\Services\OrderHistoryService;
use App\Services\SystemMappingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class OrderController extends Controller
{
    protected OrderHistoryService $orderHistoryService;

    /**
     * Get systems mapping from SystemMappingService
     */
    private function getSystemsMap(): array
    {
        return SystemMappingService::getAllSystems();
    }

    /**
     * Get systems mapping for frontend (flattened for dropdown)
     */
    private function getSystemsForFrontend(): array
    {
        return SystemMappingService::getSystemsForDropdown();
    }

    public function __construct(OrderHistoryService $orderHistoryService)
    {
        $this->orderHistoryService = $orderHistoryService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = OrderSummary::with(['orderStatus', 'invoice.tracking', 'seller']);

        // Search filter - improved to include seller name, tracking and NFE number
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('numero', 'like', "%{$search}%")
                  ->orWhere('contato_nome', 'like', "%{$search}%")
                  ->orWhere('bling_id', 'like', "%{$search}%")
                  ->orWhere('tracking_code', 'like', "%{$search}%")
                  ->orWhereJsonContains('raw_order_data->vendedor->nome', $search)
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_order_data, '$.vendedor.nome')) LIKE ?", ["%{$search}%"])
                  ->orWhereHas('invoice', function ($q) use ($search) {
                      $q->where('numero', 'like', "%{$search}%");
                  });
            });
        }

        // System filter - filter by source field
        if ($request->filled('system')) {
            $systemFilter = $request->get('system');
            
            $systemsMap = $this->getSystemsMap();
            
            // If the filter is a store ID, get the system name
            if (isset($systemsMap[$systemFilter])) {
                $systemName = $systemsMap[$systemFilter]['name'];
                $query->where('source', $systemName);
            } else {
                // If it's a system name, use it directly
                $query->where('source', $systemFilter);
            }
        }
        
        // Source filter (legacy support)
        if ($request->filled('source')) {
            $query->where('source', $request->get('source'));
        }

        // Situacao filter
        if ($request->filled('situacao')) {
            $situacao = $request->get('situacao');
            // Special case: situacao_id = 12 means cancelled
            if ($situacao == 12) {
                $query->where('situacao_id', 12);
            } else {
                $query->where('situacao_valor', $situacao);
            }
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
                        $q->whereHas('invoice.tracking', function ($q) use ($targetDescription) {
                            $q->where('descricao', $targetDescription);
                        });
                    });
                }
            } elseif (str_starts_with($trackingStatus, 'situacao_')) {
                // Filter by specific tracking situacao (numeric code)
                $situacao = (int) str_replace('situacao_', '', $trackingStatus);
                $query->whereHas('invoice.tracking', function ($q) use ($situacao) {
                    $q->where('situacao', $situacao);
                });
            }
        }

        // Logistics provider filter
        if ($request->filled('logistics_provider')) {
            $logisticsProvider = $request->get('logistics_provider');
            $query->where('logistics_provider', $logisticsProvider);
        }

        // Seller filter
        if ($request->filled('seller')) {
            $sellerId = $request->get('seller');
            $query->whereHas('seller', function ($q) use ($sellerId) {
                $q->where('sellers.id', $sellerId);
            });
        }

        // Operational Status Filters
        if ($request->filled('has_picking')) {
            $hasPicking = $request->get('has_picking') === '1';
            $query->whereHas('orderStatus', function ($q) use ($hasPicking) {
                $q->where('picking', $hasPicking);
            });
        }

        if ($request->filled('has_packing')) {
            $hasPacking = $request->get('has_packing') === '1';
            $query->whereHas('orderStatus', function ($q) use ($hasPacking) {
                $q->where('packing', $hasPacking);
            });
        }

        if ($request->filled('has_picked_up')) {
            $hasPickedUp = $request->get('has_picked_up') === '1';
            $query->whereHas('orderStatus', function ($q) use ($hasPickedUp) {
                $q->where('picked_up', $hasPickedUp);
            });
        }

        // Has Invoice Filter
        if ($request->filled('has_invoice')) {
            $hasInvoice = $request->get('has_invoice');
            if ($hasInvoice === 'with_invoice') {
                $query->has('invoice');
            } elseif ($hasInvoice === 'without_invoice') {
                $query->doesntHave('invoice');
            }
        }

        // Value Range Filters
        if ($request->filled('value_min')) {
            $valueMin = $request->get('value_min');
            if (is_numeric($valueMin) && $valueMin >= 0) {
                $query->where('total', '>=', $valueMin);
            }
        }

        if ($request->filled('value_max')) {
            $valueMax = $request->get('value_max');
            if (is_numeric($valueMax) && $valueMax >= 0) {
                $query->where('total', '<=', $valueMax);
            }
        }

        // Allow custom pagination size, default to 25
        $perPage = $request->get('per_page', 25);
        $perPage = min($perPage, 100); // Max 100 per page to prevent performance issues
        
        $orders = $query->orderBy('data', 'desc')->paginate($perPage)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders->through(function ($order) {
                // Get first tracking record from relationship
                $firstTracking = $order->invoice?->tracking->first();
                
                // Check for tracking code from direct field or relationship
                $trackingCode = $order->tracking_code;
                
                // Fallback to tracking relationship if tracking_code is empty
                if (empty($trackingCode) && $firstTracking) {
                    $trackingCode = $firstTracking->codigo_rastreamento;
                }
                
                $hasTrackingCode = !empty($trackingCode);
               
                // Get carrier name using priority order:
                // 1. invoice.raw_data['transporte']['nome'] (primary source from JSON)
                // 2. invoice.transportador_nome (normalized field fallback)
                // 3. order_summary.logistics_provider (secondary fallback)
                $logisticsProvider = null;
                if ($order->invoice) {
                    // Try raw_data first (primary source)
                    $rawData = $order->invoice->raw_data;
                    if ($rawData && isset($rawData['transporte']['nome'])) {
                        $logisticsProvider = $rawData['transporte']['nome'];
                    } else {
                        // Fallback to normalized field
                        $logisticsProvider = $order->invoice->transportador_nome;
                    }
                }
                
                // Final fallback to order_summary field
                if (empty($logisticsProvider)) {
                    $logisticsProvider = $order->logistics_provider;
                }

                // Use CarrierService to map service_type if logistics_provider is still empty or '-'
                if (empty($logisticsProvider) || $logisticsProvider === '-') {
                    $logisticsProvider = CarrierService::getCarrierDisplay($order->service_type);
                }
                
                // Default to "-" if still empty
                if (empty($logisticsProvider)) {
                    $logisticsProvider = '-';
                }
                
                $serviceType = $order->service_type;
                
                $trackingUrl = null;
                $carrier = $logisticsProvider;
                $invoiceId = null;

                if ($trackingCode) {
                    // Get tracking URL from relationship
                    if ($firstTracking) {
                        $trackingUrl = $firstTracking->url_rastreamento;
                    }
                    
                    // Get invoice number
                    if ($order->invoice) {
                        $invoiceId = $order->invoice->numero;
                    }
                }
                
                // Get tracking status from relationship
                $trackingStatus = null;
                if ($firstTracking && $firstTracking->situacao) {
                    $trackingStatus = $this->mapTrackingStatus($firstTracking->situacao);
                }
                
                // Define source based on loja_id from invoice relationship
                $source = $this->determineSourceFromInvoiceData($order);

                // Get seller name
                $sellerName = $order->seller->first()?->name ?? (strtolower($order->source) === 'beauty' ? '-' : null);

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
                    'source' => $source,
                    'seller_name' => $sellerName,
                    'data_processed' => $order->data_processed,
                    'external_updated_at' => $order->external_updated_at,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'raw_order_data' => $order->raw_order_data,
                    'logistics_provider' => $logisticsProvider,
                    'service_type' => $serviceType,
                    'volume' => $order->volume,
                    'weight' => $order->weight,
                    'order_status' => $order->orderStatus,
                    'has_tracking_code' => $hasTrackingCode,
                    'tracking_code' => $trackingCode,
                    'tracking_url' => $trackingUrl,
                    'tracking_status' => $trackingStatus,
                    'invoice' => $order->invoice,
                    'tracking' => $order->invoice?->tracking->toArray()
                ];
            }),
            'filters' => $request->only([
                'search', 'system', 'tracking_status', 'logistics_provider', 'seller', 'source', 'situacao', 
                'date_from', 'date_to', 'contato', 'data_processed', 'has_picking', 'has_packing', 
                'has_picked_up', 'has_invoice', 'value_min', 'value_max'
            ]),
            'situacoes' => [
                0 => 'Em Aberto',
                1 => 'Em Andamento',
                2 => 'Em Processamento',
                11 => 'Atendido',
                12 => 'Cancelado', // situacao_id = 12
            ],
            'systems' => $this->getSystemsForFrontend(),
            'systems_with_colors' => $this->getSystemsMap(),
            'tracking_statuses' => $this->getTrackingStatuses($query),
            'logistics_providers' => $this->getLogisticsProviders($query),
            'sellers' => $this->getSellers(),
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(OrderSummary $order)
    {
        $order->load(['orderData', 'invoice', 'orderStatus', 'seller']);
        
        // Load tracking data if invoice exists
        $trackingData = null;
        if ($order->invoice) {
            $trackingData = $order->invoice->tracking()->get();
        }
        
        // Load order history
        $orderHistory = $this->orderHistoryService->getOrderHistory($order);
        
        // Load status history (if exists)
        $statusHistory = collect([]);

        // Extract tracking information from relationships
        $trackingCode = $order->tracking_code;
        $firstTracking = $trackingData?->first();
        
        // Fallback to tracking relationship if tracking_code is empty
        if (empty($trackingCode) && $firstTracking) {
            $trackingCode = $firstTracking->codigo_rastreamento;
        }

        // Get carrier name using priority order:
        // 1. invoice.raw_data['transporte']['nome'] (primary source from JSON)
        // 2. invoice.transportador_nome (normalized field fallback)
        // 3. order_summary.logistics_provider (secondary fallback)
        $logisticsProvider = null;
        if ($order->invoice) {
            // Try raw_data first (primary source)
            $rawData = $order->invoice->raw_data;
            if ($rawData && isset($rawData['transporte']['nome'])) {
                $logisticsProvider = $rawData['transporte']['nome'];
            } else {
                // Fallback to normalized field
                $logisticsProvider = $order->invoice->transportador_nome;
            }
        }
        
        // Final fallback to order_summary field
        if (empty($logisticsProvider)) {
            $logisticsProvider = $order->logistics_provider;
        }

        // Use CarrierService to map service_type if logistics_provider is still empty or '-'
        if (empty($logisticsProvider) || $logisticsProvider === '-') {
            $logisticsProvider = CarrierService::getCarrierDisplay($order->service_type);
        }
        
        // Default to "-" if still empty
        if (empty($logisticsProvider)) {
            $logisticsProvider = '-';
        }

        $trackingUrl = null;
        
        if ($trackingCode && $firstTracking) {
            $trackingUrl = $firstTracking->url_rastreamento;
        }
        
        // Define source based on loja_id from raw_invoice_data
        $source = $this->determineSourceFromInvoiceData($order);
        
        // Get seller information
        $seller = $order->seller->first();
        $sellerData = null;
        if ($seller) {
            $sellerData = [
                'id' => $seller->id,
                'name' => $seller->name,
                'email' => $seller->email,
            ];
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
                'source' => $source,
                'seller' => $sellerData,
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
                
                // Legacy fields for compatibility
                'order_data' => $order->orderData,
                'invoice' => $order->invoice,
                'nota_fiscal_id' => $order->orderData ? $order->orderData->nota_fiscal_id : null,
                'invoice_numero' => $order->invoice ? $order->invoice->numero : null,
                'invoice_chave_acesso' => $order->invoice ? $order->invoice->chave_acesso : null,
                'raw_data' => $order->orderData ? $order->orderData->raw_data : null,
                'invoice_raw_data' => $order->invoice ? $order->invoice->raw_data : null,
                'raw_invoice_data' => $order->invoice ? $order->invoice->raw_data : null, // Alias for Vue component
                'tracking_data' => $trackingData,
                'summary_data' => $order->toArray(),
                'order_history' => $orderHistory,
            ],
            'systems_with_colors' => $this->getSystemsMap(),
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

        // Prevent status updates for cancelled orders
        if ($order->isCancelled()) {
            return back()->with('error', 'Não é possível modificar o status de pedidos cancelados.');
        }

        // Get or create order status
        $orderStatus = $order->orderStatus ?? $order->orderStatus()->create([
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        // Check if order has invoice
        $hasInvoice = false;
        if ($order->invoice && $order->invoice->numero) {
            $hasInvoice = true;
        } elseif ($order->raw_invoice_data && isset($order->raw_invoice_data['numero'])) {
            $hasInvoice = true;
        }

        // Validate sequential flow rules (only when marking as true)
        if ($status) {
            switch ($action) {
                case 'picking':
                    // Validation: Cannot mark picking without invoice
                    if (!$hasInvoice) {
                        return back()->with('error', 'Não é possível marcar picking sem nota fiscal. O pedido deve estar faturado primeiro.');
                    }
                    break;

                case 'packing':
                    // Validation: Cannot mark packing without picking
                    if (!$orderStatus->picking) {
                        return back()->with('error', 'Não é possível marcar packing sem ter picking marcado primeiro.');
                    }
                    break;

                case 'picked_up':
                    // Validation: Cannot mark picked_up without packing
                    if (!$orderStatus->packing) {
                        return back()->with('error', 'Não é possível marcar picked_up sem ter packing marcado primeiro.');
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
                        return back()->with('error', 'Não é possível marcar como coletado sem código de rastreamento.');
                    }
                    break;
            }
        } else {
            // Validate when unmarking (setting to false)
            switch ($action) {
                case 'picking':
                    // Validation: Cannot unmark picking if packing is true
                    if ($orderStatus->packing) {
                        return back()->with('error', 'Não é possível desmarcar picking enquanto packing estiver marcado.');
                    }
                    break;

                case 'packing':
                    // Validation: Cannot unmark packing if picked_up is true
                    if ($orderStatus->picked_up) {
                        return back()->with('error', 'Não é possível desmarcar packing enquanto picked_up estiver marcado.');
                    }
                    break;
            }
        }

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

        // Broadcast the status update to all connected clients
        broadcast(new OrderStatusUpdated($order, $user, $action, $status));

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
     * Get all tracking descriptions from tracking table
     */
    private function getAllTrackingDescriptions()
    {
        try {
            // Get unique descriptions from tracking table
            $descriptions = \App\Models\Tracking::whereNotNull('descricao')
                ->where('descricao', '!=', '')
                ->distinct()
                ->pluck('descricao')
                ->filter()
                ->unique()
                ->sort()
                ->mapWithKeys(function ($descricao) {
                    // Create a safe key from the description
                    $key = 'descricao_' . md5($descricao);
                    return [$key => $descricao];
                });
                
            return $descriptions->toArray();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get unique logistics providers from orders
     */
    private function getLogisticsProviders($query)
    {
        try {
            // Get distinct logistics_provider values from the database
            $providers = OrderSummary::whereNotNull('logistics_provider')
                ->where('logistics_provider', '!=', '-')
                ->distinct()
                ->pluck('logistics_provider')
                ->filter()
                ->sort()
                ->values();
            
            return $providers->mapWithKeys(function ($provider) {
                return [$provider => $provider];
            })->toArray();
        } catch (\Exception $e) {
            // Return empty array if there's an error
            return [];
        }
    }

    /**
     * Get unique sellers from orders
     */
    private function getSellers()
    {
        try {
            // Get distinct sellers from database
            $sellers = \App\Models\Seller::orderBy('name')
                ->pluck('name', 'id')
                ->toArray();
            
            return $sellers;
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

    /**
     * Determine source based on loja_id from invoice relationship
     */
    private function determineSourceFromInvoiceData($order): string
    {
        // Check if invoice exists and has loja_id
        if ($order->invoice && $order->invoice->loja_id) {
            $lojaId = (string) $order->invoice->loja_id;
            $systemsMap = $this->getSystemsMap();
            
            if (isset($systemsMap[$lojaId])) {
                $config = $systemsMap[$lojaId];
                return is_array($config) && isset($config['name']) ? $config['name'] : $config;
            }
        }

        // Fallback to existing source or default
        return $order->source ?? 'bling';
    }
}
