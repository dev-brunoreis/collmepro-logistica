<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\OrderSummary;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MetricsController extends Controller
{
    /**
     * Get system metrics with filters
     */
    public function index(Request $request): JsonResponse
    {
        // Validate User-Agent
        if ($request->header('User-Agent') !== 'DevHub-MetricsCollector/1.0') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validate all input parameters to prevent SQL injection
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['day', 'week', 'month', 'year', 'custom'])],
            'start_date' => ['nullable', 'date', 'before_or_equal:end_date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'source' => ['nullable', Rule::in(['collmepro', 'collmepro_atacadista', 'collme_beauty'])],
            'include_orders' => ['nullable', 'boolean'],
            'include_invoices' => ['nullable', 'boolean'],
            'include_tracking' => ['nullable', 'boolean'],
            'include_users' => ['nullable', 'boolean'],
        ]);

        // Set date range based on period
        $dateRange = $this->getDateRange($validated);

        $metrics = [];

        // Orders metrics
        if ($validated['include_orders'] ?? true) {
            $metrics['orders'] = $this->getOrdersMetrics($dateRange, $validated['source'] ?? null);
        }

        // Invoices metrics
        if ($validated['include_invoices'] ?? true) {
            $metrics['invoices'] = $this->getInvoicesMetrics($dateRange);
        }

        // Tracking metrics
        if ($validated['include_tracking'] ?? true) {
            $metrics['tracking'] = $this->getTrackingMetrics($dateRange);
        }

        // Users metrics
        if ($validated['include_users'] ?? true) {
            $metrics['users'] = $this->getUsersMetrics();
        }

        $metrics['period'] = [
            'start' => $dateRange['start']->toDateString(),
            'end' => $dateRange['end']->toDateString(),
        ];

        return response()->json($metrics);
    }

    /**
     * Get date range based on period
     */
    private function getDateRange(array $validated): array
    {
        $period = $validated['period'] ?? 'month';

        if ($period === 'custom' && isset($validated['start_date']) && isset($validated['end_date'])) {
            return [
                'start' => Carbon::parse($validated['start_date'])->startOfDay(),
                'end' => Carbon::parse($validated['end_date'])->endOfDay(),
            ];
        }

        $end = Carbon::now()->endOfDay();
        
        $start = match($period) {
            'day' => Carbon::now()->startOfDay(),
            'week' => Carbon::now()->subWeek()->startOfDay(),
            'month' => Carbon::now()->subMonth()->startOfDay(),
            'year' => Carbon::now()->subYear()->startOfDay(),
            default => Carbon::now()->subMonth()->startOfDay(),
        };

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Get orders metrics with safe queries
     */
    private function getOrdersMetrics(array $dateRange, ?string $source): array
    {
        $query = OrderSummary::whereBetween('data', [$dateRange['start'], $dateRange['end']]);

        if ($source) {
            $query->where('source', $source);
        }

        $total = $query->count();
        $cancelled = (clone $query)->where('situacao_id', 12)->count();
        $active = $total - $cancelled;

        // Revenue
        $revenue = (clone $query)
            ->where('situacao_id', '!=', 12)
            ->sum('total');

        // Orders by status (top statuses)
        $byStatus = (clone $query)
            ->select('situacao_id', DB::raw('count(*) as count'))
            ->groupBy('situacao_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(fn($item) => [
                'status_id' => $item->situacao_id,
                'status_name' => $this->getStatusName($item->situacao_id),
                'count' => $item->count
            ]);

        // Orders by source
        $bySource = (clone $query)
            ->select('source', DB::raw('count(*) as count'))
            ->groupBy('source')
            ->get()
            ->mapWithKeys(fn($item) => [$item->source => $item->count]);

        // Orders with tracking
        $withTracking = (clone $query)
            ->whereNotNull('tracking_code')
            ->where('tracking_code', '!=', '')
            ->count();

        // Orders by day (for charts)
        $byDay = (clone $query)
            ->select(
                DB::raw('DATE(data) as date'),
                DB::raw('count(*) as count')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'count' => $item->count
            ]);

        return [
            'total' => $total,
            'active' => $active,
            'cancelled' => $cancelled,
            'revenue' => (float) $revenue,
            'with_tracking' => $withTracking,
            'by_status' => $byStatus,
            'by_source' => $bySource,
            'by_day' => $byDay,
        ];
    }

    /**
     * Get invoices metrics with safe queries
     */
    private function getInvoicesMetrics(array $dateRange): array
    {
        $query = Invoice::whereBetween('data_emissao', [$dateRange['start'], $dateRange['end']]);

        $total = $query->count();
        
        $totalValue = (clone $query)->sum('valor_nota');
        
        $totalFreight = (clone $query)->sum('valor_frete');

        // Invoices by day
        $byDay = (clone $query)
            ->select(
                DB::raw('DATE(data_emissao) as date'),
                DB::raw('count(*) as count'),
                DB::raw('sum(valor_nota) as value')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn($item) => [
                'date' => $item->date,
                'count' => $item->count,
                'value' => (float) $item->value
            ]);

        return [
            'total' => $total,
            'total_value' => (float) $totalValue,
            'total_freight' => (float) $totalFreight,
            'by_day' => $byDay,
        ];
    }

    /**
     * Get tracking metrics with safe queries
     */
    private function getTrackingMetrics(array $dateRange): array
    {
        $ordersWithTracking = OrderSummary::whereBetween('data', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('tracking_code')
            ->where('tracking_code', '!=', '')
            ->count();

        $ordersWithoutTracking = OrderSummary::whereBetween('data', [$dateRange['start'], $dateRange['end']])
            ->where('situacao_id', '!=', 12) // Exclude cancelled
            ->where(function($query) {
                $query->whereNull('tracking_code')
                    ->orWhere('tracking_code', '');
            })
            ->count();

        // Tracking by carrier
        $byCarrier = OrderSummary::whereBetween('data', [$dateRange['start'], $dateRange['end']])
            ->whereNotNull('tracking_code')
            ->where('tracking_code', '!=', '')
            ->select('logistics_provider', DB::raw('count(*) as count'))
            ->groupBy('logistics_provider')
            ->get()
            ->mapWithKeys(fn($item) => [$item->logistics_provider ?? 'Unknown' => $item->count]);

        return [
            'with_tracking' => $ordersWithTracking,
            'without_tracking' => $ordersWithoutTracking,
            'by_carrier' => $byCarrier,
        ];
    }

    /**
     * Get users metrics with safe queries
     */
    private function getUsersMetrics(): array
    {
        $total = User::count();
        
        $byRole = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->get()
            ->mapWithKeys(fn($item) => [$item->role => $item->count]);

        $active = User::whereNotNull('email_verified_at')->count();

        return [
            'total' => $total,
            'active' => $active,
            'by_role' => $byRole,
        ];
    }

    /**
     * Get status name from situacao_id (Bling status ID)
     */
    private function getStatusName(?int $situacaoId): string
    {
        if ($situacaoId === null) {
            return 'Status desconhecido';
        }

        // Mapeamento de situacao_id do Bling para nomes legíveis
        $statusMap = [
            6 => 'Em aberto',
            9 => 'Atendido',
            12 => 'Cancelado',
            15 => 'Em andamento',
            19 => 'Venda agenciada',
            24 => 'Em digitação',
            84765 => 'Verificado',
        ];

        return $statusMap[$situacaoId] ?? "Status {$situacaoId}";
    }
}

