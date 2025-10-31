<?php

namespace App\Http\Controllers;

use App\Models\OrderSummary;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
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

        // Get paginated results - always filter to show only Pro and Atacadista
        $products = $this->buildStockQuery($source, $startMonth, $endMonth, $search, true, true);

        // Get available months for the table headers
        $months = $this->getAvailableMonths($startMonth, $endMonth);

        // Fetch stocks for all products in the current page
        $productStocks = $this->fetchProductStocksForPage($products->items(), $pro, $atacadista);

        // Transform the data for the frontend
        $transformedProducts = $products->through(function ($product) use ($months, $productStocks) {
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

            // Get stock for this product (convert to int to match array key)
            $productId = (int) $product->product_id;
            $stock = $productStocks[$productId] ?? 0;

            return [
                'product_id' => $product->product_id,
                'product_code' => $product->product_code,
                'product_name' => $product->product_name,
                'source' => $product->source ?? 'Unknown',
                'current_stock' => $stock,
                'sales_by_month' => $salesByMonth,
            ];
        });

        return Inertia::render('Stock/Index', [
            'products' => $transformedProducts,
            'months' => $months,
            'filters' => [
                'search' => $search,
                'source' => $source,
                'months_back' => $validated['months_back'] ?? 6,
                'start_month' => $startMonth,
                'end_month' => $endMonth,
                'per_page' => (int) $perPage,
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
    private function buildStockQuery($source, $startMonth, $endMonth, $search = null, $filterProAtacadista = false, $paginate = true)
    {
        $startDate = $startMonth . '-01';
        $endDate = $endMonth . '-' . date('t', strtotime($endMonth . '-01'));

        // Check if we're using MySQL or SQLite
        $isMySQL = DB::getDriverName() === 'mysql';
        
        if ($isMySQL) {
            return $this->buildMySQLQuery($source, $startDate, $endDate, $search, $filterProAtacadista, $paginate);
        } else {
            return $this->buildSQLiteQuery($source, $startDate, $endDate, $search, $filterProAtacadista, $paginate);
        }
    }

    /**
     * Build query for MySQL using JSON_TABLE
     */
    private function buildMySQLQuery($source, $startDate, $endDate, $search = null, $filterProAtacadista = false, $paginate = true)
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
                " . ($search ? "AND JSON_UNQUOTE(JSON_EXTRACT(item.value, '$.descricao')) LIKE ?" : "") . "
        ";

        $params = [$startDate, $endDate];
        if ($source) {
            $params[] = $source;
        }
        if ($search) {
            $params[] = '%' . $search . '%';
        }

        // Get the raw data
        $rawData = DB::select($rawQuery, $params);

        return $this->processRawData($rawData, $search, $paginate);
    }

    /**
     * Build query for SQLite using a simpler approach
     */
    private function buildSQLiteQuery($source, $startDate, $endDate, $search = null, $filterProAtacadista = false, $paginate = true)
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
                    // Apply search filter in PHP for SQLite
                    if ($search && stripos($item['descricao'] ?? '', $search) === false) {
                        continue;
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

        return $this->processRawData($rawData, $search, $paginate);
    }

    /**
     * Process raw data and return paginated results
     */
    private function processRawData($rawData, $search = null, $paginate = true)
    {
        // Group by product and month
        $groupedData = [];
        foreach ($rawData as $row) {
            if (!$row->product_id || !$row->product_code) {
                continue;
            }
            
            // Apply search filter
            if ($search && stripos($row->product_name, $search) === false) {
                continue;
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

        // Convert to collection for pagination
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
        })->sortBy('product_name')->values();

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
            $products = $this->buildStockQuery($source, $startMonth, $endMonth, $search, true, true);
            $products = $products->items(); // Convert to collection of items
        } else {
            // Export all results
            $products = $this->buildStockQuery($source, $startMonth, $endMonth, $search, true, false);
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
}
