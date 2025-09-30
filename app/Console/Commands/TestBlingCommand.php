<?php

namespace App\Console\Commands;

use App\Services\BlingService;
use Illuminate\Console\Command;
use Exception;

class TestBlingCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bling:test 
                            {--action=invoices : Action to test (invoices, order, date-range, status, pdf)}
                            {--order-number= : Order number for testing specific invoice}
                            {--start-date= : Start date for date range (YYYY-MM-DD)}
                            {--end-date= : End date for date range (YYYY-MM-DD)}
                            {--status=5 : Status for testing (5 = authorized)}
                            {--limit=5 : Limit of results to show}';

    /**
     * The console command description.
     */
    protected $description = 'Test Bling API integration with different scenarios';

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
        $this->info('🧪 Testing Bling API Integration...');
        $this->newLine();

        try {
            $action = $this->option('action');
            
            switch ($action) {
                case 'invoices':
                    $this->testFetchInvoices();
                    break;
                case 'order':
                    $this->testFetchInvoiceByOrder();
                    break;
                case 'date-range':
                    $this->testFetchInvoicesByDateRange();
                    break;
                case 'status':
                    $this->testFetchInvoicesByStatus();
                    break;
                case 'pdf':
                    $this->testGetInvoicePdf();
                    break;
                default:
                    $this->error("Invalid action: {$action}");
                    $this->info('Available actions: invoices, order, date-range, status, pdf');
                    return 1;
            }

            $this->newLine();
            $this->info('✅ Bling API test completed successfully!');
            return 0;

        } catch (Exception $e) {
            $this->error('❌ Error testing Bling API: ' . $e->getMessage());
            return 1;
        }
    }

    private function testFetchInvoices()
    {
        $this->info('📋 Testing: Fetch Invoices');
        
        $limit = (int) $this->option('limit');
        $filters = ['limite' => $limit];
        
        $this->info("Fetching up to {$limit} invoices...");
        
        $invoices = $this->blingService->fetchInvoices($filters);
        
        $this->displayResults('Invoices', $invoices);
    }

    private function testFetchInvoiceByOrder()
    {
        $orderNumber = $this->option('order-number');
        
        if (!$orderNumber) {
            $this->error('Order number is required for this test. Use --order-number=123456');
            return;
        }

        $this->info("🔍 Testing: Fetch Invoice by Order Number ({$orderNumber})");
        
        $invoice = $this->blingService->fetchInvoiceByOrderNumber($orderNumber);
        
        if ($invoice) {
            $this->info('✅ Invoice found!');
            $this->displayInvoiceDetails($invoice);
        } else {
            $this->warn('⚠️  No invoice found for this order number.');
        }
    }

    private function testFetchInvoicesByDateRange()
    {
        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');
        
        if (!$startDate || !$endDate) {
            $this->error('Start date and end date are required. Use --start-date=2024-01-01 --end-date=2024-01-31');
            return;
        }

        $this->info("📅 Testing: Fetch Invoices by Date Range ({$startDate} to {$endDate})");
        
        $invoices = $this->blingService->fetchInvoicesByDateRange($startDate, $endDate);
        
        $this->displayResults('Invoices by Date Range', $invoices);
    }

    private function testFetchInvoicesByStatus()
    {
        $status = (int) $this->option('status');
        
        $this->info("📊 Testing: Fetch Invoices by Status ({$status})");
        
        $invoices = $this->blingService->fetchInvoicesByStatus($status);
        
        $this->displayResults('Invoices by Status', $invoices);
    }

    private function testGetInvoicePdf()
    {
        $orderNumber = $this->option('order-number');
        
        if (!$orderNumber) {
            $this->error('Order number is required for PDF test. Use --order-number=123456');
            return;
        }

        $this->info("📄 Testing: Get Invoice PDF URL for Order ({$orderNumber})");
        
        // First get the invoice
        $invoice = $this->blingService->fetchInvoiceByOrderNumber($orderNumber);
        
        if (!$invoice) {
            $this->warn('⚠️  No invoice found for this order number.');
            return;
        }

        $invoiceId = $invoice['id'];
        $this->info("Invoice ID: {$invoiceId}");
        
        $pdfUrl = $this->blingService->getInvoicePdfUrl($invoiceId);
        
        if ($pdfUrl) {
            $this->info('✅ PDF URL found!');
            $this->info("PDF URL: {$pdfUrl}");
        } else {
            $this->warn('⚠️  No PDF URL available for this invoice.');
        }
    }

    private function displayResults(string $title, array $results)
    {
        $count = count($results);
        
        if ($count === 0) {
            $this->warn("⚠️  No {$title} found.");
            return;
        }

        $this->info("✅ Found {$count} {$title}:");
        $this->newLine();

        $headers = ['ID', 'Number', 'Status', 'Issue Date', 'Access Key'];
        $rows = [];

        foreach ($results as $invoice) {
            $rows[] = [
                $invoice['id'] ?? 'N/A',
                $invoice['numero'] ?? 'N/A',
                $invoice['situacao'] ?? 'N/A',
                $invoice['dataEmissao'] ?? 'N/A',
                substr($invoice['chaveAcesso'] ?? 'N/A', 0, 20) . '...'
            ];
        }

        $this->table($headers, $rows);
    }

    private function displayInvoiceDetails(array $invoice)
    {
        $this->newLine();
        $this->info('Invoice Details:');
        $this->table(
            ['Field', 'Value'],
            [
                ['ID', $invoice['id'] ?? 'N/A'],
                ['Number', $invoice['numero'] ?? 'N/A'],
                ['Status', $invoice['situacao'] ?? 'N/A'],
                ['Type', $invoice['tipo'] ?? 'N/A'],
                ['Issue Date', $invoice['dataEmissao'] ?? 'N/A'],
                ['Operation Date', $invoice['dataOperacao'] ?? 'N/A'],
                ['Access Key', $invoice['chaveAcesso'] ?? 'N/A'],
                ['Contact', $invoice['contato']['nome'] ?? 'N/A'],
                ['Store', $invoice['loja']['nome'] ?? 'N/A'],
            ]
        );
    }
}
