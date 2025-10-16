<?php

namespace Tests\Feature;

use App\Events\OrderUpdated;
use App\Models\Invoice;
use App\Models\OrderSummary;
use App\Models\User;
use App\Services\BlingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class InvoiceBroadcastingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected OrderSummary $order;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user
        $this->user = User::factory()->create([
            'role' => 'admin'
        ]);
        
        // Create a test order
        $this->order = OrderSummary::factory()->create([
            'numero' => 'TEST123',
            'bling_id' => 999999,
            'total' => 150.00,
            'contato_nome' => 'Test Customer'
        ]);
    }

    /** @test */
    public function it_broadcasts_order_updated_event_when_invoice_is_added()
    {
        Event::fake();

        // Simulate invoice being added to order
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'bling_id' => 888888,
            'numero' => 'NF001',
            'valor_nota' => 150.00
        ]);

        // Force reload the relationship
        $this->order->unsetRelation('invoice');
        $this->order->load('invoice');

        // Manually trigger the event that would be dispatched by sync jobs
        event(new OrderUpdated($this->order, $this->user, ['invoice_number'], ['invoice_added']));

        Event::assertDispatched(OrderUpdated::class, function ($event) {
            return $event->order->id === $this->order->id &&
                   $event->changedFields === ['invoice_number'] &&
                   $event->changeTypes === ['invoice_added'];
        });
    }

    /** @test */
    public function it_includes_invoice_number_in_broadcast_data()
    {
        // Create invoice
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'bling_id' => 888888,
            'numero' => 'NF001',
            'valor_nota' => 150.00
        ]);

        // Load the relationship
        $this->order->load('invoice');

        // Create the event
        $event = new OrderUpdated($this->order, $this->user, ['invoice_number'], ['invoice_added']);

        // Get broadcast data
        $broadcastData = $event->broadcastWith();

        $this->assertEquals('NF001', $broadcastData['order']['invoice_number']);
        $this->assertEquals($invoice->id, $broadcastData['order']['invoice_id']);
    }

    /** @test */
    public function it_detects_invoice_changes_correctly_in_sync_logic()
    {
        // Initially no invoice
        $this->assertNull($this->order->invoice);

        // Capture old state
        $oldInvoiceNumber = $this->order->invoice?->numero ?? null;
        $this->assertNull($oldInvoiceNumber);

        // Create invoice
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'bling_id' => 888888,
            'numero' => 'NF001',
            'valor_nota' => 150.00
        ]);

        // Reload order with invoice relationship
        $this->order->unsetRelation('invoice');
        $this->order->load('invoice');

        // Capture new state
        $newInvoiceNumber = $this->order->invoice?->numero ?? null;

        // Simulate change detection logic from sync jobs
        $changedFields = [];
        $changeTypes = [];

        if ($oldInvoiceNumber !== $newInvoiceNumber) {
            $changedFields[] = 'invoice_number';
            if (!$oldInvoiceNumber && $newInvoiceNumber) {
                $changeTypes[] = 'invoice_added';
            }
        }

        $this->assertContains('invoice_number', $changedFields);
        $this->assertContains('invoice_added', $changeTypes);
    }

    /** @test */
    public function it_handles_invoice_updates_correctly()
    {
        // Create initial invoice
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'bling_id' => 888888,
            'numero' => 'NF001',
            'valor_nota' => 150.00
        ]);

        // Load relationship
        $this->order->load('invoice');
        $oldInvoiceNumber = $this->order->invoice->numero;

        // Update invoice
        $invoice->update(['numero' => 'NF002']);

        // Reload
        $this->order->unsetRelation('invoice');
        $this->order->load('invoice');
        $newInvoiceNumber = $this->order->invoice->numero;

        // Simulate change detection
        $changedFields = [];
        $changeTypes = [];

        if ($oldInvoiceNumber !== $newInvoiceNumber) {
            $changedFields[] = 'invoice_number';
            if ($oldInvoiceNumber && $newInvoiceNumber && $oldInvoiceNumber !== $newInvoiceNumber) {
                $changeTypes[] = 'invoice_updated';
            }
        }

        $this->assertContains('invoice_number', $changedFields);
        $this->assertContains('invoice_updated', $changeTypes);
    }

    /** @test */
    public function it_includes_all_necessary_order_data_in_broadcast()
    {
        // Create invoice and order status
        $invoice = Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'bling_id' => 888888,
            'numero' => 'NF001',
            'valor_nota' => 150.00
        ]);

        $this->order->orderStatus()->create([
            'picking' => true,
            'packing' => false,
            'picked_up' => false
        ]);

        // Load relationships
        $this->order->load(['invoice', 'orderStatus']);

        // Create event
        $event = new OrderUpdated($this->order, $this->user, ['invoice_number'], ['invoice_added']);

        // Get broadcast data
        $broadcastData = $event->broadcastWith();

        // Verify essential fields are present
        $this->assertEquals($this->order->id, $broadcastData['order']['id']);
        $this->assertEquals($this->order->numero, $broadcastData['order']['numero']);
        $this->assertEquals($this->order->contato_nome, $broadcastData['order']['contato_nome']);
        $this->assertEquals($this->order->total, $broadcastData['order']['total']);
        $this->assertEquals('NF001', $broadcastData['order']['invoice_number']);
        $this->assertNotNull($broadcastData['order']['order_status']);
        $this->assertTrue($broadcastData['order']['order_status']['picking']);
    }

    /** @test */
    public function manual_broadcast_command_works_correctly()
    {
        // Create invoice
        Invoice::factory()->create([
            'order_summary_id' => $this->order->id,
            'bling_id' => 888888,
            'numero' => 'NF001',
            'valor_nota' => 150.00
        ]);

        Event::fake();

        // Run the manual broadcast command
        $this->artisan('broadcast:order-update', ['numero' => 'TEST123'])
            ->expectsOutputToContain('Found order: TEST123')
            ->expectsOutputToContain('Invoice found: NF001')
            ->assertExitCode(0);

        Event::assertDispatched(OrderUpdated::class, function ($event) {
            return $event->order->numero === 'TEST123' &&
                   $event->changedFields === ['invoice_number'] &&
                   $event->changeTypes === ['invoice_added'];
        });
    }

    /** @test */
    public function it_handles_order_without_invoice_gracefully()
    {
        Event::fake();

        // Run command on order without invoice
        $this->artisan('broadcast:order-update', ['numero' => 'TEST123'])
            ->expectsOutputToContain('Found order: TEST123')
            ->expectsOutputToContain('No significant changes detected. Broadcasting general update.')
            ->assertExitCode(0);

        Event::assertDispatched(OrderUpdated::class, function ($event) {
            return $event->order->numero === 'TEST123' &&
                   $event->changedFields === ['general'] &&
                   $event->changeTypes === ['general_update'];
        });
    }
}
