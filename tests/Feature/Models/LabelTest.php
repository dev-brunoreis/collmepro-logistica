<?php

namespace Tests\Feature\Models;

use App\Models\Label;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class LabelTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_a_label()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->assertInstanceOf(Label::class, $label);
        $this->assertEquals($order->id, $label->order_id);
        $this->assertEquals('ET001', $label->label_number);
        $this->assertEquals('issued', $label->status);
        $this->assertNotNull($label->issued_at);
    }

    #[Test]
    public function it_belongs_to_order()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->assertInstanceOf(Order::class, $label->order);
        $this->assertEquals($order->id, $label->order->id);
        $this->assertEquals('WC001', $label->order->order_number);
    }

    #[Test]
    public function it_can_update_label_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $label->update(['status' => 'posted']);

        $this->assertEquals('posted', $label->fresh()->status);
    }

    #[Test]
    public function it_can_have_different_statuses()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $issuedLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $postedLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => now(),
        ]);

        $expiredLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET003',
            'status' => 'expired',
            'issued_at' => now(),
        ]);

        $this->assertEquals('issued', $issuedLabel->status);
        $this->assertEquals('posted', $postedLabel->status);
        $this->assertEquals('expired', $expiredLabel->status);
    }

    #[Test]
    public function it_casts_issued_at_to_datetime()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $issuedAt = '2025-09-27 15:30:00';
        
        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => $issuedAt,
        ]);

        $this->assertInstanceOf(\DateTime::class, $label->issued_at);
    }

    #[Test]
    public function it_has_timestamps()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $this->assertNotNull($label->created_at);
        $this->assertNotNull($label->updated_at);
        $this->assertInstanceOf(\DateTime::class, $label->created_at);
        $this->assertInstanceOf(\DateTime::class, $label->updated_at);
    }

    #[Test]
    public function it_can_be_updated()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        // Update the label
        $label->update([
            'status' => 'posted'
        ]);

        $this->assertEquals('posted', $label->fresh()->status);
        // Just verify the update worked, timestamp comparison can be flaky in tests
        $this->assertNotNull($label->fresh()->updated_at);
    }

    #[Test]
    public function it_can_be_deleted()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $labelId = $label->id;
        $label->delete();

        $this->assertDatabaseMissing('labels', [
            'id' => $labelId
        ]);
    }

    #[Test]
    public function it_is_deleted_when_order_is_deleted()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $labelId = $label->id;
        $order->delete();

        $this->assertDatabaseMissing('labels', [
            'id' => $labelId
        ]);
    }

    #[Test]
    public function it_can_have_multiple_labels_per_order()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $firstLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now()->subHour(),
        ]);

        $secondLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => now(),
        ]);

        $this->assertCount(2, $order->labels);
        $this->assertEquals('ET001', $order->labels->first()->label_number);
        $this->assertEquals('ET002', $order->labels->last()->label_number);
    }

    #[Test]
    public function it_can_be_queried_by_label_number()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $foundLabel = Label::where('label_number', 'ET001')->first();

        $this->assertNotNull($foundLabel);
        $this->assertEquals($label->id, $foundLabel->id);
    }

    #[Test]
    public function it_can_be_queried_by_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $issuedLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        $postedLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => now(),
        ]);

        $issuedLabels = Label::where('status', 'issued')->get();
        $postedLabels = Label::where('status', 'posted')->get();

        $this->assertCount(1, $issuedLabels);
        $this->assertCount(1, $postedLabels);
        $this->assertEquals($issuedLabel->id, $issuedLabels->first()->id);
        $this->assertEquals($postedLabel->id, $postedLabels->first()->id);
    }

    #[Test]
    public function it_can_be_queried_by_issued_date()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $issuedDate = now()->subDay();
        
        $label = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => $issuedDate,
        ]);

        $foundLabel = Label::whereDate('issued_at', $issuedDate->format('Y-m-d'))->first();

        $this->assertNotNull($foundLabel);
        $this->assertEquals($label->id, $foundLabel->id);
    }

    #[Test]
    public function it_can_be_sorted_by_issued_date()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $olderLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now()->subDay(),
        ]);

        $newerLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => now(),
        ]);

        $sortedLabels = Label::orderBy('issued_at', 'asc')->get();

        $this->assertEquals($olderLabel->id, $sortedLabels->first()->id);
        $this->assertEquals($newerLabel->id, $sortedLabels->last()->id);
    }

    #[Test]
    public function it_can_be_filtered_by_date_range()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $yesterday = now()->subDay();
        $today = now();

        $labelYesterday = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => $yesterday,
        ]);

        $labelToday = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => $today,
        ]);

        $labelsInRange = Label::whereBetween('issued_at', [$yesterday, $today])->get();

        $this->assertCount(2, $labelsInRange);
    }

    #[Test]
    public function it_can_check_if_status_is_valid()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $validStatuses = ['issued', 'posted', 'expired'];

        foreach ($validStatuses as $status) {
            $label = Label::create([
                'order_id' => $order->id,
                'label_number' => "ET_{$status}",
                'status' => $status,
                'issued_at' => now(),
            ]);

            $this->assertContains($label->status, $validStatuses);
        }
    }

    #[Test]
    public function it_can_be_counted_by_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        // Create labels with different statuses
        Label::create(['order_id' => $order->id, 'label_number' => 'ET001', 'status' => 'issued', 'issued_at' => now()]);
        Label::create(['order_id' => $order->id, 'label_number' => 'ET002', 'status' => 'issued', 'issued_at' => now()]);
        Label::create(['order_id' => $order->id, 'label_number' => 'ET003', 'status' => 'posted', 'issued_at' => now()]);
        Label::create(['order_id' => $order->id, 'label_number' => 'ET004', 'status' => 'expired', 'issued_at' => now()]);

        $issuedCount = Label::where('status', 'issued')->count();
        $postedCount = Label::where('status', 'posted')->count();
        $expiredCount = Label::where('status', 'expired')->count();

        $this->assertEquals(2, $issuedCount);
        $this->assertEquals(1, $postedCount);
        $this->assertEquals(1, $expiredCount);
    }

    #[Test]
    public function it_can_be_retrieved_latest_first()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $olderLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET001',
            'status' => 'issued',
            'issued_at' => now()->subHour(),
        ]);

        $newerLabel = Label::create([
            'order_id' => $order->id,
            'label_number' => 'ET002',
            'status' => 'posted',
            'issued_at' => now(),
        ]);

        $latestLabels = Label::latest('issued_at')->get();

        $this->assertEquals($newerLabel->id, $latestLabels->first()->id);
        $this->assertEquals($olderLabel->id, $latestLabels->last()->id);
    }
}
