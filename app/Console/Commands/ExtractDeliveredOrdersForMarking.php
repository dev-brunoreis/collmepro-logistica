<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderSummary;
use Carbon\Carbon;

class ExtractDeliveredOrdersForMarking extends Command
{
    protected $signature = 'orders:extract-delivered 
                            {--output= : Path to CSV output file}
                            {--limit= : Limit number of results}
                            {--dry-run : Only count without exporting}';

    protected $description = 'Extract old delivered orders that need to be marked as picking, packing, and picked_up';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        if ($dryRun) {
            $this->info("🔍 DRY RUN MODE - No CSV will be exported");
        }

        $this->info("Searching for delivered orders that need marking...");

        // Build query following all criteria
        $query = OrderSummary::query()
            ->with(['invoice', 'orderStatus'])
            ->where('order_summary.situacao_id', '!=', 12) // Not cancelled
            ->whereHas('invoice', function ($q) {
                $q->whereNotNull('numero'); // Has invoice number
            })
            ->whereHas('invoice.tracking', function ($q) {
                // Has tracking with situacao = 3 (delivered)
                $q->where('situacao', 3)
                  ->where(function ($subQ) {
                      // Has tracking code
                      $subQ->whereNotNull('codigo_rastreamento')
                           ->where('codigo_rastreamento', '!=', '');
                  });
            })
            ->where(function ($q) {
                // Has tracking code in order_summary OR in tracking table
                $q->where(function ($subQ) {
                    $subQ->whereNotNull('tracking_code')
                         ->where('tracking_code', '!=', '');
                })->orWhereHas('invoice.tracking', function ($subQ) {
                    $subQ->whereNotNull('codigo_rastreamento')
                         ->where('codigo_rastreamento', '!=', '');
                });
            })
            ->where(function ($q) {
                // Not marked yet (at least one status is false or order_status doesn't exist)
                $q->doesntHave('orderStatus')
                  ->orWhereHas('orderStatus', function ($subQ) {
                      $subQ->where(function ($statusQ) {
                          $statusQ->where('picking', false)
                                  ->orWhere('packing', false)
                                  ->orWhere('picked_up', false);
                      });
                  });
            })
            ->orderBy('order_summary.id');

        if ($limit) {
            $query->limit($limit);
        }

        // Count total
        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->warn("No orders found matching the criteria.");
            return;
        }

        $this->info("Found {$totalCount} orders matching criteria.");

        if ($dryRun) {
            $this->info("✅ DRY RUN completed. Found {$totalCount} orders that would be exported.");
            return;
        }

        // Get the orders with relationships loaded
        $orders = $query->get();

        // Prepare CSV output
        $outputPath = $this->getOutputPath();
        $this->info("Exporting {$orders->count()} orders to: {$outputPath}");

        $file = fopen($outputPath, 'w');

        // Write CSV headers
        $headers = [
            'ID Pedido',
            'Número Pedido',
            'Número Nota Fiscal',
            'Código Rastreio',
            'Transportadora',
            'Status Atual (Picking)',
            'Status Atual (Packing)',
            'Status Atual (Picked Up)',
            'Descrição Tracking',
            'Data Pedido',
            'Última Alteração Tracking',
        ];
        fputcsv($file, $headers, ';');

        // Write data
        $bar = $this->output->createProgressBar($orders->count());
        $bar->start();

        foreach ($orders as $order) {
            // Get tracking code (from order_summary or tracking table)
            $trackingCode = $order->tracking_code;
            $trackingDescricao = null;
            $trackingUltimaAlteracao = null;

            // If no tracking code in order_summary, get from tracking
            if (empty($trackingCode) && $order->invoice) {
                $deliveredTracking = $order->invoice->tracking()
                    ->where('situacao', 3)
                    ->whereNotNull('codigo_rastreamento')
                    ->where('codigo_rastreamento', '!=', '')
                    ->first();
                
                if ($deliveredTracking) {
                    $trackingCode = $deliveredTracking->codigo_rastreamento;
                    $trackingDescricao = $deliveredTracking->descricao;
                    $trackingUltimaAlteracao = $deliveredTracking->ultima_alteracao;
                }
            } else if ($order->invoice) {
                // Get tracking description and date from first delivered tracking
                $deliveredTracking = $order->invoice->tracking()
                    ->where('situacao', 3)
                    ->first();
                
                if ($deliveredTracking) {
                    $trackingDescricao = $deliveredTracking->descricao;
                    $trackingUltimaAlteracao = $deliveredTracking->ultima_alteracao;
                }
            }

            $carrier = $order->logistics_provider ?: $order->service_type ?: '-';
            $orderStatus = $order->orderStatus;

            $row = [
                $order->id,
                $order->numero,
                $order->invoice ? $order->invoice->numero : '-',
                $trackingCode ?: '-',
                $carrier,
                $orderStatus && $orderStatus->picking ? 'Sim' : 'Não',
                $orderStatus && $orderStatus->packing ? 'Sim' : 'Não',
                $orderStatus && $orderStatus->picked_up ? 'Sim' : 'Não',
                $trackingDescricao ?: '-',
                $order->data ? $order->data->format('Y-m-d H:i:s') : '-',
                $trackingUltimaAlteracao ? $trackingUltimaAlteracao->format('Y-m-d H:i:s') : '-',
            ];

            fputcsv($file, $row, ';');
            $bar->advance();
        }

        $bar->finish();
        fclose($file);

        $this->newLine();
        $this->info("✅ Export completed! {$orders->count()} orders exported to: {$outputPath}");
    }

    /**
     * Get output file path
     */
    private function getOutputPath(): string
    {
        if ($output = $this->option('output')) {
            return $output;
        }

        $timestamp = Carbon::now()->format('Ymd_His');
        $filename = "extracted_delivered_orders_{$timestamp}.csv";
        
        return storage_path("app/{$filename}");
    }
}

