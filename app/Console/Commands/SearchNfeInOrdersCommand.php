<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\BlingService;
use Illuminate\Console\Command;
use Exception;

class SearchNfeInOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'orders:search-nfe 
                            {--limit=50 : Limit of orders to check}
                            {--order-id= : Check specific order ID}
                            {--stop-on-first : Stop on first NFE found}';

    /**
     * The console command description.
     */
    protected $description = 'Search for NFE (Nota Fiscal Eletrônica) in orders using Bling API';

    private BlingService $blingService;

    public function __construct(BlingService $blingService)
    {
        parent::__construct();
        $this->blingService = $blingService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Searching for NFEs in orders...');
        $this->newLine();

        try {
            $orderId = $this->option('order-id');
            $limit = (int) $this->option('limit');
            $stopOnFirst = $this->option('stop-on-first');

            if ($orderId) {
                $this->searchNfeInSpecificOrder($orderId);
            } else {
                $this->searchNfeInAllOrders($limit, $stopOnFirst);
            }

            $this->newLine();
            $this->info('✅ NFE search completed!');
            return 0;

        } catch (Exception $e) {
            $this->error('❌ Error searching for NFEs: ' . $e->getMessage());
            return 1;
        }
    }

    private function searchNfeInSpecificOrder(string $orderId)
    {
        $this->info("🎯 Searching NFE for specific order: {$orderId}");
        
        $order = Order::find($orderId);
        if (!$order) {
            $this->error("❌ Order with ID {$orderId} not found.");
            return;
        }

        $this->checkOrderForNfe($order);
    }

    private function searchNfeInAllOrders(int $limit, bool $stopOnFirst)
    {
        $this->info("📋 Searching NFE in up to {$limit} orders...");
        
        $orders = Order::limit($limit)->get();
        
        if ($orders->isEmpty()) {
            $this->warn('⚠️  No orders found in database.');
            return;
        }

        $this->info("Found {$orders->count()} orders to check.");
        $this->newLine();

        $foundCount = 0;
        $checkedCount = 0;

        $progressBar = $this->output->createProgressBar($orders->count());
        $progressBar->start();

        foreach ($orders as $order) {
            $checkedCount++;
            
            $this->line("\n🔍 Checking Order #{$order->id} - {$order->order_number}");
            
            $nfeFound = $this->checkOrderForNfe($order, false);
            
            if ($nfeFound) {
                $foundCount++;
                
                if ($stopOnFirst) {
                    $this->newLine();
                    $this->info("🛑 Stopping on first NFE found!");
                    break;
                }
            }
            
            $progressBar->advance();
            
            // Rate limiting - wait 1 second between requests
            sleep(1);
        }

        $progressBar->finish();
        
        $this->newLine(2);
        $this->info("📊 Summary:");
        $this->info("   - Orders checked: {$checkedCount}");
        $this->info("   - NFEs found: {$foundCount}");
        
        if ($foundCount === 0) {
            $this->warn("⚠️  No NFEs found in any of the checked orders.");
        }
    }

    private function checkOrderForNfe(Order $order, bool $showDetails = true): bool
    {
        try {
            // Try to find NFE by order number
            $this->line("   📞 Calling Bling API for order: {$order->order_number}");
            
            $nfe = $this->blingService->fetchInvoiceByOrderNumber($order->order_number);
            
            if ($nfe) {
                $this->newLine();
                $this->info("✅ NFE FOUND for Order #{$order->id}!");
                
                if ($showDetails) {
                    $this->displayNfeDetails($nfe);
                } else {
                    $this->displayNfeSummary($order, $nfe);
                }
                
                return true;
            } else {
                $this->line("   ❌ No NFE found for order: {$order->order_number}");
                return false;
            }

        } catch (Exception $e) {
            $this->error("   ❌ Error checking order {$order->id}: " . $e->getMessage());
            return false;
        }
    }

    private function displayNfeDetails(array $nfe)
    {
        $this->newLine();
        $this->info('🎉 NFE Details:');
        
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $nfe['id'] ?? 'N/A'],
                ['Number', $nfe['numero'] ?? 'N/A'],
                ['Status', $nfe['situacao'] ?? 'N/A'],
                ['Type', $nfe['tipo'] ?? 'N/A'],
                ['Issue Date', $nfe['dataEmissao'] ?? 'N/A'],
                ['Operation Date', $nfe['dataOperacao'] ?? 'N/A'],
                ['Access Key', $nfe['chaveAcesso'] ?? 'N/A'],
                ['Contact Name', $nfe['contato']['nome'] ?? 'N/A'],
                ['Contact Document', $nfe['contato']['numeroDocumento'] ?? 'N/A'],
                ['Contact Email', $nfe['contato']['email'] ?? 'N/A'],
                ['Store', $nfe['loja']['nome'] ?? 'N/A'],
            ]
        );

        // Try to get PDF URL
        $this->newLine();
        $this->info('📄 Getting PDF URL...');
        
        $pdfUrl = $this->blingService->getInvoicePdfUrl($nfe['id']);
        
        if ($pdfUrl) {
            $this->info("✅ PDF URL: {$pdfUrl}");
        } else {
            $this->warn("⚠️  PDF URL not available");
        }
    }

    private function displayNfeSummary(Order $order, array $nfe)
    {
        $this->info("   ✅ Order #{$order->id} ({$order->order_number}) -> NFE #{$nfe['numero']} - {$nfe['dataEmissao']}");
        
        if (isset($nfe['contato']['nome'])) {
            $this->info("      👤 Customer: {$nfe['contato']['nome']}");
        }
        
        if (isset($nfe['chaveAcesso'])) {
            $accessKey = substr($nfe['chaveAcesso'], 0, 20) . '...';
            $this->info("      🔑 Access Key: {$accessKey}");
        }
    }
}
