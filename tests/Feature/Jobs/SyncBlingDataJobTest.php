<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncBlingDataJob;
use App\Models\Order;
use App\Models\Invoice;
use App\Services\BlingService;
use App\Services\BlingLogisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SyncBlingDataJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_sync_specific_order_with_bling()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '12345678',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('12345678')
            ->once()
            ->andReturn([
                'id' => 12345678,
                'numero' => '6541',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12',
                'chaveAcesso' => '35250145752681000182650010000266371238060760'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(true);

        $mockBlingService->shouldReceive('fetchInvoiceById')
            ->with(12345678)
            ->once()
            ->andReturn([
                'id' => 12345678,
                'numero' => '6541',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12',
                'chaveAcesso' => '35250145752681000182650010000266371238060760'
            ]);

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 12345678,
                'invoice_number' => '6541',
                'access_key' => '35250145752681000182650010000266371238060760',
                'issue_date' => '2023-01-12 09:52:12',
                'operation_date' => '2023-01-12 09:52:12',
                'status' => 5,
                'type' => 1,
                'valor_nota' => 100.00,
                'valor_frete' => 10.00,
                'link_pdf' => null,
                'link_danfe' => null,
                'serie' => null,
                'raw_data' => [],
                'transport' => []
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(12345678)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/12345678/pdf/download');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was updated
        $order->refresh();
        $this->assertEquals('6541', $order->nfe_number);

        // Assert invoice was created
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'invoice_number' => '6541'
        ]);
    }

    /** @test */
    public function it_can_sync_by_order_number()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '87654321',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('87654321')
            ->once()
            ->andReturn([
                'id' => 87654321,
                'numero' => '7654',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(true);

        $mockBlingService->shouldReceive('fetchInvoiceById')
            ->with(87654321)
            ->once()
            ->andReturn([
                'id' => 87654321,
                'numero' => '7654',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 87654321,
                'invoice_number' => '7654',
                'access_key' => '35250145752681000182650010000266371238060760',
                'issue_date' => '2023-01-12 09:52:12',
                'operation_date' => '2023-01-12 09:52:12',
                'status' => 5,
                'type' => 1,
                'valor_nota' => 150.00,
                'valor_frete' => 15.00,
                'link_pdf' => null,
                'link_danfe' => null,
                'serie' => null,
                'raw_data' => [],
                'transport' => []
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(87654321)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/87654321/pdf/download');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was updated
        $order->refresh();
        $this->assertEquals('7654', $order->nfe_number);
    }

    /** @test */
    public function it_skips_orders_without_invoice_in_bling()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '99999999',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService to return null (no invoice found)
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('99999999')
            ->once()
            ->andReturn(null);

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was not updated
        $order->refresh();
        $this->assertNull($order->nfe_number);
        $this->assertNull($order->nfe_pdf_url);
    }

    /** @test */
    public function it_skips_invalid_invoices()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '11111111',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('11111111')
            ->once()
            ->andReturn([
                'id' => 11111111,
                'numero' => '1111',
                'situacao' => 1, // Status pendente (em elaboração), não autorizada
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(false); // Invalid invoice (status 1 não é processado)

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was not updated
        $order->refresh();
        $this->assertNull($order->nfe_number);
    }

    /** @test */
    public function it_handles_orders_without_invoice_for_bulk_sync()
    {
        // Create multiple test orders without NFE
        $orders = Order::factory()->count(3)->create([
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        
        // First order has invoice
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with($orders[0]->order_number)
            ->once()
            ->andReturn([
                'id' => 12345678,
                'numero' => '6541',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(true);

        $mockBlingService->shouldReceive('fetchInvoiceById')
            ->with(12345678)
            ->once()
            ->andReturn([
                'id' => 12345678,
                'numero' => '6541',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 12345678,
                'invoice_number' => '6541',
                'access_key' => '35250145752681000182650010000266371238060760',
                'issue_date' => '2023-01-12 09:52:12',
                'operation_date' => '2023-01-12 09:52:12',
                'status' => 5,
                'type' => 1,
                'valor_nota' => 200.00,
                'valor_frete' => 20.00,
                'link_pdf' => null,
                'link_danfe' => null,
                'serie' => null,
                'raw_data' => [],
                'transport' => []
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(12345678)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/12345678/pdf/download');

        // Second order has no invoice
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with($orders[1]->order_number)
            ->once()
            ->andReturn(null);

        // Third order has no invoice
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with($orders[2]->order_number)
            ->once()
            ->andReturn(null);

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute jobs for each order individually
        $job1 = new SyncBlingDataJob($orders[0]->id);
        $job1->handle();

        $job2 = new SyncBlingDataJob($orders[1]->id);
        $job2->handle();

        $job3 = new SyncBlingDataJob($orders[2]->id);
        $job3->handle();

        // Assert first order was updated
        $orders[0]->refresh();
        $this->assertEquals('6541', $orders[0]->nfe_number);

        // Assert other orders were not updated
        $orders[1]->refresh();
        $orders[2]->refresh();
        $this->assertNull($orders[1]->nfe_number);
        $this->assertNull($orders[2]->nfe_number);
    }

    /** @test */
    public function it_handles_job_failures_gracefully()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '55555555',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService to throw exception
        $mockBlingService = $this->createMock(BlingService::class);
        $mockBlingService->expects($this->once())
            ->method('fetchInvoiceByOrderNumber')
            ->with('55555555')
            ->willThrowException(new \Exception('API Error'));

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job - it should handle the exception gracefully
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was not updated (exception was caught and handled)
        $order->refresh();
        $this->assertNull($order->nfe_number);
        $this->assertNull($order->nfe_pdf_url);
    }

    /** @test */
    public function it_processes_invoices_with_status_6_emitida_danfe()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '88888889',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('88888889')
            ->once()
            ->andReturn([
                'id' => 88888889,
                'numero' => '8888',
                'situacao' => 6, // Status Emitida DANFE
                'dataEmissao' => '2023-01-12 09:52:12',
                'chaveAcesso' => '35250145752681000182650010000888812345678901'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(true); // Status 6 é válido (Emitida DANFE)

        $mockBlingService->shouldReceive('fetchInvoiceById')
            ->with(88888889)
            ->once()
            ->andReturn([
                'id' => 88888889,
                'numero' => '8888',
                'situacao' => 6,
                'dataEmissao' => '2023-01-12 09:52:12',
                'chaveAcesso' => '35250145752681000182650010000888812345678901',
                'transporte' => [
                    'volumes' => [
                        [
                            'codigoRastreamento' => 'AN008645069BR'
                        ]
                    ],
                    'etiqueta' => [
                        'nome' => 'Correios'
                    ]
                ]
            ]);

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 88888889,
                'invoice_number' => '8888',
                'access_key' => '35250145752681000182650010000888812345678901',
                'issue_date' => '2023-01-12 09:52:12',
                'operation_date' => '2023-01-12 09:52:12',
                'status' => 6,
                'type' => 1,
                'valor_nota' => 50.00,
                'valor_frete' => 5.00,
                'link_pdf' => null,
                'link_danfe' => null,
                'serie' => null,
                'raw_data' => [],
                'transport' => [
                    'logistics_provider' => 'Correios',
                    'tracking_code' => 'AN008645069BR'
                ]
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(88888889)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/88888889/pdf/download');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was updated with DANFE emitted invoice
        $order->refresh();
        $this->assertEquals('8888', $order->nfe_number);
        $this->assertNotNull($order->nfe_pdf_url);

        // Assert invoice was created
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'invoice_number' => '8888',
            'status' => 6 // Status Emitida DANFE
        ]);
    }

    /** @test */
    public function it_fetches_tracking_info_when_invoice_has_danfe()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '99999999',
            'nfe_number' => null,
            'nfe_pdf_url' => null,
            'tracking_code' => null,
            'logistics_provider' => null,
            'tracking_status' => null
        ]);

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('99999999')
            ->once()
            ->andReturn([
                'id' => 99999999,
                'numero' => '9999',
                'situacao' => 6, // Status Emitida DANFE
                'dataEmissao' => '2023-01-12 09:52:12',
                'chaveAcesso' => '35250145752681000182650010000999912345678901'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(true);

        $mockBlingService->shouldReceive('fetchInvoiceById')
            ->with(99999999)
            ->once()
            ->andReturn([
                'id' => 99999999,
                'numero' => '9999',
                'situacao' => 6,
                'dataEmissao' => '2023-01-12 09:52:12',
                'chaveAcesso' => '35250145752681000182650010000999912345678901',
                'transporte' => [
                    'volumes' => [
                        [
                            'codigoRastreamento' => 'AN008645069BR'
                        ]
                    ],
                    'etiqueta' => [
                        'nome' => 'Correios'
                    ]
                ]
            ]);

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 99999999,
                'invoice_number' => '9999',
                'access_key' => '35250145752681000182650010000999912345678901',
                'issue_date' => '2023-01-12 09:52:12',
                'operation_date' => '2023-01-12 09:52:12',
                'status' => 6, // Emitida DANFE
                'type' => 1,
                'valor_nota' => 75.00,
                'valor_frete' => 8.00,
                'link_pdf' => null,
                'link_danfe' => null,
                'serie' => null,
                'raw_data' => [],
                'transport' => [
                    'logistics_provider' => 'Correios',
                    'tracking_code' => 'AN008645069BR'
                ]
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(99999999)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/99999999/pdf/download');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was updated with NFE and tracking data from transport info
        $order->refresh();
        $this->assertEquals('9999', $order->nfe_number);
        $this->assertNotNull($order->nfe_pdf_url);
        $this->assertEquals('AN008645069BR', $order->tracking_code);
        $this->assertEquals('Correios', $order->logistics_provider);

        // Assert invoice was created
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'invoice_number' => '9999',
            'status' => 6
        ]);
    }

    /** @test */
    public function it_skips_orders_that_already_have_nfe()
    {
        // Create test order that already has NFE with tracking
        $order = Order::factory()->create([
            'order_number' => '88888888',
            'nfe_number' => '1234',
            'nfe_pdf_url' => 'https://example.com/nfe/1234.pdf',
            'tracking_code' => 'AB123456789BR',
            'logistics_provider' => 'Correios'
        ]);

        // Create authorized invoice (status 6) for the order
        Invoice::create([
            'order_id' => $order->id,
            'invoice_number' => '1234',
            'status' => 6, // Authorized
            'issued_at' => now()
        ]);

        // Mock BlingService - should not be called
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldNotReceive('fetchInvoiceByOrderNumber');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert order was not updated (NFE data remains the same)
        $order->refresh();
        $this->assertEquals('1234', $order->nfe_number);
        $this->assertEquals('https://example.com/nfe/1234.pdf', $order->nfe_pdf_url);
    }

    /** @test */
    public function it_creates_invoice_record_correctly()
    {
        // Create test order
        $order = Order::factory()->create([
            'order_number' => '77777777',
            'nfe_number' => null,
            'nfe_pdf_url' => null
        ]);

        $invoiceData = [
            'bling_id' => 77777777,
            'invoice_number' => '7777',
            'access_key' => '35250145752681000182650010000266371238060760',
            'issue_date' => '2023-01-12 09:52:12',
            'operation_date' => '2023-01-12 09:52:12',
            'status' => 5,
            'type' => 1,
            'valor_nota' => 300.00,
            'valor_frete' => 30.00,
            'link_pdf' => null,
            'link_danfe' => null,
            'serie' => null,
            'raw_data' => ['test' => 'data'],
            'transport' => []
        ];

        // Mock BlingService
        $mockBlingService = $this->mock(BlingService::class);
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with('77777777')
            ->once()
            ->andReturn([
                'id' => 77777777,
                'numero' => '7777',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(true);

        $mockBlingService->shouldReceive('fetchInvoiceById')
            ->with(77777777)
            ->once()
            ->andReturn([
                'id' => 77777777,
                'numero' => '7777',
                'situacao' => 5,
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn($invoiceData);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(77777777)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/77777777/pdf/download');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob($order->id);
        $job->handle();

        // Assert invoice record was created
        $this->assertDatabaseHas('invoices', [
            'order_id' => $order->id,
            'invoice_number' => '7777',
            'issued_at' => '2023-01-12 09:52:12'
        ]);

        $invoice = Invoice::where('order_id', $order->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals('7777', $invoice->invoice_number);
        $this->assertEquals('2023-01-12 09:52:12', $invoice->issued_at->format('Y-m-d H:i:s'));
    }
}
