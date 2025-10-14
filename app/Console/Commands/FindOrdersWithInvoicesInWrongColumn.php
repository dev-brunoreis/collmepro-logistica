<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderSummary;
use App\Http\Controllers\KanbanController;

class FindOrdersWithInvoicesInWrongColumn extends Command
{
    protected $signature = 'bling:find-wrong-column-orders {--limit=50 : Limit number of orders to check}';
    protected $description = 'Find orders that have invoices but are not in the correct Kanban column';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        
        $this->info("Finding orders with invoices in wrong columns...");
        
        // Get recent orders with invoices
        $ordersWithInvoices = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice', function($query) {
                $query->whereNotNull('numero');
            })
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
            
        $this->info("Found {$ordersWithInvoices->count()} orders with invoices");
        
        $wrongColumnOrders = [];
        
        foreach ($ordersWithInvoices as $order) {
            // Get the correct column using the same logic as KanbanController
            $correctColumn = $this->getOrderKanbanStatus($order);
            
            // Check if order would be in "faturamento" column (wrong for orders with invoices)
            if ($correctColumn === 'faturamento') {
                $wrongColumnOrders[] = [
                    'numero' => $order->numero,
                    'invoice_number' => $order->invoice->numero,
                    'correct_column' => $correctColumn,
                    'has_invoice' => true,
                    'picking' => $order->orderStatus?->picking ?? false,
                    'packing' => $order->orderStatus?->packing ?? false,
                    'tracking_code' => $order->tracking_code,
                ];
            }
        }
        
        if (empty($wrongColumnOrders)) {
            $this->info("✅ All orders with invoices are in the correct columns!");
            return;
        }
        
        $this->warn("❌ Found " . count($wrongColumnOrders) . " orders with invoices in wrong columns:");
        
        $headers = ['Order', 'Invoice', 'Should Be', 'Picking', 'Packing', 'Tracking'];
        $rows = [];
        
        foreach ($wrongColumnOrders as $order) {
            $rows[] = [
                $order['numero'],
                $order['invoice_number'],
                $order['correct_column'],
                $order['picking'] ? '✅' : '❌',
                $order['packing'] ? '✅' : '❌',
                $order['tracking_code'] ?: 'N/A'
            ];
        }
        
        $this->table($headers, $rows);
        
        // Show the logic explanation
        $this->info("\n📋 Kanban Column Logic:");
        $this->line("1. Coleta Realizada: picked_up=true");
        $this->line("2. Coleta Aguardando: invoice + picking + packing + tracking + !picked_up");
        $this->line("3. Etiqueta: invoice + picking + packing + !tracking");
        $this->line("4. Embalagem: invoice + picking + !packing");
        $this->line("5. Pedidos Emitidos: !invoice");
        $this->line("6. Faturamento: invoice + !picking");
        $this->line("7. Pedidos Emitidos: default");
    }
    
    /**
     * Get the kanban status for an order (same logic as KanbanController)
     */
    private function getOrderKanbanStatus(OrderSummary $order): string
    {
        $orderStatus = $order->orderStatus;

        // Check for tracking code
        $hasTracking = false;
        if ($order->tracking_code && $order->tracking_code !== '') {
            $hasTracking = true;
        } elseif ($order->invoice && $order->invoice->tracking()->count() > 0) {
            $hasTracking = true;
        }

        // Check for invoice number
        $hasInvoice = false;
        if ($order->invoice && $order->invoice->numero) {
            $hasInvoice = true;
        }

        // 1. Coleta Realizada: picked_up=true
        if ($orderStatus && $orderStatus->picked_up) {
            return 'coleta_realizada';
        }

        // 2. Coleta Aguardando: invoice + picking + packing + tracking_code + !picked_up
        if ($hasInvoice && $orderStatus && $orderStatus->picking && $orderStatus->packing && $hasTracking) {
            return 'coleta_aguardando';
        }

        // 3. Etiqueta: invoice + picking + packing + !tracking_code
        if ($hasInvoice && $orderStatus && $orderStatus->picking && $orderStatus->packing && !$hasTracking) {
            return 'etiqueta';
        }

        // 4. Embalagem: invoice + picking + !packing
        if ($hasInvoice && $orderStatus && $orderStatus->picking && !$orderStatus->packing) {
            return 'embalagem';
        }

        // 5. Pedidos Emitidos: !invoice
        if (!$hasInvoice) {
            return 'pedidos_emitidos';
        }

        // 6. Faturamento: invoice + !picking
        if ($hasInvoice && (!$orderStatus || !$orderStatus->picking)) {
            return 'faturamento';
        }

        // 7. Pedidos Emitidos: default
        return 'pedidos_emitidos';
    }
}
