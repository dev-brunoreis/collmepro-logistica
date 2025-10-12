<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderSummary;
use App\Models\User;
use App\Events\OrderUpdated;

class FixKanbanColumnPositions extends Command
{
    protected $signature = 'bling:fix-kanban-positions {--limit=50 : Limit number of orders to process} {--dry-run : Show what would be done without executing}';
    protected $description = 'Fix orders with invoices that are in wrong Kanban columns by forcing broadcasts';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info("🔍 DRY RUN MODE - No broadcasts will be sent");
        }
        
        $this->info("Finding orders with invoices in wrong Kanban positions...");
        
        // Get recent orders with invoices
        $ordersWithInvoices = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice', function($query) {
                $query->whereNotNull('numero');
            })
            ->where('updated_at', '>=', now()->subDays(7)) // Only recent orders
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
            
        $this->info("Found {$ordersWithInvoices->count()} recent orders with invoices");
        
        if ($ordersWithInvoices->isEmpty()) {
            $this->info("No orders found to process");
            return;
        }
        
        // Get system user for broadcasting
        $systemUser = User::whereIn('role', ['admin', 'manager'])
            ->orderByRaw("FIELD(role, 'admin', 'manager')")
            ->first();
            
        if (!$systemUser) {
            $systemUser = User::first();
        }
        
        if (!$systemUser) {
            $this->error("No users found in system for broadcasting");
            return;
        }
        
        $this->info("Using user: {$systemUser->name} ({$systemUser->role})");
        
        $processed = 0;
        $errors = 0;
        
        foreach ($ordersWithInvoices as $order) {
            try {
                // Determine what column this order SHOULD be in
                $correctColumn = $this->getOrderKanbanStatus($order);
                
                // If order has invoice but should NOT be in 'faturamento', it needs a broadcast
                if ($correctColumn !== 'faturamento') {
                    if ($dryRun) {
                        $this->line("Would fix order: {$order->numero} (Invoice: {$order->invoice->numero}) -> Should be in: {$correctColumn}");
                    } else {
                        // Force broadcast to correct Kanban position
                        event(new OrderUpdated($order, $systemUser, ['invoice_number'], ['invoice_added']));
                        $this->line("✅ Fixed order: {$order->numero} (Invoice: {$order->invoice->numero}) -> Should be in: {$correctColumn}");
                    }
                    $processed++;
                } else {
                    $this->line("⏭️  Order {$order->numero} already in correct column: {$correctColumn}");
                }
            } catch (\Exception $e) {
                $this->error("❌ Error fixing order {$order->numero}: " . $e->getMessage());
                $errors++;
            }
        }
        
        if ($dryRun) {
            $this->info("\n🔍 DRY RUN SUMMARY:");
            $this->info("Would fix: {$processed} orders");
        } else {
            $this->info("\n📊 FIX SUMMARY:");
            $this->info("✅ Successfully fixed: {$processed} orders");
            if ($errors > 0) {
                $this->warn("❌ Errors: {$errors} orders");
            }
        }
        
        $this->info("\n🎯 Orders with invoices should now be in 'Checkout' column instead of 'Faturamento'");
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

        // 5. Checkout: invoice + !picking
        if ($hasInvoice && (!$orderStatus || !$orderStatus->picking)) {
            return 'checkout';
        }

        // 6. Faturamento: !invoice
        if (!$hasInvoice) {
            return 'faturamento';
        }

        // 7. Pedidos Emitidos: default
        return 'pedidos_emitidos';
    }
}
