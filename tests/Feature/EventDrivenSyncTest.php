<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\OrderSummary;
use App\Models\Invoice;
use App\Models\User;
use App\Events\OrderSynced;
use App\Events\InvoiceSynced;
use App\Events\TrackingSynced;
use App\Jobs\SyncInvoiceJob;
use App\Jobs\ExtractTrackingJob;
use App\Jobs\SyncSellerJob;
use App\Listeners\QueueInvoiceSync;
use App\Listeners\QueueTrackingExtraction;
use App\Listeners\QueueSellerSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

class EventDrivenSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'admin',
        ]);
    }

    public function test_order_synced_event_dispatches_invoice_and_seller_jobs(): void
    {
        Queue::fake();
        Event::fake([SyncInvoiceJob::class, SyncSellerJob::class]);

        $order = OrderSummary::factory()->create([
            'bling_id' => 123,
            'numero' => 'TEST-001',
            'source' => 'Collmepro',
        ]);

        // Emit OrderSynced event
        event(new OrderSynced($order, true, []));

        // Assert invoice sync job was queued
        Queue::assertPushed(SyncInvoiceJob::class, function ($job) use ($order) {
            return $job->uniqueId() === "sync-invoice-{$order->id}";
        });

        // Assert seller sync job was queued
        Queue::assertPushed(SyncSellerJob::class, function ($job) use ($order) {
            return $job->uniqueId() === "sync-seller-{$order->id}";
        });
    }

    public function test_invoice_synced_event_dispatches_tracking_extraction_job(): void
    {
        Queue::fake();

        $order = OrderSummary::factory()->create();
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $order->id,
            'bling_id' => 456,
        ]);

        // Emit InvoiceSynced event
        event(new InvoiceSynced($invoice, true));

        // Assert tracking extraction job was queued
        Queue::assertPushed(ExtractTrackingJob::class, function ($job) use ($order) {
            return $job->uniqueId() === "extract-tracking-{$order->id}";
        });
    }

    public function test_queue_invoice_sync_listener_handles_order_synced(): void
    {
        Queue::fake();

        $order = OrderSummary::factory()->create();
        $event = new OrderSynced($order, true, []);

        $listener = new QueueInvoiceSync();
        $listener->handle($event);

        Queue::assertPushed(SyncInvoiceJob::class);
    }

    public function test_queue_tracking_extraction_listener_handles_invoice_synced(): void
    {
        Queue::fake();

        $order = OrderSummary::factory()->create();
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $order->id,
        ]);
        $event = new InvoiceSynced($invoice, true);

        $listener = new QueueTrackingExtraction();
        $listener->handle($event);

        Queue::assertPushed(ExtractTrackingJob::class);
    }

    public function test_queue_seller_sync_listener_handles_order_synced(): void
    {
        Queue::fake();

        $order = OrderSummary::factory()->create();
        $event = new OrderSynced($order, true, []);

        $listener = new QueueSellerSync();
        $listener->handle($event);

        Queue::assertPushed(SyncSellerJob::class);
    }

    public function test_events_contain_correct_data(): void
    {
        $order = OrderSummary::factory()->create();

        $orderSyncedEvent = new OrderSynced($order, true, ['total', 'status']);
        $this->assertEquals($order->id, $orderSyncedEvent->order->id);
        $this->assertTrue($orderSyncedEvent->wasCreated);
        $this->assertEquals(['total', 'status'], $orderSyncedEvent->changedFields);

        $invoice = Invoice::factory()->create(['order_summary_id' => $order->id]);
        $invoiceSyncedEvent = new InvoiceSynced($invoice, false);
        $this->assertEquals($invoice->id, $invoiceSyncedEvent->invoice->id);
        $this->assertFalse($invoiceSyncedEvent->wasCreated);

        $trackingSyncedEvent = new TrackingSynced($order, 'BR123456789');
        $this->assertEquals($order->id, $trackingSyncedEvent->order->id);
        $this->assertEquals('BR123456789', $trackingSyncedEvent->trackingCode);
    }

    public function test_event_flow_from_order_to_tracking(): void
    {
        Queue::fake();
        Event::fake([OrderSynced::class, InvoiceSynced::class]);

        // Step 1: Order synced
        $order = OrderSummary::factory()->create();
        
        // Manually call listeners (since events are faked)
        $orderSyncedEvent = new OrderSynced($order, true, []);
        (new QueueInvoiceSync())->handle($orderSyncedEvent);
        (new QueueSellerSync())->handle($orderSyncedEvent);

        Queue::assertPushed(SyncInvoiceJob::class, 1);
        Queue::assertPushed(SyncSellerJob::class, 1);

        // Step 2: Invoice synced
        $invoice = Invoice::factory()->create(['order_summary_id' => $order->id]);
        
        $invoiceSyncedEvent = new InvoiceSynced($invoice, true);
        (new QueueTrackingExtraction())->handle($invoiceSyncedEvent);

        Queue::assertPushed(ExtractTrackingJob::class, 1);
    }

    public function test_jobs_have_correct_queue_configuration(): void
    {
        $order = OrderSummary::factory()->create();

        $invoiceJob = new SyncInvoiceJob($order->id);
        $this->assertEquals(60, $invoiceJob->timeout);
        $this->assertEquals(3, $invoiceJob->tries);

        $trackingJob = new ExtractTrackingJob($order->id);
        $this->assertEquals(45, $trackingJob->timeout);
        $this->assertEquals(3, $trackingJob->tries);

        $sellerJob = new SyncSellerJob($order->id);
        $this->assertEquals(30, $sellerJob->timeout);
        $this->assertEquals(2, $sellerJob->tries);
    }

    public function test_jobs_have_unique_ids_for_idempotency(): void
    {
        $order = OrderSummary::factory()->create(['id' => 123]);

        $invoiceJob = new SyncInvoiceJob(123);
        $this->assertEquals('sync-invoice-123', $invoiceJob->uniqueId());

        $trackingJob = new ExtractTrackingJob(123);
        $this->assertEquals('extract-tracking-123', $trackingJob->uniqueId());

        $sellerJob = new SyncSellerJob(123);
        $this->assertEquals('sync-seller-123', $sellerJob->uniqueId());
    }
}

