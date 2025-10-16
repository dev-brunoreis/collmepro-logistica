<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\OrderSummary;
use App\Models\Invoice;
use App\Models\User;
use App\Jobs\SyncInvoiceJob;
use App\Events\InvoiceSynced;
use App\Services\CollmeProBlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;

class SyncInvoiceJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create admin user for broadcasts
        User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);
    }

    public function test_sync_invoice_job_fetches_and_saves_invoice(): void
    {
        Event::fake([InvoiceSynced::class]);

        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'source' => 'Collmepro',
        ]);

        // Mock Bling service
        $blingServiceMock = Mockery::mock(CollmeProBlingService::class);
        
        $blingServiceMock->shouldReceive('getCompleteOrderData')
            ->once()
            ->with(12345)
            ->andReturn([
                'id' => 12345,
                'numero' => 'TEST-001',
                'notaFiscal' => [
                    'id' => 67890,
                ],
            ]);

        $blingServiceMock->shouldReceive('getCompleteInvoiceData')
            ->once()
            ->with(67890)
            ->andReturn([
                'id' => 67890,
                'numero' => 'NFE-12345',
                'tipo' => 1,
                'situacao' => 6,
                'valorNota' => 150.00,
                'dataEmissao' => '2025-10-15',
                'contato' => ['nome' => 'Test Customer'],
                'transporte' => ['volumes' => []],
            ]);

        $this->app->instance(CollmeProBlingService::class, $blingServiceMock);

        // Execute job
        $job = new SyncInvoiceJob($order->id);
        $job->handle();

        // Assert invoice was created
        $this->assertDatabaseHas('invoices', [
            'bling_id' => 67890,
            'numero' => 'NFE-12345',
            'order_summary_id' => $order->id,
        ]);

        // Assert event was emitted
        Event::assertDispatched(InvoiceSynced::class);
    }

    public function test_sync_invoice_job_skips_if_no_invoice_in_order(): void
    {
        Event::fake();

        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'source' => 'Collmepro',
        ]);

        // Mock Bling service with no invoice
        $blingServiceMock = Mockery::mock(CollmeProBlingService::class);
        
        $blingServiceMock->shouldReceive('getCompleteOrderData')
            ->once()
            ->andReturn([
                'id' => 12345,
                'numero' => 'TEST-001',
                // No notaFiscal
            ]);

        $this->app->instance(CollmeProBlingService::class, $blingServiceMock);

        // Execute job
        $job = new SyncInvoiceJob($order->id);
        $job->handle();

        // Assert no invoice was created
        $this->assertDatabaseCount('invoices', 0);

        // Assert event was NOT emitted
        Event::assertNotDispatched(InvoiceSynced::class);
    }

    public function test_sync_invoice_job_implements_idempotency(): void
    {
        $order = OrderSummary::factory()->create();

        // Set cache to simulate recent processing
        Cache::put("invoice_synced_{$order->id}", now(), 300);

        $job = new SyncInvoiceJob($order->id);
        $job->handle();

        // Assert no database queries were made (idempotency worked)
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_sync_invoice_job_updates_existing_invoice(): void
    {
        Event::fake();

        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'source' => 'Collmepro',
        ]);

        // Create existing invoice
        Invoice::factory()->create([
            'bling_id' => 67890,
            'order_summary_id' => $order->id,
            'numero' => 'NFE-OLD',
        ]);

        // Mock Bling service
        $blingServiceMock = Mockery::mock(CollmeProBlingService::class);
        
        $blingServiceMock->shouldReceive('getCompleteOrderData')
            ->once()
            ->andReturn([
                'id' => 12345,
                'notaFiscal' => ['id' => 67890],
            ]);

        $blingServiceMock->shouldReceive('getCompleteInvoiceData')
            ->once()
            ->andReturn([
                'id' => 67890,
                'numero' => 'NFE-UPDATED',
                'tipo' => 1,
                'situacao' => 6,
                'valorNota' => 200.00,
                'dataEmissao' => '2025-10-15',
                'contato' => [],
                'transporte' => ['volumes' => []],
            ]);

        $this->app->instance(CollmeProBlingService::class, $blingServiceMock);

        // Execute job
        $job = new SyncInvoiceJob($order->id);
        $job->handle();

        // Assert invoice was updated
        $this->assertDatabaseHas('invoices', [
            'bling_id' => 67890,
            'numero' => 'NFE-UPDATED',
        ]);

        // Assert only one invoice exists
        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_sync_invoice_job_records_failure_on_exception(): void
    {
        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'source' => 'Collmepro',
        ]);

        // Mock Bling service to throw exception
        $blingServiceMock = Mockery::mock(CollmeProBlingService::class);
        $blingServiceMock->shouldReceive('getCompleteOrderData')
            ->once()
            ->andThrow(new \Exception('API Error'));

        $this->app->instance(CollmeProBlingService::class, $blingServiceMock);

        // Execute job and expect exception
        $job = new SyncInvoiceJob($order->id);
        
        try {
            $job->handle();
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('API Error', $e->getMessage());
        }
    }

    public function test_sync_invoice_job_handles_missing_order(): void
    {
        Event::fake();

        // Execute job with non-existent order
        $job = new SyncInvoiceJob(99999);
        $job->handle();

        // Should not throw exception, just log and return
        Event::assertNotDispatched(InvoiceSynced::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

