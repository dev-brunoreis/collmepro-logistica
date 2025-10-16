<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckBroadcastingSyncStatus extends Command
{
    protected $signature = 'orders:check-broadcasting-sync {--test-kanban : Test specific orders in Kanban format}';
    protected $description = 'Check if orders have proper broadcasting sync status';

    public function handle()
    {
        $this->info("Checking broadcasting sync status...");
        
        // Check orders that might have broadcasting issues
        $this->checkInvoiceSyncStatus();
        
        if ($this->option('test-kanban')) {
            $this->testKanbanDataFormat();
        }
        
        return 0;
    }
    
    private function checkInvoiceSyncStatus()
    {
        $this->info("\n=== INVOICE SYNC STATUS ANALYSIS ===");
        
        // Find orders where invoice was created after the last sync
        $problematicOrders = OrderSummary::with(['invoice'])
            ->whereHas('invoice')
            ->where('situacao_id', '!=', 12)
            ->where('data', '>=', now()->subDays(7)) // Last 7 days
            ->get()
            ->filter(function ($order) {
                if (!$order->invoice) return false;
                
                // Check if invoice was created after external_updated_at
                $invoiceCreatedAt = $order->invoice->created_at;
                $lastSync = $order->external_updated_at ?? $order->created_at;
                
                // If invoice is newer than sync, this might be a problem
                return $invoiceCreatedAt->gt($lastSync);
            });
        
        if ($problematicOrders->isNotEmpty()) {
            $this->warn("Found {$problematicOrders->count()} orders where invoice was created after last sync:");
            
            foreach ($problematicOrders as $order) {
                $invoiceCreated = $order->invoice->created_at->format('Y-m-d H:i:s');
                $lastSync = ($order->external_updated_at ?? $order->created_at)->format('Y-m-d H:i:s');
                
                $this->line("• Order {$order->numero}: Invoice created at {$invoiceCreated}, Last sync: {$lastSync}");
            }
        } else {
            $this->info("✅ No orders found with invoice sync timing issues");
        }
        
        // Check orders that might need manual broadcasting
        $this->info("\n=== ORDERS THAT MIGHT NEED MANUAL BROADCAST ===");
        
        $candidates = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice')
            ->where('situacao_id', '!=', 12)
            ->where('data', '>=', now()->subDays(3)) // Last 3 days
            ->orderBy('data', 'desc')
            ->limit(20)
            ->get();
        
        $this->table(
            ['Order', 'Customer', 'Invoice', 'Has Status', 'Last Sync'],
            $candidates->map(function ($order) {
                return [
                    $order->numero,
                    substr($order->contato_nome, 0, 20),
                    $order->invoice?->numero ?? 'N/A',
                    $order->orderStatus ? 'Yes' : 'No',
                    $order->external_updated_at ? $order->external_updated_at->format('m-d H:i') : 'Never'
                ];
            })->toArray()
        );
    }
    
    private function testKanbanDataFormat()
    {
        $this->info("\n=== TESTING KANBAN DATA FORMAT ===");
        
        // Test with a few specific orders
        $testOrders = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice')
            ->where('situacao_id', '!=', 12)
            ->where('data', '>=', now()->subDays(1))
            ->limit(5)
            ->get();
        
        foreach ($testOrders as $order) {
            $this->info("Testing order: {$order->numero}");
            
            // Simulate Kanban data structure
            $invoiceNumber = $order->invoice?->numero ?? null;
            $hasInvoice = !empty($invoiceNumber);
            
            $this->line("  - Invoice Number: " . ($invoiceNumber ?? 'NULL'));
            $this->line("  - Has Invoice: " . ($hasInvoice ? 'YES' : 'NO'));
            $this->line("  - Would show in Kanban as: " . ($hasInvoice ? "NF: {$invoiceNumber}" : "Sem NF"));
            
            // Check if this matches what should be displayed
            if (!$hasInvoice && $order->invoice) {
                $this->warn("  ⚠️  PROBLEM: Order has invoice in DB but would show 'Sem NF' in Kanban!");
            } elseif ($hasInvoice) {
                $this->info("  ✅ Correct: Order would show invoice in Kanban");
            }
        }
    }
}
