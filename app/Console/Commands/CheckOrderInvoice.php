<?php

namespace App\Console\Commands;

use App\Models\OrderSummary;
use App\Models\Invoice;
use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Console\Command;

class CheckOrderInvoice extends Command
{
    protected $signature = 'bling:check-invoice {numero : Order number to check}';
    protected $description = 'Check order invoice and tracking details';

    public function handle()
    {
        $numero = $this->argument('numero');
        
        // Find in database
        $localOrder = OrderSummary::with(['invoice'])->where('numero', $numero)->first();
        
        if (!$localOrder) {
            $this->error("Order {$numero} not found in database");
            return 1;
        }
        
        $this->info("=== LOCAL DATABASE - INVOICE INFO ===");
        $this->table(
            ['Field', 'Value'],
            [
                ['Order Number', $localOrder->numero],
                ['Bling ID', $localOrder->bling_id],
                ['Has Invoice', $localOrder->invoice ? 'YES' : 'NO'],
                ['Invoice Number', $localOrder->invoice?->numero ?? 'N/A'],
                ['Invoice ID', $localOrder->invoice?->id ?? 'N/A'],
                ['Invoice Status', $localOrder->invoice?->situacao ?? 'N/A'],
                ['Tracking Code', $localOrder->tracking_code ?? 'N/A'],
                ['Updated At', $localOrder->updated_at],
            ]
        );
        
        // Check if there are invoices in the invoices table for this order
        $invoices = Invoice::where('order_summary_id', $localOrder->id)->get();
        $this->info("\n=== INVOICES TABLE ===");
        $this->table(
            ['ID', 'Bling ID', 'Number', 'Status', 'Created At'],
            $invoices->map(function($invoice) {
                return [
                    $invoice->id,
                    $invoice->bling_id,
                    $invoice->numero ?? 'N/A',
                    $invoice->situacao ?? 'N/A',
                    $invoice->created_at,
                ];
            })->toArray()
        );
        
        // Determine which service to use
        $blingService = $localOrder->source === 'collmepro_atacadista' 
            ? app(CollmeAtacadistaBlingService::class)
            : app(CollmeProBlingService::class);
        
        $this->info("\n=== CHECKING BLING API FOR INVOICES ===");
        
        try {
            // Get complete order data
            $blingOrder = $blingService->getCompleteOrderData($localOrder->bling_id);
            
            if (!$blingOrder) {
                $this->error("Could not fetch order from Bling API");
                return 1;
            }
            
            $this->info("Order has notaFiscal ID: " . ($blingOrder['notaFiscal']['id'] ?? 'N/A'));
            
            // Check if there are invoices in Bling for this order
            if (isset($blingOrder['notaFiscal']['id']) && $blingOrder['notaFiscal']['id']) {
                $this->info("\n=== BLING INVOICE DETAILS ===");
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Invoice ID', $blingOrder['notaFiscal']['id'] ?? 'N/A'],
                        ['Invoice Number', $blingOrder['notaFiscal']['numero'] ?? 'N/A'],
                        ['Invoice Status', $blingOrder['notaFiscal']['situacao'] ?? 'N/A'],
                        ['Invoice Date', $blingOrder['notaFiscal']['dataEmissao'] ?? 'N/A'],
                    ]
                );
                
                // Check if we have this invoice in our database
                $blingInvoiceId = $blingOrder['notaFiscal']['id'];
                $existingInvoice = Invoice::where('bling_id', $blingInvoiceId)->first();
                
                if ($existingInvoice) {
                    $this->info("✅ Invoice exists in our database");
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Our Invoice ID', $existingInvoice->id],
                            ['Our Invoice Number', $existingInvoice->numero],
                            ['Our Invoice Status', $existingInvoice->situacao],
                            ['Created At', $existingInvoice->created_at],
                        ]
                    );
                } else {
                    $this->warn("❌ Invoice NOT found in our database!");
                    $this->line("Bling has invoice ID: {$blingInvoiceId}");
                    $this->line("But we don't have it in our invoices table.");
                }
            } else {
                $this->info("Order does not have invoice in Bling");
            }
            
        } catch (\Exception $e) {
            $this->error("Error fetching from Bling: " . $e->getMessage());
            return 1;
        }
    }
}
