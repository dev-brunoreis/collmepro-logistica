<?php

namespace App\Console\Commands;

use App\Services\BlingService;
use Illuminate\Console\Command;
use Exception;

class TestNfeSearchCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:nfe-search {order-number}';

    /**
     * The console command description.
     */
    protected $description = 'Test NFE search for a specific order number';

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
        
        $this->info("🔍 Testing NFE search for order: {$orderNumber}");
        $this->newLine();

        try {
            $this->info("📞 Calling Bling API...");
            
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
            } else {
                $this->warn("❌ No NFE found for order: {$orderNumber}");
            }

            return 0;

        } catch (Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}
