<?php

namespace App\Console\Commands;

use App\Services\BlingService;
use Illuminate\Console\Command;
use Exception;

class TestNfeFormatCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:nfe-format {order-number=8175}';

    /**
     * The console command description.
     */
    protected $description = 'Test NFE search with the correct id_id format';

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
        $orderNumber = $this->argument('order-number');
        $numeroLoja = $orderNumber . '_' . $orderNumber;
        
        $this->info("🔍 Testing NFE search with new format:");
        $this->info("   Order Number: {$orderNumber}");
        $this->info("   numeroLoja: {$numeroLoja}");
        $this->newLine();

        try {
            $this->info("📞 Calling Bling API: /nfe?numeroLoja={$numeroLoja}");
            
            $nfe = $this->blingService->fetchInvoiceByOrderNumber($orderNumber);
            
            if ($nfe) {
                $this->info("✅ NFE FOUND!");
                $this->newLine();
                
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $nfe['id'] ?? 'N/A'],
                        ['Number', $nfe['numero'] ?? 'N/A'],
                        ['Status', $nfe['situacao'] ?? 'N/A'],
                        ['Issue Date', $nfe['dataEmissao'] ?? 'N/A'],
                        ['Contact', $nfe['contato']['nome'] ?? 'N/A'],
                        ['Access Key', substr($nfe['chaveAcesso'] ?? 'N/A', 0, 20) . '...'],
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
            } else {
                $this->warn("❌ No NFE found for order: {$orderNumber} (numeroLoja: {$numeroLoja})");
                $this->newLine();
                $this->info("💡 This could mean:");
                $this->info("   - No NFE exists for this order");
                $this->info("   - Wrong token/authentication");
                $this->info("   - Order number doesn't exist in Bling");
            }

            return 0;

        } catch (Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            $this->info("💡 Check if BLING_BEARER_TOKEN is configured in .env");
            return 1;
        }
    }
}
