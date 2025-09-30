<?php

namespace Tests\Feature;

use App\Services\BlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private BlingService $blingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Only run if BLING_BEARER_TOKEN is configured
        if (!env('BLING_BEARER_TOKEN')) {
            $this->markTestSkipped('BLING_BEARER_TOKEN not configured. Set it in .env to run integration tests.');
        }

        $this->blingService = app(BlingService::class);
    }

    /** @test */
    public function it_can_connect_to_bling_api()
    {
        // Test with a simple request to check connectivity
        $invoices = $this->blingService->fetchInvoices(['limite' => 1]);
        
        // We expect either an array (success) or empty array (no data, but connection works)
        $this->assertIsArray($invoices);
    }

    /** @test */
    public function it_can_fetch_authorized_invoices()
    {
        $invoices = $this->blingService->fetchAuthorizedInvoices();
        
        $this->assertIsArray($invoices);
        
        // If we have invoices, validate their structure
        if (!empty($invoices)) {
            $invoice = $invoices[0];
            $this->assertArrayHasKey('id', $invoice);
            $this->assertArrayHasKey('numero', $invoice);
            $this->assertArrayHasKey('situacao', $invoice);
            $this->assertEquals(5, $invoice['situacao']); // Should be authorized
        }
    }

    /** @test */
    public function it_can_fetch_invoices_by_date_range()
    {
        // Test with last 30 days
        $startDate = now()->subDays(30)->format('Y-m-d 00:00:00');
        $endDate = now()->format('Y-m-d 23:59:59');
        
        $invoices = $this->blingService->fetchInvoicesByDateRange($startDate, $endDate);
        
        $this->assertIsArray($invoices);
        
        // If we have invoices, validate they are within the date range
        foreach ($invoices as $invoice) {
            if (isset($invoice['dataEmissao'])) {
                $issueDate = \Carbon\Carbon::parse($invoice['dataEmissao']);
                $this->assertTrue($issueDate->between($startDate, $endDate));
            }
        }
    }

    /** @test */
    public function it_validates_invoice_data_structure()
    {
        $invoices = $this->blingService->fetchInvoices(['limite' => 5]);
        
        $this->assertIsArray($invoices);
        
        foreach ($invoices as $invoice) {
            // Test if invoice is valid according to our validation
            $isValid = $this->blingService->isValidInvoice($invoice);
            $this->assertTrue($isValid, 'Invoice should be valid: ' . json_encode($invoice));
            
            // Test data extraction
            $extractedData = $this->blingService->extractInvoiceData($invoice);
            $this->assertArrayHasKey('bling_id', $extractedData);
            $this->assertArrayHasKey('invoice_number', $extractedData);
            $this->assertArrayHasKey('status', $extractedData);
            $this->assertContains($extractedData['status'], [1, 2, 3, 4, 5]); // Should be valid status
        }
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        // This test ensures our service handles API errors without crashing
        $invoices = $this->blingService->fetchInvoices(['invalid_param' => 'test']);
        
        // Should return empty array instead of throwing exception
        $this->assertIsArray($invoices);
    }

    /** @test */
    public function it_can_get_pdf_url_for_existing_invoice()
    {
        // First, get an invoice
        $invoices = $this->blingService->fetchInvoices(['limite' => 1]);
        
        if (empty($invoices)) {
            $this->markTestSkipped('No invoices available to test PDF URL retrieval.');
        }
        
        $invoice = $invoices[0];
        $invoiceId = $invoice['id'];
        
        $pdfUrl = $this->blingService->getInvoicePdfUrl($invoiceId);
        
        // PDF URL might not be available for all invoices, so we just check it doesn't crash
        if ($pdfUrl !== null) {
            $this->assertIsString($pdfUrl);
            $this->assertStringContainsString('http', $pdfUrl);
        }
    }

    /** @test */
    public function it_respects_rate_limiting()
    {
        $startTime = microtime(true);
        
        // Make multiple requests quickly
        for ($i = 0; $i < 3; $i++) {
            $this->blingService->fetchInvoices(['limite' => 1]);
        }
        
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Should take at least 2 seconds (1 second between requests)
        $this->assertGreaterThan(2, $duration, 'Rate limiting should be enforced');
    }
}
