<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\OrderSummary;
use App\Models\User;
use App\Events\OrderUpdated;

class ForceBroadcastForOrdersWithInvoices extends Command
{
    protected $signature = 'bling:force-broadcast-invoices {--limit=50 : Limit number of orders to process} {--dry-run : Show what would be done without executing}';
    protected $description = 'Force broadcast for orders that have invoices but may not have been broadcasted correctly';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->info("🔍 DRY RUN MODE - No broadcasts will be sent");
        }
        
        $this->info("Finding orders with invoices that need broadcast...");
        
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
                if ($dryRun) {
                    $this->line("Would broadcast order: {$order->numero} (Invoice: {$order->invoice->numero})");
                } else {
                    // Force broadcast OrderUpdated event
                    event(new OrderUpdated($order, $systemUser, ['invoice_number'], ['invoice_added']));
                    $this->line("✅ Broadcasted order: {$order->numero} (Invoice: {$order->invoice->numero})");
                }
                $processed++;
            } catch (\Exception $e) {
                $this->error("❌ Error broadcasting order {$order->numero}: " . $e->getMessage());
                $errors++;
            }
        }
        
        if ($dryRun) {
            $this->info("\n🔍 DRY RUN SUMMARY:");
            $this->info("Would process: {$processed} orders");
        } else {
            $this->info("\n📊 BROADCAST SUMMARY:");
            $this->info("✅ Successfully broadcasted: {$processed} orders");
            if ($errors > 0) {
                $this->warn("❌ Errors: {$errors} orders");
            }
        }
        
        $this->info("\n🎯 This should move orders with invoices from 'Faturamento' to 'Checkout' column in Kanban");
    }
}
