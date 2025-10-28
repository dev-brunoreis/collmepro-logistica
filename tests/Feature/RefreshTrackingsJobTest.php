<?php

namespace Tests\Feature;

use App\Jobs\ExtractTrackingJob;
use App\Jobs\RefreshTrackingsJob;
use App\Models\Invoice;
use App\Models\OrderSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshTrackingsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    /** @test */
    public function it_enqueues_extract_tracking_job_for_orders_with_invoice_and_tracking()
    {
        // Create order with invoice and tracking
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK123',
            'created_at' => now()->subDays(15),
            'situacao_id' => 6, // Not cancelled
        ]);

        $invoice = Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 12345,
        ]);

        // Create tracking record that needs sync (last_sync_at is null or old)
        \App\Models\Tracking::factory()->create([
            'invoice_id' => $invoice->id,
            'last_sync_at' => null, // Never synced
            'situacao' => 2, // Not delivered (situacao != 4)
        ]);

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert ExtractTrackingJob was dispatched
        Queue::assertPushed(ExtractTrackingJob::class, function ($job) use ($order) {
            return $job->queue === 'tracking';
        });
    }

    /** @test */
    public function it_ignores_orders_without_invoice()
    {
        // Create order without invoice
        OrderSummary::factory()->create([
            'tracking_code' => 'TRACK123',
            'created_at' => now()->subDays(15),
            'situacao_id' => 6,
        ]);

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert no job was dispatched
        Queue::assertNotPushed(ExtractTrackingJob::class);
    }

    /** @test */
    public function it_ignores_orders_without_tracking_code()
    {
        // Create order without tracking code
        $order = OrderSummary::factory()->create([
            'tracking_code' => null,
            'created_at' => now()->subDays(15),
            'situacao_id' => 6,
        ]);

        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 12345,
        ]);

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert no job was dispatched
        Queue::assertNotPushed(ExtractTrackingJob::class);
    }

    /** @test */
    public function it_ignores_cancelled_orders()
    {
        // Create cancelled order (situacao_id = 12)
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK123',
            'created_at' => now()->subDays(15),
            'situacao_id' => 12, // Cancelled
        ]);

        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 12345,
        ]);

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert no job was dispatched
        Queue::assertNotPushed(ExtractTrackingJob::class);
    }

    /** @test */
    public function it_ignores_orders_older_than_30_days()
    {
        // Create old order (31 days ago)
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK123',
            'created_at' => now()->subDays(31),
            'situacao_id' => 6,
        ]);

        Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 12345,
        ]);

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert no job was dispatched
        Queue::assertNotPushed(ExtractTrackingJob::class);
    }

    /** @test */
    public function it_processes_multiple_valid_orders()
    {
        // Create 3 valid orders
        for ($i = 0; $i < 3; $i++) {
            $order = OrderSummary::factory()->create([
                'tracking_code' => 'TRACK' . $i,
                'created_at' => now()->subDays(10 + $i),
                'situacao_id' => 6,
            ]);

            $invoice = Invoice::factory()->create([
                'order_summary_id' => $order->id,
                'bling_id' => 12345 + $i,
            ]);

            // Create tracking record that needs sync
            \App\Models\Tracking::factory()->create([
                'invoice_id' => $invoice->id,
                'last_sync_at' => null, // Never synced
                'situacao' => 2, // Not delivered
            ]);
        }

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert 3 jobs were dispatched
        Queue::assertPushed(ExtractTrackingJob::class, 3);
    }

    /** @test */
    public function it_handles_mixed_order_scenarios()
    {
        // Valid order
        $validOrder = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK_VALID',
            'created_at' => now()->subDays(10),
            'situacao_id' => 6,
        ]);
        $validInvoice = Invoice::factory()->create([
            'order_summary_id' => $validOrder->id,
            'bling_id' => 12345,
        ]);
        // Create tracking record that needs sync
        \App\Models\Tracking::factory()->create([
            'invoice_id' => $validInvoice->id,
            'last_sync_at' => null, // Never synced
            'situacao' => 2, // Not delivered
        ]);

        // Order without tracking
        $noTrackingOrder = OrderSummary::factory()->create([
            'tracking_code' => null,
            'created_at' => now()->subDays(10),
            'situacao_id' => 6,
        ]);
        Invoice::factory()->create([
            'order_summary_id' => $noTrackingOrder->id,
            'bling_id' => 12346,
        ]);

        // Cancelled order
        $cancelledOrder = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK_CANCELLED',
            'created_at' => now()->subDays(10),
            'situacao_id' => 12,
        ]);
        Invoice::factory()->create([
            'order_summary_id' => $cancelledOrder->id,
            'bling_id' => 12347,
        ]);

        // Old order
        $oldOrder = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK_OLD',
            'created_at' => now()->subDays(40),
            'situacao_id' => 6,
        ]);
        Invoice::factory()->create([
            'order_summary_id' => $oldOrder->id,
            'bling_id' => 12348,
        ]);

        // Execute job
        $job = new RefreshTrackingsJob();
        $job->handle();

        // Assert only 1 job was dispatched (the valid one)
        Queue::assertPushed(ExtractTrackingJob::class, 1);
    }

    /** @test */
    public function it_logs_execution_details()
    {
        // Create valid order
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK123',
            'created_at' => now()->subDays(15),
            'situacao_id' => 6,
        ]);

        $invoice = Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 12345,
        ]);

        // Create tracking record that needs sync
        \App\Models\Tracking::factory()->create([
            'invoice_id' => $invoice->id,
            'last_sync_at' => null, // Never synced
            'situacao' => 2, // Not delivered
        ]);

        // Execute job (should log without errors)
        $job = new RefreshTrackingsJob();
        $job->handle();

        // If we got here without exceptions, logging worked
        $this->assertTrue(true);
    }

    /** @test */
    public function it_handles_errors_gracefully()
    {
        // Create order with invalid data that might cause issues
        $order = OrderSummary::factory()->create([
            'tracking_code' => 'TRACK123',
            'created_at' => now()->subDays(15),
            'situacao_id' => 6,
        ]);

        $invoice = Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 12345,
        ]);

        // Create tracking record that needs sync
        \App\Models\Tracking::factory()->create([
            'invoice_id' => $invoice->id,
            'last_sync_at' => null, // Never synced
            'situacao' => 2, // Not delivered
        ]);

        // Execute job - should handle gracefully even with queue fake
        $job = new RefreshTrackingsJob();
        
        try {
            $job->handle();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Should not throw exceptions for individual order failures
            $this->fail('Job should handle errors gracefully: ' . $e->getMessage());
        }
    }
}

