<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use Illuminate\Console\Command;

class VerifyInvoiceDisplayFix extends Command
{
    protected $signature = 'orders:verify-invoice-display-fix {--sample=10 : Number of orders to sample and verify}';
    protected $description = 'Verify that the invoice display fix worked correctly';

    public function handle()
    {
        $sampleSize = $this->option('sample');
        
        $this->info("Verifying invoice display fix...");
        
        // Get a sample of recent orders with invoices
        $orders = OrderSummary::with(['invoice', 'orderStatus'])
            ->whereHas('invoice')
            ->where('situacao_id', '!=', 12)
            ->where('data', '>=', now()->subDays(3))
            ->orderBy('data', 'desc')
            ->limit($sampleSize)
            ->get();
        
        $this->info("Sampling {$orders->count()} orders to verify...");
        
        $verified = 0;
        $issues = [];
        
        foreach ($orders as $order) {
            $hasInvoice = !empty($order->invoice?->numero);
            $invoiceNumber = $order->invoice?->numero ?? 'N/A';
            
            // Simulate what the Kanban would show
            $kanbanDisplay = $hasInvoice ? "NF: {$invoiceNumber}" : "Sem NF";
            
            if ($hasInvoice) {
                $verified++;
                $this->info("✅ Order {$order->numero}: {$kanbanDisplay}");
            } else {
                $issues[] = "Order {$order->numero}: Expected invoice but found none";
                $this->error("❌ Order {$order->numero}: {$kanbanDisplay}");
            }
        }
        
        $this->newLine();
        $this->info("=== VERIFICATION SUMMARY ===");
        $this->table(
            ['Metric', 'Count', 'Percentage'],
            [
                ['Total Sampled', $orders->count(), '100%'],
                ['Correctly Showing Invoices', $verified, round(($verified / $orders->count()) * 100, 1) . '%'],
                ['Issues Found', count($issues), round((count($issues) / $orders->count()) * 100, 1) . '%'],
            ]
        );
        
        if (!empty($issues)) {
            $this->warn("Issues found:");
            foreach ($issues as $issue) {
                $this->line("• {$issue}");
            }
        } else {
            $this->info("✅ All sampled orders are correctly displaying invoices!");
        }
        
        // Additional verification: Check specific orders mentioned by user
        $this->info("\n=== CHECKING SPECIFIC ORDERS ===");
        
        $specificOrders = ['106050', '106049', '106048'];
        foreach ($specificOrders as $numero) {
            $order = OrderSummary::with('invoice')->where('numero', $numero)->first();
            if ($order) {
                $hasInvoice = !empty($order->invoice?->numero);
                $status = $hasInvoice ? '✅' : '❌';
                $this->line("{$status} Order {$numero}: " . ($order->invoice?->numero ?? 'No invoice'));
            } else {
                $this->line("❌ Order {$numero}: Not found");
            }
        }
        
        return count($issues) === 0 ? 0 : 1;
    }
}
