<?php

namespace App\Console\Commands;

use App\Events\OrderUpdated;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Console\Command;

class FixAllInvoiceBroadcasting extends Command
{
    protected $signature = 'orders:fix-invoice-broadcasting {--limit=50 : Limit number of orders to process} {--dry-run : Show what would be done without actually broadcasting}';
    protected $description = 'Fix invoice broadcasting for all orders that have invoices but might not be displaying correctly';

    public function handle()
    {
        $limit = $this->option('limit');
        $dryRun = $this->option('dry-run');
        
        $this->info("Fixing invoice broadcasting for orders with invoices...");
        
        if ($dryRun) {
            $this->warn("DRY RUN MODE - No actual broadcasts will be sent");
        }
        
        // Get system user for broadcasting
        $systemUser = User::whereIn('role', ['admin', 'manager'])
            ->orderByRaw("FIELD(role, 'admin', 'manager')")
            ->first();
        
        if (!$systemUser) {
            $systemUser = User::first();
        }
        
        if (!$systemUser) {
            $this->error('No users found in system to broadcast as');
            return 1;
        }
        
        $this->info("Broadcasting as: {$systemUser->name} ({$systemUser->role})");
        
        // Find orders that have invoices and might need broadcasting
        $orders = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice')
            ->where('situacao_id', '!=', 12) // Not cancelled
            ->where('data', '>=', now()->subDays(7)) // Last 7 days
            ->orderBy('data', 'desc')
            ->limit($limit)
            ->get();
        
        $this->info("Found {$orders->count()} orders to process");
        
        if ($orders->isEmpty()) {
            $this->warn('No orders found to process');
            return 0;
        }
        
        $processed = 0;
        $broadcasted = 0;
        $skipped = 0;
        
        $progressBar = $this->output->createProgressBar($orders->count());
        $progressBar->start();
        
        foreach ($orders as $order) {
            $processed++;
            
            // Determine what changed
            $changedFields = [];
            $changeTypes = [];
            
            // Check for invoice
            if ($order->invoice && $order->invoice->numero) {
                $changedFields[] = 'invoice_number';
                $changeTypes[] = 'invoice_added';
            }
            
            // Check for tracking
            if ($order->tracking_code) {
                $changedFields[] = 'tracking_code';
                $changeTypes[] = 'tracking_added';
            }
            
            // Check for order status updates
            if ($order->orderStatus) {
                if ($order->orderStatus->picking) {
                    $changedFields[] = 'picking';
                }
                if ($order->orderStatus->packing) {
                    $changedFields[] = 'packing';
                }
                if ($order->orderStatus->picked_up) {
                    $changedFields[] = 'picked_up';
                }
                
                if (!empty($changedFields)) {
                    $changeTypes[] = 'status_updated';
                }
            }
            
            // Skip if no significant changes
            if (empty($changedFields)) {
                $skipped++;
                $progressBar->advance();
                continue;
            }
            
            if (!$dryRun) {
                // Broadcast the event
                event(new OrderUpdated($order, $systemUser, $changedFields, $changeTypes));
                $broadcasted++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine();
        
        // Summary
        $this->info("\n=== SUMMARY ===");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $processed],
                ['Broadcasted', $dryRun ? '0 (dry run)' : $broadcasted],
                ['Skipped', $skipped],
                ['Orders with Invoices', $orders->filter(fn($o) => $o->invoice)->count()],
                ['Orders with Tracking', $orders->filter(fn($o) => $o->tracking_code)->count()],
            ]
        );
        
        if ($dryRun) {
            $this->warn("This was a dry run. To actually broadcast, run without --dry-run flag");
        } else {
            $this->info("✅ Broadcasting completed successfully!");
            $this->info("Check your Kanban board to verify the updates are visible");
        }
        
        return 0;
    }
}
