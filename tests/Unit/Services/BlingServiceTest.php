<?php

namespace Tests\Unit\Services;

use App\Services\BlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BlingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlingService $blingService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configuration
        Config::set('bling.api.base_url', 'https://api.bling.com.br/Api/v3');
        Config::set('bling.api.api_key', 'test_api_key');
        Config::set('bling.api.timeout', 30);
        Config::set('bling.rate_limit.requests_per_second', 3);
        Config::set('bling.defaults.invoice_status', 5);
        Config::set('bling.defaults.invoice_type', 1);
        Config::set('bling.defaults.limit_per_page', 100);
        Config::set('bling.logging.enabled', false);
        Config::set('bling.logging.log_requests', false);
        Config::set('bling.logging.log_responses', false);

        $this->blingService = new BlingService();
    }

    /** @test */
    public function it_can_fetch_invoices_with_default_parameters()
    {
        $mockResponse = [
            'data' => [
                [
                    'id' => 12345678,
                    'tipo' => 1,
                    'situacao' => 5,
                    'numero' => '6541',
                    'dataEmissao' => '2023-01-12 09:52:12',
                    'dataOperacao' => '2023-01-12 09:52:12',
                    'chaveAcesso' => '35250145752681000182650010000266371238060760',
                    'contato' => [
                        'id' => 12345678,
                        'nome' => 'Cliente Teste',
                        'numeroDocumento' => '30188025000121',
                        'email' => 'cliente@teste.com.br'
                    ]
                ]
            ]
        ];

        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response($mockResponse, 200)
        ]);

        $invoices = $this->blingService->fetchInvoices();

        $this->assertIsArray($invoices);
        $this->assertCount(1, $invoices);
        $this->assertEquals(12345678, $invoices[0]['id']);
        $this->assertEquals('6541', $invoices[0]['numero']);
    }

    /** @test */
    public function it_can_fetch_invoice_by_order_number()
    {
        $mockResponse = [
            'data' => [
                [
                    'id' => 12345678,
                    'numero' => '6541',
                    'situacao' => 5,
                    'dataEmissao' => '2023-01-12 09:52:12',
                    'chaveAcesso' => '35250145752681000182650010000266371238060760'
                ]
            ]
        ];

        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response($mockResponse, 200)
        ]);

        $invoice = $this->blingService->fetchInvoiceByOrderNumber('12345678');

        $this->assertIsArray($invoice);
        $this->assertEquals(12345678, $invoice['id']);
        $this->assertEquals('6541', $invoice['numero']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.bling.com.br/Api/v3/nfe') &&
                   str_contains($request->url(), 'numeroLoja=12345678');
        });
    }

    /** @test */
    public function it_returns_null_when_no_invoice_found_by_order_number()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response(['data' => []], 200)
        ]);

        $invoice = $this->blingService->fetchInvoiceByOrderNumber('99999999');

        $this->assertNull($invoice);
    }

    /** @test */
    public function it_can_fetch_invoices_by_date_range()
    {
        $mockResponse = [
            'data' => [
                [
                    'id' => 12345678,
                    'numero' => '6541',
                    'situacao' => 5,
                    'dataEmissao' => '2023-01-12 09:52:12'
                ],
                [
                    'id' => 12345679,
                    'numero' => '6542',
                    'situacao' => 5,
                    'dataEmissao' => '2023-01-13 09:52:12'
                ]
            ]
        ];

        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response($mockResponse, 200)
        ]);

        $invoices = $this->blingService->fetchInvoicesByDateRange(
            '2023-01-01 00:00:00',
            '2023-01-31 23:59:59'
        );

        $this->assertIsArray($invoices);
        $this->assertCount(2, $invoices);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.bling.com.br/Api/v3/nfe') &&
                   str_contains($request->url(), 'dataEmissaoInicial=2023-01-01+00%3A00%3A00') &&
                   str_contains($request->url(), 'dataEmissaoFinal=2023-01-31+23%3A59%3A59');
        });
    }

    /** @test */
    public function it_can_fetch_invoices_by_status()
    {
        $mockResponse = [
            'data' => [
                [
                    'id' => 12345678,
                    'numero' => '6541',
                    'situacao' => 5,
                    'dataEmissao' => '2023-01-12 09:52:12'
                ]
            ]
        ];

        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response($mockResponse, 200)
        ]);

        $invoices = $this->blingService->fetchInvoicesByStatus(5);

        $this->assertIsArray($invoices);
        $this->assertCount(1, $invoices);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.bling.com.br/Api/v3/nfe') &&
                   str_contains($request->url(), 'situacao=5');
        });
    }

    /** @test */
    public function it_can_fetch_authorized_invoices()
    {
        $mockResponse = [
            'data' => [
                [
                    'id' => 12345678,
                    'numero' => '6541',
                    'situacao' => 5,
                    'dataEmissao' => '2023-01-12 09:52:12'
                ]
            ]
        ];

        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response($mockResponse, 200)
        ]);

        $invoices = $this->blingService->fetchAuthorizedInvoices();

        $this->assertIsArray($invoices);
        $this->assertCount(1, $invoices);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.bling.com.br/Api/v3/nfe') &&
                   str_contains($request->url(), 'situacao=5'); // Status autorizada
        });
    }

    /** @test */
    public function it_handles_api_errors_gracefully()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response(['error' => 'Unauthorized'], 401)
        ]);

        $invoices = $this->blingService->fetchInvoices();

        $this->assertIsArray($invoices);
        $this->assertEmpty($invoices);
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response('', 408)
        ]);

        $invoices = $this->blingService->fetchInvoices();

        $this->assertIsArray($invoices);
        $this->assertEmpty($invoices);
    }

    /** @test */
    public function it_validates_invoice_data_correctly()
    {
        $validInvoice = [
            'id' => 12345678,
            'numero' => '6541',
            'situacao' => 5,
            'dataEmissao' => '2023-01-12 09:52:12'
        ];

        $invalidInvoice = [
            'id' => 12345678,
            'numero' => '6541',
            'situacao' => 1, // Status pendente, não autorizada
            'dataEmissao' => '2023-01-12 09:52:12'
        ];

        $this->assertTrue($this->blingService->isValidInvoice($validInvoice));
        $this->assertFalse($this->blingService->isValidInvoice($invalidInvoice));
    }

    /** @test */
    public function it_extracts_invoice_data_correctly()
    {
        $rawInvoice = [
            'id' => 12345678,
            'tipo' => 1,
            'situacao' => 5,
            'numero' => '6541',
            'dataEmissao' => '2023-01-12 09:52:12',
            'dataOperacao' => '2023-01-12 09:52:12',
            'chaveAcesso' => '35250145752681000182650010000266371238060760',
            'contato' => [
                'id' => 12345678,
                'nome' => 'Cliente Teste'
            ],
            'loja' => [
                'id' => 12345678
            ]
        ];

        $extractedData = $this->blingService->extractInvoiceData($rawInvoice);

        $this->assertEquals(12345678, $extractedData['bling_id']);
        $this->assertEquals('6541', $extractedData['invoice_number']);
        $this->assertEquals('35250145752681000182650010000266371238060760', $extractedData['access_key']);
        $this->assertEquals('2023-01-12 09:52:12', $extractedData['issue_date']);
        $this->assertEquals(5, $extractedData['status']);
        $this->assertEquals(1, $extractedData['type']);
        $this->assertIsArray($extractedData['contact']);
        $this->assertIsArray($extractedData['store']);
        $this->assertIsArray($extractedData['raw_data']);
    }

    /** @test */
    public function it_handles_invalid_json_response()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response('invalid json', 200)
        ]);

        $invoices = $this->blingService->fetchInvoices();

        $this->assertIsArray($invoices);
        $this->assertEmpty($invoices);
    }

    /** @test */
    public function it_uses_correct_api_endpoint()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response(['data' => []], 200)
        ]);

        $this->blingService->fetchInvoices();

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://api.bling.com.br/Api/v3/nfe');
        });
    }

    /** @test */
    public function it_includes_authentication_headers()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response(['data' => []], 200)
        ]);

        $this->blingService->fetchInvoices();

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer test_api_key') &&
                   $request->hasHeader('Content-Type', 'application/json') &&
                   $request->hasHeader('Accept', 'application/json');
        });
    }

    /** @test */
    public function it_handles_empty_response()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe*' => Http::response(['data' => []], 200)
        ]);

        $invoices = $this->blingService->fetchInvoices();

        $this->assertIsArray($invoices);
        $this->assertEmpty($invoices);
    }

    /** @test */
    public function it_can_get_invoice_pdf_url()
    {
        $mockResponse = [
            'data' => [
                'url' => 'https://api.bling.com.br/Api/v3/nfe/12345678/pdf/download'
            ]
        ];

        Http::fake([
            'api.bling.com.br/Api/v3/nfe/12345678/pdf*' => Http::response($mockResponse, 200)
        ]);

        $pdfUrl = $this->blingService->getInvoicePdfUrl('12345678');

        $this->assertEquals('https://api.bling.com.br/Api/v3/nfe/12345678/pdf/download', $pdfUrl);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'https://api.bling.com.br/Api/v3/nfe/12345678/pdf') &&
                   $request->hasHeader('Authorization', 'Bearer test_api_key');
        });
    }

    /** @test */
    public function it_returns_null_when_pdf_url_not_found()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe/12345678/pdf*' => Http::response(['data' => []], 200)
        ]);

        $pdfUrl = $this->blingService->getInvoicePdfUrl('12345678');

        $this->assertNull($pdfUrl);
    }

    /** @test */
    public function it_handles_pdf_url_api_errors()
    {
        Http::fake([
            'api.bling.com.br/Api/v3/nfe/12345678/pdf*' => Http::response(['error' => 'Not Found'], 404)
        ]);

        $pdfUrl = $this->blingService->getInvoicePdfUrl('12345678');

        $this->assertNull($pdfUrl);
    }
}
