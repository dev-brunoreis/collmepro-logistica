<?php

namespace App\Console\Commands;

use App\Services\BlingService;
use Illuminate\Console\Command;
use Exception;

class TestNfeDetailsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'test:nfe-details {invoice-id}';

    /**
     * The console command description.
     */
    protected $description = 'Test fetching complete NFE details by ID';

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
        $invoiceId = $this->argument('invoice-id');
        
        $this->info("🔍 Testing NFE details fetch for ID: {$invoiceId}");
        $this->newLine();

        try {
            $this->info("📞 Calling Bling API: /nfe/{$invoiceId}");
            
            $nfeDetails = $this->blingService->fetchInvoiceById($invoiceId);
            
            if ($nfeDetails) {
                $this->info("✅ NFE Details FOUND!");
                $this->newLine();
                
                // Display basic info
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['ID', $nfeDetails['id'] ?? 'N/A'],
                        ['Number', $nfeDetails['numero'] ?? 'N/A'],
                        ['Status', $nfeDetails['situacao'] ?? 'N/A'],
                        ['Type', $nfeDetails['tipo'] ?? 'N/A'],
                        ['Issue Date', $nfeDetails['dataEmissao'] ?? 'N/A'],
                        ['Value', $nfeDetails['valorNota'] ?? 'N/A'],
                        ['Freight', $nfeDetails['valorFrete'] ?? 'N/A'],
                        ['Series', $nfeDetails['serie'] ?? 'N/A'],
                    ]
                );

                // Display PDF/DANFE links
                $this->newLine();
                $this->info('📄 PDF & DANFE Links:');
                $this->table(
                    ['Type', 'URL'],
                    [
                        ['PDF', $nfeDetails['linkPDF'] ?? 'Not available'],
                        ['DANFE', $nfeDetails['linkDanfe'] ?? 'Not available'],
                    ]
                );

                // Display contact info
                if (isset($nfeDetails['contato'])) {
                    $this->newLine();
                    $this->info('👤 Contact Information:');
                    $contact = $nfeDetails['contato'];
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Name', $contact['nome'] ?? 'N/A'],
                            ['Document', $contact['numeroDocumento'] ?? 'N/A'],
                            ['Email', $contact['email'] ?? 'N/A'],
                            ['Phone', $contact['telefone'] ?? 'N/A'],
                        ]
                    );
                }

                // Test PDF URL extraction
                $this->newLine();
                $this->info('🔗 Testing PDF URL extraction...');
                $pdfUrl = $this->blingService->getInvoicePdfUrl($invoiceId);
                
                if ($pdfUrl) {
                    $this->info("✅ PDF URL: {$pdfUrl}");
                } else {
                    $this->warn("⚠️  PDF URL not available");
                }

                // Test data extraction
                $this->newLine();
                $this->info('📊 Testing data extraction...');
                $extractedData = $this->blingService->extractInvoiceData($nfeDetails);
                
                $this->info('✅ Data extracted successfully:');
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Bling ID', $extractedData['bling_id']],
                        ['Invoice Number', $extractedData['invoice_number']],
                        ['Access Key', substr($extractedData['access_key'] ?? 'N/A', 0, 20) . '...'],
                        ['Link PDF', $extractedData['link_pdf'] ? 'Available' : 'Not available'],
                        ['Link DANFE', $extractedData['link_danfe'] ? 'Available' : 'Not available'],
                        ['Value', $extractedData['valor_nota'] ?? 'N/A'],
                        ['Freight', $extractedData['valor_frete'] ?? 'N/A'],
                    ]
                );

            } else {
                $this->warn("❌ No NFE details found for ID: {$invoiceId}");
                $this->newLine();
                $this->info("💡 This could mean:");
                $this->info("   - NFE ID doesn't exist");
                $this->info("   - Wrong token/authentication");
                $this->info("   - API endpoint not accessible");
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
