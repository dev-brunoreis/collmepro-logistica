<?php

namespace App\Http\Controllers;

use App\Models\HiddenStockProduct;
use App\Models\OrderSummary;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class StockController extends Controller
{
    /**
     * Display the stock page
     */
    public function index(
        Request $request,
        CollmeProBlingService $pro,
        CollmeAtacadistaBlingService $atacadista
    ) {
        // Validate request parameters
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'source' => 'nullable|in:Pro,Atacadista',
            'months_back' => 'nullable|integer|min:1|max:60',
            'start_month' => 'nullable|date_format:Y-m',
            'end_month' => 'nullable|date_format:Y-m|after_or_equal:start_month',
            'per_page' => 'nullable|integer|min:10|max:100',
            'sort_column' => 'nullable|string',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        // Always filter to show only Pro and Atacadista, ignore Beauty
        $validated['source'] = $validated['source'] ?? null;

        // Set default values
        $perPage = $validated['per_page'] ?? 10;
        $source = $validated['source'] ?? null;
        $search = $validated['search'] ?? null;
        
        // Calculate date range based on months_back or use provided dates
        if (isset($validated['months_back'])) {
            $monthsBack = $validated['months_back'];
            $startMonth = now()->subMonths($monthsBack - 1)->format('Y-m');
            $endMonth = now()->format('Y-m');
        } else {
            $startMonth = $validated['start_month'] ?? now()->subMonths(5)->format('Y-m');
            $endMonth = $validated['end_month'] ?? now()->format('Y-m');
        }

        // Get sorting parameters
        $sortColumn = $validated['sort_column'] ?? null;
        $sortDirection = $validated['sort_direction'] ?? 'asc';

        // Get available months for the table headers
        $months = $this->getAvailableMonths($startMonth, $endMonth);

        // Paginate products at database level FIRST for instant loading
        // Stock data will be fetched progressively on the frontend
        $products = $this->buildStockQuery($source, $startMonth, $endMonth, $search, true, true, $sortColumn, $sortDirection);

        // Transform the paginated data for the frontend
        $transformedProducts = collect($products->items())->map(function ($product) use ($months) {
            $salesByMonth = [];
            
            // Initialize all months with 0
            foreach ($months as $month) {
                $salesByMonth[$month] = 0;
            }
            
            // Fill in actual sales data
            if ($product->sales_data) {
                foreach (json_decode($product->sales_data, true) as $sale) {
                    $month = $sale['month'];
                    $salesByMonth[$month] = (int) $sale['total_quantity'];
                }
            }

            return [
                'product_id' => $product->product_id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'source' => $product->source ?? 'Unknown',
                'current_stock' => null, // Will be fetched progressively by frontend
                'sales_by_month' => $salesByMonth,
            ];
        });

        // Create a new paginator with transformed data
        $products = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedProducts,
            $products->total(),
            $products->perPage(),
            $products->currentPage(),
            [
                'path' => $products->path(),
                'pageName' => 'page',
            ]
        );
        
        $products->appends(request()->query());

        return Inertia::render('Stock/Index', [
            'products' => $products,
            'months' => $months,
            'filters' => [
                'search' => $search,
                'source' => $source,
                'months_back' => $validated['months_back'] ?? 6,
                'start_month' => $startMonth,
                'end_month' => $endMonth,
                'per_page' => (int) $perPage,
                'sort_column' => $sortColumn,
                'sort_direction' => $sortDirection,
            ],
            'sources' => [
                'Pro' => 'Pro',
                'Atacadista' => 'Atacadista',
            ],
        ]);
    }

    /**
     * Build the complex query to extract and aggregate products from JSON
     */
    private function buildStockQuery($source, $startMonth, $endMonth, $search = null, $filterProAtacadista = false, $paginate = true, $sortColumn = null, $sortDirection = 'asc')
    {
        $startDate = $startMonth . '-01';
        $endDate = $endMonth . '-' . date('t', strtotime($endMonth . '-01'));

        // Check if we're using MySQL or SQLite
        $isMySQL = DB::getDriverName() === 'mysql';
        
        if ($isMySQL) {
            return $this->buildMySQLQuery($source, $startDate, $endDate, $search, $filterProAtacadista, $paginate, $sortColumn, $sortDirection);
        } else {
            return $this->buildSQLiteQuery($source, $startDate, $endDate, $search, $filterProAtacadista, $paginate, $sortColumn, $sortDirection);
        }
    }

    /**
     * Build query for MySQL using JSON_TABLE
     */
    private function buildMySQLQuery($source, $startDate, $endDate, $search = null, $filterProAtacadista = false, $paginate = true, $sortColumn = null, $sortDirection = 'asc')
    {
        // Raw SQL to extract JSON data and aggregate from raw_order_data
        $rawQuery = "
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.produto.id')) as product_id,
                JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.codigo')) as product_code,
                JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.descricao')) as product_name,
                JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.quantidade')) as quantity,
                DATE_FORMAT(os.data, '%Y-%m') as month,
                os.source
            FROM order_summary os
            JOIN JSON_TABLE(os.raw_order_data, '$.itens[*]' COLUMNS (
                value JSON PATH '$'
            )) AS item
            WHERE os.data >= ? 
                AND os.data <= ?
                AND os.source IN ('Pro', 'Atacadista')
                " . ($source ? "AND os.source = ?" : "") . "
                " . ($search ? "AND (
                    LOWER(JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.descricao'))) LIKE LOWER(?) OR
                    LOWER(JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.codigo'))) LIKE LOWER(?)
                )" : "") . "
        ";

        $params = [$startDate, $endDate];
        if ($source) {
            $params[] = $source;
        }
        if ($search) {
            $searchPattern = '%' . $search . '%';
            $params[] = $searchPattern; // For product name
            $params[] = $searchPattern; // For product code/SKU
        }

        // Get the raw data
        $rawData = DB::select($rawQuery, $params);

        return $this->processRawData($rawData, $search, $paginate, $sortColumn, $sortDirection);
    }

    /**
     * Build query for SQLite using a simpler approach
     */
    private function buildSQLiteQuery($source, $startDate, $endDate, $search = null, $filterProAtacadista = false, $paginate = true, $sortColumn = null, $sortDirection = 'asc')
    {
        // For SQLite, we'll get all orders and process the JSON in PHP
        $query = DB::table('order_summary as os')
            ->whereNotNull('os.raw_order_data')
            ->where('os.data', '>=', $startDate)
            ->where('os.data', '<=', $endDate)
            ->whereIn('os.source', ['Pro', 'Atacadista']);

        if ($source) {
            $query->where('os.source', $source);
        }

        $orders = $query->select('os.data', 'os.source', 'os.raw_order_data')->get();

        $rawData = [];
        foreach ($orders as $order) {
            $month = \Carbon\Carbon::parse($order->data)->format('Y-m');
            
            // Decode raw_order_data
            $rawDataDecoded = json_decode($order->raw_order_data, true);
            
            if (isset($rawDataDecoded['itens']) && is_array($rawDataDecoded['itens'])) {
                foreach ($rawDataDecoded['itens'] as $item) {
                    // Apply search filter in PHP for SQLite - search in both name and SKU (case-insensitive)
                    if ($search) {
                        $searchLower = mb_strtolower($search);
                        $productName = mb_strtolower($item['descricao'] ?? '');
                        $productCode = mb_strtolower($item['codigo'] ?? '');
                        
                        if (stripos($productName, $searchLower) === false && stripos($productCode, $searchLower) === false) {
                            continue;
                        }
                    }

                    $rawData[] = (object) [
                        'product_id' => $item['produto']['id'] ?? null,
                        'product_code' => $item['codigo'] ?? null,
                        'product_name' => $item['descricao'] ?? null,
                        'quantity' => $item['quantidade'] ?? 0,
                        'month' => $month,
                        'source' => $order->source
                    ];
                }
            }
        }

        return $this->processRawData($rawData, $search, $paginate, $sortColumn, $sortDirection);
    }

    /**
     * Process raw data and return paginated results
     */
    private function processRawData($rawData, $search = null, $paginate = true, $sortColumn = null, $sortDirection = 'asc')
    {
        // Get hidden product keys for filtering
        $hiddenKeys = $this->getHiddenProductKeys();
        
        // Group by product and month
        $groupedData = [];
        foreach ($rawData as $row) {
            if (!$row->product_id || !$row->product_code) {
                continue;
            }
            
            // Filter out hidden products
            $productKey = $row->product_id . '_' . $row->source;
            if (in_array($productKey, $hiddenKeys)) {
                continue;
            }
            
            // Apply search filter - search in both name and SKU (case-insensitive)
            if ($search) {
                $searchLower = mb_strtolower($search);
                $productName = mb_strtolower($row->product_name ?? '');
                $productCode = mb_strtolower($row->product_code ?? '');
                
                if (stripos($productName, $searchLower) === false && stripos($productCode, $searchLower) === false) {
                    continue;
                }
            }
            
            $key = $row->product_id . '_' . $row->product_code;
            
            if (!isset($groupedData[$key])) {
                $groupedData[$key] = [
                    'product_id' => $row->product_id,
                    'product_code' => $row->product_code,
                    'product_name' => $row->product_name,
                    'source' => $row->source ?? 'Unknown',
                    'sales_data' => []
                ];
            }
            
            if (!isset($groupedData[$key]['sales_data'][$row->month])) {
                $groupedData[$key]['sales_data'][$row->month] = 0;
            }
            
            $groupedData[$key]['sales_data'][$row->month] += (int) $row->quantity;
        }

        // Convert to collection
        $collection = collect($groupedData)->map(function ($item) {
            $salesData = [];
            foreach ($item['sales_data'] as $month => $quantity) {
                $salesData[] = [
                    'month' => $month,
                    'total_quantity' => $quantity
                ];
            }
            
            return (object) [
                'product_id' => $item['product_id'],
                'product_code' => $item['product_code'],
                'product_name' => $item['product_name'],
                'source' => $item['source'],
                'sales_data' => json_encode($salesData)
            ];
        });

        // Apply sorting if specified
        if ($sortColumn && $sortColumn !== 'current_stock') {
            // Sort by month sales
            $collection = $collection->sort(function ($a, $b) use ($sortColumn, $sortDirection) {
                $salesA = json_decode($a->sales_data, true);
                $salesB = json_decode($b->sales_data, true);
                
                $valueA = 0;
                $valueB = 0;
                
                foreach ($salesA as $sale) {
                    if ($sale['month'] === $sortColumn) {
                        $valueA = $sale['total_quantity'];
                        break;
                    }
                }
                
                foreach ($salesB as $sale) {
                    if ($sale['month'] === $sortColumn) {
                        $valueB = $sale['total_quantity'];
                        break;
                    }
                }
                
                $result = $valueA <=> $valueB;
                return $sortDirection === 'desc' ? -$result : $result;
            })->values();
        } else {
            // Default sort by product name
            $collection = $collection->sortBy('product_name')->values();
        }

        // Return paginated or all results based on $paginate parameter
        if ($paginate) {
            // Manual pagination
            $page = request('page', 1);
            $perPage = request('per_page', 10);
            $offset = ($page - 1) * $perPage;
            
            $paginatedItems = $collection->slice($offset, $perPage);
            $total = $collection->count();
            
            // Create a paginator instance
            $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $paginatedItems,
                $total,
                $perPage,
                $page,
                [
                    'path' => request()->url(),
                    'pageName' => 'page',
                ]
            );
            
            $paginator->appends(request()->query());
            
            return $paginator;
        } else {
            // Return all results for export
            return $collection;
        }
    }

    /**
     * Export stock data to CSV
     */
    public function export(
        Request $request,
        CollmeProBlingService $pro,
        CollmeAtacadistaBlingService $atacadista
    ) {
        // Validate request parameters (same as index)
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'source' => 'nullable|in:Pro,Atacadista',
            'months_back' => 'nullable|integer|min:1|max:60',
            'start_month' => 'nullable|date_format:Y-m',
            'end_month' => 'nullable|date_format:Y-m|after_or_equal:start_month',
            'per_page' => 'nullable|integer|min:10|max:100',
            'page' => 'nullable|integer|min:1',
            'export_type' => 'nullable|in:page,all',
        ]);

        // Set default values
        $source = $validated['source'] ?? null;
        $search = $validated['search'] ?? null;
        $exportType = $validated['export_type'] ?? 'all'; // 'page' or 'all'
        
        // Calculate date range based on months_back or use provided dates
        if (isset($validated['months_back'])) {
            $monthsBack = $validated['months_back'];
            $startMonth = now()->subMonths($monthsBack - 1)->format('Y-m');
            $endMonth = now()->format('Y-m');
        } else {
            $startMonth = $validated['start_month'] ?? now()->subMonths(5)->format('Y-m');
            $endMonth = $validated['end_month'] ?? now()->format('Y-m');
        }

        // Get products based on export type
        if ($exportType === 'page') {
            // Export only current page
            $products = $this->buildStockQuery($source, $startMonth, $endMonth, $search, true, true, null, 'asc');
            $products = $products->items(); // Convert to collection of items
        } else {
            // Export all results
            $products = $this->buildStockQuery($source, $startMonth, $endMonth, $search, true, false, null, 'asc');
        }

        // Get available months for the CSV headers
        $months = $this->getAvailableMonths($startMonth, $endMonth);

        // Fetch stock for all products
        $productStocks = $this->fetchProductStocksForExport($products, $pro, $atacadista);

        // Transform the data for CSV
        $csvData = [];
        foreach ($products as $product) {
            // Initialize all months with 0
            $salesByMonth = [];
            foreach ($months as $month) {
                $salesByMonth[$month] = 0;
            }
            
            // Fill in actual sales data
            if ($product->sales_data) {
                foreach (json_decode($product->sales_data, true) as $sale) {
                    $month = $sale['month'];
                    $salesByMonth[$month] = (int) $sale['total_quantity'];
                }
            }

            // Get stock for this product (convert to int to match array key)
            $productId = (int) $product->product_id;
            $stock = $productStocks[$productId] ?? 0;

            $row = [
                'Produto' => $product->product_name,
                'Código' => $product->product_code,
                'Sistema' => $product->source ?? 'Unknown',
                'Estoque Atual' => $stock,
            ];

            // Add monthly sales data
            foreach ($months as $month) {
                $monthName = $this->formatMonthForCSV($month);
                $row[$monthName] = $salesByMonth[$month];
            }

            $csvData[] = $row;
        }

        // Generate CSV content
        $csvContent = $this->arrayToCsv($csvData);

        // Generate filename with date range and export type
        $exportTypeSuffix = $exportType === 'page' ? '_pagina' : '_todos';
        $filename = 'estoque_' . $startMonth . '_a_' . $endMonth . $exportTypeSuffix . '_' . now()->format('Y-m-d_H-i-s') . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Fetch product stocks for page display
     * Groups products by source and fetches stocks from Bling API
     */
    private function fetchProductStocksForPage(
        $products,
        CollmeProBlingService $pro,
        CollmeAtacadistaBlingService $atacadista
    ): array {
        // Group products by source
        $productsBySource = [
            'Pro' => [],
            'Atacadista' => []
        ];
        
        foreach ($products as $product) {
            if ($product->source && $product->product_id) {
                $source = $product->source;
                if (isset($productsBySource[$source])) {
                    $productsBySource[$source][] = (int) $product->product_id;
                }
            }
        }
        
        // Fetch stocks for each source
        $allStocks = [];
        
        foreach ($productsBySource as $source => $ids) {
            if (empty($ids)) {
                continue;
            }
            
            // Remove duplicate IDs and reindex
            $ids = array_values(array_unique($ids));
            
            // Throttle to protect upstream
            $rateKey = 'stock_page:' . $source;
            if (!RateLimiter::attempt($rateKey, $perMinute = 30, function () {})) {
                continue; // Skip this source if rate limited
            }
            
            $service = $source === 'Pro' ? $pro : $atacadista;
            
            // Use cache similar to API endpoint
            $cacheTtl = (int) config('bling.defaults.stock_ttl', 1800); // 30 minutes
            $cacheKey = "stock_by_ids:{$source}:" . md5(implode(',', $ids));
            
            try {
                $stocks = Cache::remember($cacheKey, $cacheTtl, function () use ($service, $ids) {
                    return $service->getProductStockByIds($ids);
                });
                
                // Merge stocks into allStocks array
                foreach ($stocks as $productId => $stock) {
                    $allStocks[$productId] = $stock;
                }
            } catch (\Exception $e) {
                // Log error but continue with other sources
                Log::error("Failed to fetch stock for page - source: {$source}", [
                    'error' => $e->getMessage(),
                    'product_ids_count' => count($ids)
                ]);
            }
        }
        
        return $allStocks;
    }

    /**
     * Fetch product stocks for export
     * Groups products by source and fetches stocks from Bling API
     */
    private function fetchProductStocksForExport(
        $products,
        CollmeProBlingService $pro,
        CollmeAtacadistaBlingService $atacadista
    ): array {
        // Group products by source
        $productsBySource = [
            'Pro' => [],
            'Atacadista' => []
        ];
        
        foreach ($products as $product) {
            if ($product->source && $product->product_id) {
                $source = $product->source;
                if (isset($productsBySource[$source])) {
                    $productsBySource[$source][] = (int) $product->product_id;
                }
            }
        }
        
        // Fetch stocks for each source
        $allStocks = [];
        
        foreach ($productsBySource as $source => $ids) {
            if (empty($ids)) {
                continue;
            }
            
            // Remove duplicate IDs and reindex
            $ids = array_values(array_unique($ids));
            
            // Throttle to protect upstream
            $rateKey = 'stock_export:' . $source;
            if (!RateLimiter::attempt($rateKey, $perMinute = 30, function () {})) {
                continue; // Skip this source if rate limited
            }
            
            $service = $source === 'Pro' ? $pro : $atacadista;
            
            // Use cache similar to API endpoint
            $cacheTtl = (int) config('bling.defaults.stock_ttl', 1800); // 30 minutes
            $cacheKey = "stock_by_ids:{$source}:" . md5(implode(',', $ids));
            
            try {
                $stocks = Cache::remember($cacheKey, $cacheTtl, function () use ($service, $ids) {
                    return $service->getProductStockByIds($ids);
                });
                
                // Merge stocks into allStocks array
                foreach ($stocks as $productId => $stock) {
                    $allStocks[$productId] = $stock;
                }
            } catch (\Exception $e) {
                // Log error but continue with other sources
                Log::error("Failed to fetch stock for export - source: {$source}", [
                    'error' => $e->getMessage(),
                    'product_ids_count' => count($ids)
                ]);
            }
        }
        
        return $allStocks;
    }

    /**
     * Get available months for the date range
     */
    private function getAvailableMonths($startMonth, $endMonth)
    {
        $months = [];
        $current = \Carbon\Carbon::createFromFormat('Y-m', $startMonth);
        $end = \Carbon\Carbon::createFromFormat('Y-m', $endMonth);

        while ($current->lte($end)) {
            $months[] = $current->format('Y-m');
            $current->addMonth();
        }

        return array_reverse($months); // Most recent first
    }

    /**
     * Format month for CSV header
     */
    private function formatMonthForCSV($monthString)
    {
        $monthNames = [
            'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun',
            'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'
        ];
        
        $parts = explode('-', $monthString);
        $year = $parts[0];
        $month = (int) $parts[1];
        
        return $monthNames[$month - 1] . '/' . $year;
    }


    /**
     * Convert array to CSV string
     */
    private function arrayToCsv($data)
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Add headers
        fputcsv($output, array_keys($data[0]), ';');
        
        // Add data rows
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Get all unique products from order_summary for the product selector
     * Only accessible by admin or developer
     */
    public function getAllProducts(Request $request)
    {
        // Check if user is admin or developer
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isDeveloper())) {
            return response()->json([
                'error' => 'Access denied. Only admin and developer can access this.'
            ], 403);
        }

        $isMySQL = DB::getDriverName() === 'mysql';

        try {
            if ($isMySQL) {
                // MySQL query to extract unique products (grouped by product_id + source)
                $products = DB::select("
                    SELECT 
                        JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.produto.id')) as product_id,
                        JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.codigo')) as product_code,
                        JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.descricao')) as product_name,
                        os.source
                    FROM order_summary os
                    JOIN JSON_TABLE(os.raw_order_data, '$.itens[*]' COLUMNS (
                        value JSON PATH '$'
                    )) AS item
                    WHERE os.source IN ('Pro', 'Atacadista')
                        AND JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.produto.id')) IS NOT NULL
                        AND JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.codigo')) IS NOT NULL
                    GROUP BY 
                        JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.produto.id')),
                        os.source,
                        JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.codigo')),
                        JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.descricao'))
                    ORDER BY os.source, product_name
                ");
            } else {
                // SQLite: get all orders and process in PHP
                $orders = DB::table('order_summary')
                    ->whereIn('source', ['Pro', 'Atacadista'])
                    ->whereNotNull('raw_order_data')
                    ->select('source', 'raw_order_data')
                    ->get();

                $productsMap = [];
                foreach ($orders as $order) {
                    $rawData = json_decode($order->raw_order_data, true);
                    if (isset($rawData['itens']) && is_array($rawData['itens'])) {
                        foreach ($rawData['itens'] as $item) {
                            $productId = $item['produto']['id'] ?? null;
                            $productCode = $item['codigo'] ?? null;
                            $productName = $item['descricao'] ?? null;

                            if ($productId && $productCode) {
                                $key = $productId . '_' . $order->source;
                                if (!isset($productsMap[$key])) {
                                    $productsMap[$key] = (object) [
                                        'product_id' => (string) $productId,
                                        'product_code' => $productCode,
                                        'product_name' => $productName,
                                        'source' => $order->source,
                                    ];
                                }
                            }
                        }
                    }
                }
                $products = array_values($productsMap);
            }

            // Convert to array format and deduplicate by product_id + source
            $productsMap = [];
            foreach ($products as $product) {
                $key = (int) $product->product_id . '_' . ($product->source ?? 'Unknown');
                if (!isset($productsMap[$key])) {
                    $productsMap[$key] = [
                        'product_id' => (int) $product->product_id,
                        'product_code' => $product->product_code ?? '',
                        'product_name' => $product->product_name ?? '',
                        'source' => $product->source ?? 'Unknown',
                    ];
                }
            }

            // Sort by source and product name
            $result = collect($productsMap)->values()->sortBy(function ($product) {
                return $product['source'] . '_' . strtolower($product['product_name']);
            })->values()->toArray();

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Error fetching all products for stock modal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Erro ao carregar produtos. Tente novamente mais tarde.'
            ], 500);
        }
    }

    /**
     * Get list of hidden products
     * Only accessible by admin or developer
     */
    public function getHiddenProducts(Request $request)
    {
        // Check if user is admin or developer
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isDeveloper())) {
            return response()->json([
                'error' => 'Access denied. Only admin and developer can access this.'
            ], 403);
        }

        $hiddenProducts = HiddenStockProduct::orderBy('source')
            ->orderBy('product_name')
            ->get()
            ->map(function ($product) {
                return [
                    'product_id' => $product->product_id,
                    'source' => $product->source,
                    'product_name' => $product->product_name,
                    'product_code' => $product->product_code,
                ];
            })
            ->toArray();

        return response()->json($hiddenProducts);
    }

    /**
     * Save hidden products
     * Only accessible by admin or developer
     */
    public function saveHiddenProducts(Request $request)
    {
        // Check if user is admin or developer
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && !$user->isDeveloper())) {
            return response()->json([
                'error' => 'Access denied. Only admin and developer can access this.'
            ], 403);
        }

        // Get products from request - can be empty array
        $products = $request->input('products', []);
        
        // Ensure products is an array
        if (!is_array($products)) {
            return response()->json([
                'error' => 'O campo produtos deve ser um array.',
            ], 422);
        }
        
        // Validate each product if array is not empty
        if (!empty($products)) {
            $validated = $request->validate([
                'products' => 'array',
                'products.*.product_id' => 'required|integer',
                'products.*.source' => 'required|in:Pro,Atacadista',
                'products.*.product_name' => 'nullable|string|max:255',
                'products.*.product_code' => 'nullable|string|max:255',
            ], [
                'products.array' => 'O campo produtos deve ser um array.',
                'products.*.product_id.required' => 'O ID do produto é obrigatório.',
                'products.*.product_id.integer' => 'O ID do produto deve ser um número inteiro.',
                'products.*.source.required' => 'A fonte do produto é obrigatória.',
                'products.*.source.in' => 'A fonte do produto deve ser Pro ou Atacadista.',
            ]);
            $validatedProducts = $validated['products'];
        } else {
            // Empty array is valid - means no products should be hidden
            $validatedProducts = [];
        }

        // Delete all existing hidden products
        HiddenStockProduct::truncate();

        // Insert new hidden products (if any)
        $productsToInsert = [];
        if (!empty($validatedProducts)) {
            foreach ($validatedProducts as $product) {
                $productsToInsert[] = [
                    'product_id' => $product['product_id'],
                    'source' => $product['source'],
                    'product_name' => $product['product_name'] ?? null,
                    'product_code' => $product['product_code'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            HiddenStockProduct::insert($productsToInsert);
        }

        return response()->json([
            'success' => true,
            'message' => 'Produtos ocultos salvos com sucesso.',
            'count' => count($productsToInsert),
        ]);
    }

    /**
     * Get hidden product keys for filtering (product_id_source format)
     */
    private function getHiddenProductKeys(): array
    {
        return HiddenStockProduct::all()
            ->map(function ($product) {
                return $product->product_id . '_' . $product->source;
            })
            ->toArray();
    }
}
