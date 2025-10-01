<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReprocessPendingInvoicesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    /**
     * Execute the job.
     * 
     * Busca pedidos com NFE status 5 (aguardando autorização)
     * e dispara job para reprocessar e verificar se viraram status 6
     */
    public function handle(): void
    {
        Log::info('Starting reprocess of pending invoices (status 5)');

        // Busca TODAS as invoices com status 5 (aguardando autorização)
        $pendingInvoices = Invoice::where('status', 5)
            ->with('order')
            ->get();

        if ($pendingInvoices->isEmpty()) {
            Log::info('No pending invoices (status 5) found to reprocess');
            return;
        }

        Log::info('Found pending invoices to reprocess', [
            'count' => $pendingInvoices->count()
        ]);

        $dispatched = 0;
        
        foreach ($pendingInvoices as $invoice) {
            if (!$invoice->order) {
                Log::warning('Invoice without order, skipping', [
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number
                ]);
                continue;
            }

            Log::info('Dispatching reprocess for invoice', [
                'order_id' => $invoice->order_id,
                'order_number' => $invoice->order->order_number,
                'invoice_number' => $invoice->invoice_number,
                'current_status' => $invoice->status
            ]);

            // Dispara job para reprocessar este pedido
            SyncBlingDataJob::dispatch($invoice->order_id)
                ->delay(now()->addSeconds($dispatched * 2)); // Escalonado: 0s, 2s, 4s, 6s, etc

            $dispatched++;
        }

        Log::info('Reprocess jobs dispatched', [
            'total' => $dispatched
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ReprocessPendingInvoicesJob failed', [
            'error' => $exception->getMessage()
        ]);
    }
}
