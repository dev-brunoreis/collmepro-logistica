<?php

namespace App\Jobs;

use App\Events\TrackingSynced;
use App\Models\Invoice;
use App\Models\OrderSummary;
use App\Models\Tracking;
use App\Services\BlingService;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use App\Services\OrderHistoryService;
use App\Traits\TrackingLogger;
use App\Traits\BroadcastsOrderUpdates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ExtractTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TrackingLogger, BroadcastsOrderUpdates;

    public int $timeout = 45;
    public int $tries = 3;
    public array $backoff = [60, 300]; // 1min, 5min

    private int $orderSummaryId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderSummaryId)
    {
        $this->orderSummaryId = $orderSummaryId;
        $this->onQueue('tracking');
    }

    /**
     * Get unique ID for idempotency.
     */
    public function uniqueId(): string
    {
        return "extract-tracking-{$this->orderSummaryId}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = "tracking_extracted_{$this->orderSummaryId}";

        // Idempotency check: Skip if processed recently (within 5 minutes)
        if (Cache::has($cacheKey)) {
            $this->logTrackingInfo('Tracking extraction skipped (already processed recently)', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        $order = OrderSummary::with('invoice')->find($this->orderSummaryId);

        if (!$order) {
            $this->logTrackingError('Order not found for tracking extraction', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        if (!$order->invoice) {
            $this->logTrackingDebug('Order has no invoice for tracking extraction', [
                'order_id' => $order->id,
            ]);
            return;
        }

        $this->logTrackingInfo('Starting tracking extraction', [
            'order_id' => $order->id,
            'order_number' => $order->numero,
            'invoice_id' => $order->invoice->id,
        ]);

        try {
            // Get the appropriate Bling service based on order source
            $blingService = $this->getBlingService($order->source);

            // Get complete invoice data
            $completeInvoiceData = $blingService->getCompleteInvoiceData($order->invoice->bling_id);

            if (!$completeInvoiceData) {
                $this->logTrackingWarning('Failed to fetch invoice data for tracking', [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice->id,
                ]);
                return;
            }

            // Check for tracking data in invoice transport volumes
            $volumes = $completeInvoiceData['transporte']['volumes'] ?? [];

            if (empty($volumes)) {
                $this->logTrackingDebug('Invoice has no volumes for tracking', [
                    'order_id' => $order->id,
                    'invoice_id' => $order->invoice->id,
                ]);
                // Mark as processed to avoid constant re-checking
                Cache::put($cacheKey, now(), 300); // 5 minutes
                return;
            }

            $volumeId = $volumes[0]['id'] ?? null;

            if (!$volumeId) {
                $this->logTrackingDebug('Volume has no ID', [
                    'order_id' => $order->id,
                ]);
                Cache::put($cacheKey, now(), 300);
                return;
            }

            // Get complete tracking data
            $completeTrackingData = $blingService->getCompleteTrackingData($volumeId);

            if (!$completeTrackingData) {
                $this->logTrackingWarning('Failed to fetch tracking data', [
                    'order_id' => $order->id,
                    'volume_id' => $volumeId,
                ]);
                return;
            }

            $trackingCode = $completeTrackingData['rastreamento']['codigo'] ?? null;

            // Check if tracking already exists
            $existingTracking = Tracking::where('invoice_id', $order->invoice->id)
                ->where('volume_id', $volumeId)
                ->first();
            $wasCreated = !$existingTracking;
            $oldTrackingCode = $existingTracking?->codigo_rastreamento ?? null;

            DB::transaction(function () use ($order, $completeTrackingData, $completeInvoiceData, $volumeId, $trackingCode, $wasCreated, $oldTrackingCode) {
                // Update order with tracking summary
                $order->update([
                    'tracking_code' => $trackingCode,
                    'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                    'service_type' => $completeTrackingData['servico']['nome'] ?? null,
                    'volume' => $completeTrackingData['dimensao']['peso'] ?? null,
                    'weight' => $completeTrackingData['dimensao']['peso'] ?? null,
                    'external_updated_at' => now(),
                ]);

                // Create or update tracking record
                $trackingRecord = Tracking::updateOrCreate(
                    ['invoice_id' => $order->invoice->id, 'volume_id' => $volumeId],
                    [
                        'codigo_rastreamento' => $trackingCode,
                        'servico_id' => $completeTrackingData['servico']['id'] ?? null,
                        'descricao' => $completeTrackingData['rastreamento']['descricao'] ?? null,
                        'situacao' => $completeTrackingData['rastreamento']['situacao'] ?? null,
                        'url_rastreamento' => $completeTrackingData['rastreamento']['url'] ?? null,
                        'origem' => $completeTrackingData['origem'] ?? null,
                        'destino' => $completeTrackingData['destino'] ?? null,
                        'ultima_alteracao' => $this->parseValidDate($completeTrackingData['rastreamento']['ultimaAlteracao'] ?? null),
                        'peso' => $completeTrackingData['dimensao']['peso'] ?? null,
                        'altura' => $completeTrackingData['dimensao']['altura'] ?? null,
                        'largura' => $completeTrackingData['dimensao']['largura'] ?? null,
                        'comprimento' => $completeTrackingData['dimensao']['comprimento'] ?? null,
                        'diametro' => $completeTrackingData['dimensao']['diametro'] ?? null,
                        'raw_data' => $completeTrackingData,
                        'last_sync_at' => now(),
                    ]
                );

                // Record in history if created or updated
                $wasUpdated = !$wasCreated && ($oldTrackingCode !== $trackingRecord->codigo_rastreamento);

                if ($wasCreated || $wasUpdated) {
                    app(OrderHistoryService::class)->recordTrackingFetched($order, [
                        'volume_id' => $volumeId,
                        'tracking_code' => $trackingCode,
                        'logistics_provider' => $this->extractLogisticsProvider($completeTrackingData, $completeInvoiceData),
                    ]);
                }
            });

            // Broadcast OrderUpdated to frontend (non-blocking)
            $this->safeBroadcastOrderUpdate($order, ['tracking_code'], ['tracking_added']);

            // Emit internal event
            event(new TrackingSynced($order, $trackingCode));

            // Mark as processed (1 hour)
            Cache::put($cacheKey, now(), 3600);

            $this->logTrackingInfo('Tracking extraction completed', [
                'order_id' => $order->id,
                'tracking_code' => $trackingCode,
                'was_created' => $wasCreated,
            ]);

        } catch (\Exception $e) {
            $this->logTrackingError('Tracking extraction failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        DB::table('failed_sync_jobs')->insert([
            'order_summary_id' => $this->orderSummaryId,
            'job_type' => 'tracking_extraction',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'payload' => json_encode([
                'order_summary_id' => $this->orderSummaryId,
            ]),
            'failed_at' => now(),
            'resolved' => false,
        ]);

        $this->logTrackingError('Tracking extraction job failed permanently', [
            'order_id' => $this->orderSummaryId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get appropriate Bling service based on source.
     */
    private function getBlingService(string $source): BlingService
    {
        return match ($source) {
            'Collmepro Atacadista' => app(CollmeAtacadistaBlingService::class),
            default => app(CollmeProBlingService::class),
        };
    }

    /**
     * Extract logistics provider from tracking data.
     */
    private function extractLogisticsProvider(array $trackingData, array $invoiceData = null): ?string
    {
        // Try from tracking service name
        $serviceName = strtolower($trackingData['servico']['nome'] ?? '');

        if (str_contains($serviceName, 'correios') || str_contains($serviceName, 'pac') || str_contains($serviceName, 'sedex')) {
            return 'Correios';
        }

        if (str_contains($serviceName, 'total')) {
            return 'Total Express';
        }

        if (str_contains($serviceName, 'melhor')) {
            return 'Melhor Envio';
        }

        // Fallback to invoice transportador name
        if ($invoiceData) {
            $transportadorNome = strtolower($invoiceData['transporte']['transportador']['nome'] ?? '');

            if (str_contains($transportadorNome, 'correios')) {
                return 'Correios';
            }

            if (str_contains($transportadorNome, 'total')) {
                return 'Total Express';
            }

            if (str_contains($transportadorNome, 'melhor')) {
                return 'Melhor Envio';
            }

            return $invoiceData['transporte']['transportador']['nome'] ?? null;
        }

        return null;
    }

    /**
     * Parse valid date or return null.
     */
    private function parseValidDate(?string $date): ?Carbon
    {
        if (!$date || 
            $date === '0000-00-00' || 
            $date === '0000-00-00 00:00:00' ||
            $date === '' ||
            $date === '-0001-11-30 00:00:00') {
            return null;
        }

        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }

}

