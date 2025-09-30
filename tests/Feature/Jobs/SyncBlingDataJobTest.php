<?php

namespace Tests\Feature\Jobs;

use App\Jobs\SyncBlingDataJob;
use App\Models\Order;
use App\Models\Invoice;
use App\Services\BlingService;
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

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 12345678,
                'invoice_number' => '6541',
                'access_key' => '35250145752681000182650010000266371238060760',
                'issue_date' => '2023-01-12 09:52:12',
                'status' => 5,
                'type' => 1,
                'raw_data' => []
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

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 87654321,
                'invoice_number' => '7654',
                'issue_date' => '2023-01-12 09:52:12',
                'status' => 5,
                'type' => 1,
                'raw_data' => []
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(87654321)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/87654321/pdf/download');

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job
        $job = new SyncBlingDataJob(null, '87654321');
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
                'situacao' => 1, // Status pendente, não autorizada
                'dataEmissao' => '2023-01-12 09:52:12'
            ]);

        $mockBlingService->shouldReceive('isValidInvoice')
            ->once()
            ->andReturn(false); // Invalid invoice

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

        $mockBlingService->shouldReceive('extractInvoiceData')
            ->once()
            ->andReturn([
                'bling_id' => 12345678,
                'invoice_number' => '6541',
                'issue_date' => '2023-01-12 09:52:12',
                'status' => 5,
                'type' => 1,
                'raw_data' => []
            ]);

        $mockBlingService->shouldReceive('getInvoicePdfUrl')
            ->twice()
            ->with(12345678)
            ->andReturn('https://api.bling.com.br/Api/v3/nfe/12345678/pdf/download');

        // Second and third orders have no invoice
        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with($orders[1]->order_number)
            ->once()
            ->andReturn(null);

        $mockBlingService->shouldReceive('fetchInvoiceByOrderNumber')
            ->with($orders[2]->order_number)
            ->once()
            ->andReturn(null);

        $this->app->instance(BlingService::class, $mockBlingService);

        // Execute job for bulk sync (no specific order ID)
        $job = new SyncBlingDataJob();
        $job->handle();

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
            'status' => 5,
            'type' => 1,
            'raw_data' => ['test' => 'data']
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
