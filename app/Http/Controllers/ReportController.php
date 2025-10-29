<?php

namespace App\Http\Controllers;

use App\Models\OrderSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportController extends Controller
{
    /**
     * Display the sales report page
     */
    public function sales(Request $request)
    {
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
        $perPage = $validated['per_page'] ?? 25;
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
        $products = $this->buildSalesReportQuery($source, $startMonth, $endMonth, $search, true);

        // Get available months for the table headers
        $months = $this->getAvailableMonths($startMonth, $endMonth);

        // Transform the data for the frontend
        $transformedProducts = $products->through(function ($product) use ($months) {
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
                'current_stock' => 0, // Hardcoded as requested
                'sales_by_month' => $salesByMonth,
            ];
        });

        return Inertia::render('Reports/Sales', [
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
    private function buildSalesReportQuery($source, $startMonth, $endMonth, $search = null, $filterProAtacadista = false)
    {
        $startDate = $startMonth . '-01';
        $endDate = $endMonth . '-' . date('t', strtotime($endMonth . '-01'));

        // Check if we're using MySQL or SQLite
        $isMySQL = DB::getDriverName() === 'mysql';
        
        if ($isMySQL) {
            return $this->buildMySQLQuery($source, $startDate, $endDate, $search, $filterProAtacadista);
        } else {
            return $this->buildSQLiteQuery($source, $startDate, $endDate, $search, $filterProAtacadista);
        }
    }

    /**
     * Build query for MySQL using JSON_TABLE
     */
    private function buildMySQLQuery($source, $startDate, $endDate, $search = null, $filterProAtacadista = false)
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

        return $this->processRawData($rawData, $search);
    }

    /**
     * Build query for SQLite using a simpler approach
     */
    private function buildSQLiteQuery($source, $startDate, $endDate, $search = null, $filterProAtacadista = false)
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

        return $this->processRawData($rawData, $search);
    }

    /**
     * Process raw data and return paginated results
     */
    private function processRawData($rawData, $search = null)
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
        });

        // Manual pagination
        $page = request('page', 1);
        $perPage = request('per_page', 25);
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
}
