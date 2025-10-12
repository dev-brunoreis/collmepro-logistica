<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use App\Models\Invoice;
use Illuminate\Console\Command;

class FindOrdersWithMissingInvoiceDisplay extends Command
{
    protected $signature = 'orders:find-missing-invoice-display {--limit=50 : Limit number of results}';
    protected $description = 'Find orders that have invoices in database but might not display correctly in Kanban';

    public function handle()
    {
        $limit = $this->option('limit');
        
        $this->info("Searching for orders with invoice display issues...");
        
        // Find orders that have invoices but might not be displaying them correctly
        // This includes orders that:
        // 1. Have invoices in the invoices table
        // 2. Are recent (last 30 days) to focus on current issues
        // 3. Are not cancelled
        
        $ordersWithInvoices = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice')
            ->where('situacao_id', '!=', 12) // Not cancelled
            ->where('data', '>=', now()->subDays(30)) // Recent orders
            ->orderBy('data', 'desc')
            ->limit($limit)
            ->get();
        
        $this->info("Found {$ordersWithInvoices->count()} orders with invoices in the last 30 days");
        
        if ($ordersWithInvoices->isEmpty()) {
            $this->warn('No orders found with invoices in the last 30 days');
            return 0;
        }
        
        $this->info("\n=== ORDERS WITH INVOICES ===");
        
        $tableData = [];
        $problematicOrders = [];
        
        foreach ($ordersWithInvoices as $order) {
            $invoice = $order->invoice;
            $invoiceNumber = $invoice ? $invoice->numero : 'N/A';
            $invoiceId = $invoice ? $invoice->bling_id : 'N/A';
            $lastUpdated = $order->external_updated_at ? $order->external_updated_at->format('Y-m-d H:i:s') : 'Never';
            
            $tableData[] = [
                $order->numero,
                $order->contato_nome,
                $invoiceNumber,
                $invoiceId,
                $order->source,
                $lastUpdated,
            ];
            
            // Check for potential issues
            $issues = [];
            
            // Issue 1: Invoice exists but external_updated_at is old (suggests sync issue)
            if ($invoice && $order->external_updated_at && $order->external_updated_at->lt(now()->subDays(1))) {
                $issues[] = 'Old sync';
            }
            
            // Issue 2: Invoice was created but order wasn't updated recently
            if ($invoice && $invoice->created_at->gt($order->external_updated_at ?? $order->created_at)) {
                $issues[] = 'Invoice newer than sync';
            }
            
            if (!empty($issues)) {
                $problematicOrders[] = [
                    'order' => $order,
                    'issues' => $issues
                ];
            }
        }
        
        $this->table(
            ['Order', 'Customer', 'Invoice #', 'Bling ID', 'Source', 'Last Sync'],
            $tableData
        );
        
        if (!empty($problematicOrders)) {
            $this->warn("\n=== POTENTIALLY PROBLEMATIC ORDERS ===");
            $this->warn("These orders may have invoice display issues:");
            
            foreach ($problematicOrders as $item) {
                $order = $item['order'];
                $issues = $item['issues'];
                $this->line("• Order {$order->numero}: " . implode(', ', $issues));
            }
            
            $this->info("\n=== RECOMMENDATIONS ===");
            $this->info("1. Run: php artisan orders:fix-invoice-broadcasting --all");
            $this->info("2. Or fix specific orders: php artisan broadcast:order-update {numero}");
            $this->info("3. Check Kanban to verify display is correct");
        } else {
            $this->info("\n✅ No obvious invoice display issues found in recent orders");
        }
        
        // Also check for orders that should have invoices but don't
        $this->info("\n=== CHECKING FOR MISSING INVOICES ===");
        
        $recentOrders = OrderSummary::with(['invoice', 'orderStatus'])
            ->where('situacao_id', '!=', 12)
            ->where('data', '>=', now()->subDays(7)) // Last 7 days
            ->whereDoesntHave('invoice')
            ->orderBy('data', 'desc')
            ->limit(20)
            ->get();
        
        if ($recentOrders->isNotEmpty()) {
            $this->warn("Found {$recentOrders->count()} recent orders WITHOUT invoices:");
            foreach ($recentOrders as $order) {
                $this->line("• Order {$order->numero} ({$order->contato_nome}) - {$order->source}");
            }
        } else {
            $this->info("✅ All recent orders have invoices");
        }
        
        return 0;
    }
}
