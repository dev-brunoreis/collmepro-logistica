<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Exception;

class SyncAllBlingDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1; // Apenas 1 tentativa, pois este job cria outros jobs
    public int $timeout = 600; // 10 minutos

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info('Starting bulk Bling data sync');

            $batchSize = config('bling.sync.batch_size', 50);
            $maxOrders = config('bling.sync.max_orders_per_run', 500);

            // Busca pedidos sem NFE
            $orders = Order::whereNull('nfe_number')
                ->whereNull('nfe_pdf_url')
                ->limit($maxOrders)
                ->get();

            if ($orders->isEmpty()) {
                Log::info('No orders without invoice found for bulk sync');
                return;
            }

            Log::info('Found orders without invoice for bulk sync', [
                'count' => $orders->count()
            ]);

            // Divide em lotes e cria jobs individuais
            $batches = $orders->chunk($batchSize);
            $jobCount = 0;

            foreach ($batches as $batch) {
                foreach ($batch as $order) {
                    // Cria job individual para cada pedido
                    Queue::push(new SyncBlingDataJob($order->id));
                    $jobCount++;
                }

                // Pausa entre lotes para não sobrecarregar a fila
                sleep(1);
            }

            Log::info('Bulk Bling sync jobs created', [
                'total_jobs' => $jobCount,
                'batches' => $batches->count()
            ]);

        } catch (Exception $e) {
            Log::error('Bulk Bling sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncAllBlingDataJob failed', [
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }
}
