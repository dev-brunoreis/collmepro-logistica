<?php

namespace App\Jobs;

use App\Events\SellerSynced;
use App\Models\OrderSummary;
use App\Models\Seller;
use App\Services\WooCommerceService;
use App\Traits\BlingSyncLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SyncSellerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, BlingSyncLogger;

    public int $timeout = 30;
    public int $tries = 2;
    public int $backoff = 15;

    private int $orderSummaryId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $orderSummaryId)
    {
        $this->orderSummaryId = $orderSummaryId;
        $this->onQueue('sellers');
    }

    /**
     * Get unique ID for idempotency.
     */
    public function uniqueId(): string
    {
        return "sync-seller-{$this->orderSummaryId}";
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $cacheKey = "seller_synced_{$this->orderSummaryId}";

        // Idempotency check: Skip if processed recently (within 10 minutes)
        if (Cache::has($cacheKey)) {
            $this->logBlingInfo('Seller sync skipped (already processed recently)', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        $order = OrderSummary::find($this->orderSummaryId);

        if (!$order) {
            $this->logBlingError('Order not found for seller sync', [
                'order_id' => $this->orderSummaryId,
            ]);
            return;
        }

        // Only capture seller for pro and atacadista orders
        $source = strtolower($order->source);
        if (!in_array($source, ['pro', 'atacadista', 'collmepro', 'collmepro atacadista'])) {
            $this->logBlingDebug('Order source not eligible for seller sync', [
                'order_id' => $order->id,
                'source' => $order->source,
            ]);
            // Mark as processed to avoid constant re-checking
            Cache::put($cacheKey, now(), 600); // 10 minutes
            return;
        }

        // Extract WooCommerce order ID from numero_loja
        if (empty($order->numero_loja)) {
            $this->logBlingDebug('Order has no numero_loja for seller extraction', [
                'order_id' => $order->id,
            ]);
            Cache::put($cacheKey, now(), 600);
            return;
        }

        $parts = explode('_', $order->numero_loja);
        $woocommerceOrderId = $parts[0] ?? null;

        if (!$woocommerceOrderId) {
            $this->logBlingDebug('Could not extract WooCommerce order ID', [
                'order_id' => $order->id,
                'numero_loja' => $order->numero_loja,
            ]);
            Cache::put($cacheKey, now(), 600);
            return;
        }

        $this->logBlingInfo('Starting seller sync', [
            'order_id' => $order->id,
            'order_number' => $order->numero,
            'woocommerce_order_id' => $woocommerceOrderId,
            'source' => $source,
        ]);

        try {
            // Fetch seller from WooCommerce
            $woocommerceService = app(WooCommerceService::class);
            $sellerData = $woocommerceService->getOrderSeller($woocommerceOrderId, $source);

            if (!$sellerData || empty($sellerData['id'])) {
                $this->logBlingDebug('No seller data found in WooCommerce', [
                    'order_id' => $order->id,
                    'woocommerce_order_id' => $woocommerceOrderId,
                ]);
                Cache::put($cacheKey, now(), 600);
                return;
            }

            $seller = null;

            DB::transaction(function () use ($sellerData, $order, &$seller) {
                // Find or create seller
                $seller = Seller::firstOrCreate(
                    ['woocommerce_id' => $sellerData['id']],
                    [
                        'name' => $sellerData['name'] ?? 'Unknown',
                        'email' => $sellerData['email'] ?? null,
                    ]
                );

                // Sync the relationship (will not duplicate if already exists)
                $order->seller()->syncWithoutDetaching([$seller->id]);
            });

            // Emit internal event
            event(new SellerSynced($order, $seller));

            // Mark as processed (1 hour)
            Cache::put($cacheKey, now(), 3600);

            $this->logBlingInfo('Seller sync completed', [
                'order_id' => $order->id,
                'seller_id' => $seller?->id,
                'seller_name' => $seller?->name,
            ]);

        } catch (\Exception $e) {
            $this->logBlingError('Seller sync failed', [
                'order_id' => $order->id,
                'woocommerce_order_id' => $woocommerceOrderId,
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
            'job_type' => 'seller_sync',
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
            'payload' => json_encode([
                'order_summary_id' => $this->orderSummaryId,
            ]),
            'failed_at' => now(),
            'resolved' => false,
        ]);

        $this->logBlingError('Seller sync job failed permanently', [
            'order_id' => $this->orderSummaryId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}

