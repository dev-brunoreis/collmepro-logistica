<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TrackingHistory;
use App\Jobs\UpdateTrackingStatusJob;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * TrackingService - Unified tracking system
 * 
 * Manages tracking information from multiple sources
 * Currently integrated with Bling Logistics API
 */
class TrackingService
{
    private BlingLogisticsService $blingLogisticsService;

    public function __construct(BlingLogisticsService $blingLogisticsService)
    {
        $this->blingLogisticsService = $blingLogisticsService;
    }

    /**
     * Get tracking information for an order
     */
    public function getOrderTracking(Order $order): ?array
    {
        if (!$order->tracking_code) {
            return null;
        }

        try {
            $trackingInfo = $this->blingLogisticsService->getTrackingInfo($order->tracking_code);
            
            if ($trackingInfo) {
                // Update order with latest tracking status
                $this->updateOrderTrackingStatus($order, $trackingInfo);
                
                return [
                    'tracking_code' => $order->tracking_code,
                    'status' => $trackingInfo['situacao'] ?? 'unknown',
                    'status_description' => $this->getStatusDescription($trackingInfo['situacao'] ?? 'unknown'),
                    'logistics_provider' => $trackingInfo['transportadora'] ?? $order->logistics_provider,
                    'last_update' => $trackingInfo['dataAtualizacao'] ?? null,
                    'estimated_delivery' => $trackingInfo['dataPrevistaEntrega'] ?? null,
                    'history' => $this->getTrackingHistory($order)
                ];
            }

            return null;
        } catch (Exception $e) {
            Log::error('Failed to get order tracking', [
                'order_id' => $order->id,
                'tracking_code' => $order->tracking_code,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get tracking history for an order
     */
    public function getTrackingHistory(Order $order): array
    {
        if (!$order->tracking_code) {
            return [];
        }

        try {
            // Get from database first
            $history = TrackingHistory::where('order_id', $order->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->toArray();

            // If no history in database, try to fetch from Bling
            if (empty($history)) {
                $this->updateTrackingHistoryFromBling($order);
                $history = TrackingHistory::where('order_id', $order->id)
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->toArray();
            }

            return $history;
        } catch (Exception $e) {
            Log::error('Failed to get tracking history', [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Update tracking status for an order
     */
    public function updateOrderTrackingStatus(Order $order, array $trackingInfo): void
    {
        try {
            $status = $trackingInfo['situacao'] ?? null;
            $logisticsProvider = $trackingInfo['transportadora'] ?? null;
            $lastUpdate = $trackingInfo['dataAtualizacao'] ?? null;

            $updateData = [];
            
            if ($status) {
                $updateData['tracking_status'] = $status;
            }
            
            if ($logisticsProvider) {
                $updateData['logistics_provider'] = $logisticsProvider;
            }

            if (!empty($updateData)) {
                $order->update($updateData);
                
                Log::info('Updated order tracking status', [
                    'order_id' => $order->id,
                    'tracking_code' => $order->tracking_code,
                    'status' => $status,
                    'logistics_provider' => $logisticsProvider
                ]);
            }

            // Update tracking history
            if ($lastUpdate) {
                $this->updateTrackingHistoryFromBling($order);
            }
        } catch (Exception $e) {
            Log::error('Failed to update order tracking status', [
                'order_id' => $order->id,
                'tracking_code' => $order->tracking_code,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update tracking history from Bling API
     */
    public function updateTrackingHistoryFromBling(Order $order): void
    {
        if (!$order->tracking_code) {
            return;
        }

        try {
            // First get the package ID
            $packages = $this->blingLogisticsService->fetchPackages([
                'codigoRastreamento' => $order->tracking_code,
                'limite' => 1
            ]);

            if (empty($packages)) {
                return;
            }

            $packageId = $packages[0]['id'];
            $history = $this->blingLogisticsService->getTrackingHistory($packageId);

            foreach ($history as $event) {
                $this->createTrackingHistoryRecord($order, $event);
            }
        } catch (Exception $e) {
            Log::error('Failed to update tracking history from Bling', [
                'order_id' => $order->id,
                'tracking_code' => $order->tracking_code,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create tracking history record
     */
    private function createTrackingHistoryRecord(Order $order, array $event): void
    {
        try {
            // Check if record already exists
            $exists = TrackingHistory::where('order_id', $order->id)
                ->where('event_date', $event['data'] ?? null)
                ->where('event_description', $event['descricao'] ?? null)
                ->exists();

            if (!$exists) {
                TrackingHistory::create([
                    'order_id' => $order->id,
                    'event_date' => $event['data'] ?? null,
                    'event_description' => $event['descricao'] ?? null,
                    'event_location' => $event['local'] ?? null,
                    'event_status' => $event['situacao'] ?? null,
                    'raw_data' => $event
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to create tracking history record', [
                'order_id' => $order->id,
                'event' => $event,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Schedule tracking updates for orders
     */
    public function scheduleTrackingUpdates(array $orderIds = []): void
    {
        try {
            $query = Order::whereNotNull('tracking_code');
            
            if (!empty($orderIds)) {
                $query->whereIn('id', $orderIds);
            }

            $orders = $query->get();

            foreach ($orders as $order) {
                UpdateTrackingStatusJob::dispatch($order);
            }

            Log::info('Scheduled tracking updates', [
                'orders_count' => $orders->count(),
                'order_ids' => $orderIds
            ]);
        } catch (Exception $e) {
            Log::error('Failed to schedule tracking updates', [
                'error' => $e->getMessage(),
                'order_ids' => $orderIds
            ]);
        }
    }

    /**
     * Get status description in Portuguese
     */
    private function getStatusDescription(string $status): string
    {
        $descriptions = [
            'postado' => 'Postado',
            'em_transito' => 'Em Trânsito',
            'saiu_para_entrega' => 'Saiu para Entrega',
            'entregue' => 'Entregue',
            'nao_entregue' => 'Não Entregue',
            'devolvido' => 'Devolvido',
            'extraviado' => 'Extraviado',
            'pendente' => 'Pendente',
            'processando' => 'Processando',
            'coletado' => 'Coletado',
            'unknown' => 'Status Desconhecido'
        ];

        return $descriptions[$status] ?? $status;
    }

    /**
     * Get tracking statistics
     */
    public function getTrackingStatistics(): array
    {
        try {
            $totalOrders = Order::whereNotNull('tracking_code')->count();
            
            $statusCounts = Order::whereNotNull('tracking_code')
                ->selectRaw('tracking_status, COUNT(*) as count')
                ->groupBy('tracking_status')
                ->pluck('count', 'tracking_status')
                ->toArray();

            $providerCounts = Order::whereNotNull('logistics_provider')
                ->selectRaw('logistics_provider, COUNT(*) as count')
                ->groupBy('logistics_provider')
                ->pluck('count', 'logistics_provider')
                ->toArray();

            return [
                'total_tracked_orders' => $totalOrders,
                'status_distribution' => $statusCounts,
                'provider_distribution' => $providerCounts,
                'last_updated' => now()
            ];
        } catch (Exception $e) {
            Log::error('Failed to get tracking statistics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }
}
