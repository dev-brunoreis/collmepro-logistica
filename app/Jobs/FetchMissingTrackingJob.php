<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class FetchMissingTrackingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    /**
     * Busca rastreios para pedidos que têm NFE mas ainda não têm código de rastreamento
     */
    public function handle(): void
    {
        Log::info('Starting fetch missing tracking job');

        // Busca TODOS os pedidos que têm NFE mas não têm rastreio
        $orders = Order::whereNotNull('nfe_number')
            ->whereNull('tracking_code')
            ->get();

        if ($orders->isEmpty()) {
            Log::info('No orders found with NFE but without tracking');
            return;
        }

        Log::info('Found orders with NFE but without tracking', [
            'count' => $orders->count()
        ]);

        $dispatched = 0;
        
        foreach ($orders as $order) {
            Log::info('Dispatching tracking fetch for order', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'nfe_number' => $order->nfe_number
            ]);

            // Dispara job para buscar rastreio deste pedido
            FetchTrackingDataJob::dispatch($order->id)
                ->delay(now()->addSeconds($dispatched * 3)); // Escalonado: 0s, 3s, 6s, etc

            $dispatched++;
        }

        Log::info('Fetch missing tracking jobs dispatched', [
            'total' => $dispatched
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('FetchMissingTrackingJob failed', [
            'error' => $exception->getMessage()
        ]);
    }
}
