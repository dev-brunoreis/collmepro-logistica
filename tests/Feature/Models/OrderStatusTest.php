<?php

namespace Tests\Feature\Models;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_an_order_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $this->assertInstanceOf(OrderStatus::class, $orderStatus);
        $this->assertEquals($order->id, $orderStatus->order_id);
        $this->assertFalse($orderStatus->picking);
        $this->assertFalse($orderStatus->packing);
        $this->assertFalse($orderStatus->picked_up);
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

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $this->assertInstanceOf(Order::class, $orderStatus->order);
        $this->assertEquals($order->id, $orderStatus->order->id);
    }

    #[Test]
    public function it_can_mark_picking()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $orderStatus->markPicking($user);

        $this->assertTrue($orderStatus->fresh()->picking);
        $this->assertEquals($user->id, $orderStatus->fresh()->picking_marked_by);
        $this->assertNotNull($orderStatus->fresh()->picking_marked_at);
    }

    #[Test]
    public function it_can_mark_packing()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $orderStatus->markPacking($user);

        $this->assertTrue($orderStatus->fresh()->packing);
        $this->assertEquals($user->id, $orderStatus->fresh()->packing_marked_by);
        $this->assertNotNull($orderStatus->fresh()->packing_marked_at);
    }

    #[Test]
    public function it_can_mark_picked_up()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $orderStatus->markPickedUp($user);

        $this->assertTrue($orderStatus->fresh()->picked_up);
        $this->assertEquals($user->id, $orderStatus->fresh()->picked_up_marked_by);
        $this->assertNotNull($orderStatus->fresh()->picked_up_marked_at);
    }

    #[Test]
    public function it_can_unmark_picking()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
            'picking_marked_by' => $user->id,
            'picking_marked_at' => now(),
        ]);

        $orderStatus->unmarkPicking($user);

        $this->assertFalse($orderStatus->fresh()->picking);
        $this->assertNull($orderStatus->fresh()->picking_marked_by);
        $this->assertNull($orderStatus->fresh()->picking_marked_at);
    }

    #[Test]
    public function it_can_unmark_packing()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => true,
            'picked_up' => false,
            'packing_marked_by' => $user->id,
            'packing_marked_at' => now(),
        ]);

        $orderStatus->unmarkPacking($user);

        $this->assertFalse($orderStatus->fresh()->packing);
        $this->assertNull($orderStatus->fresh()->packing_marked_by);
        $this->assertNull($orderStatus->fresh()->packing_marked_at);
    }

    #[Test]
    public function it_can_unmark_picked_up()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => true,
            'picked_up_marked_by' => $user->id,
            'picked_up_marked_at' => now(),
        ]);

        $orderStatus->unmarkPickedUp($user);

        $this->assertFalse($orderStatus->fresh()->picked_up);
        $this->assertNull($orderStatus->fresh()->picked_up_marked_by);
        $this->assertNull($orderStatus->fresh()->picked_up_marked_at);
    }

    #[Test]
    public function it_checks_if_all_statuses_are_completed()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $incompleteStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $completeStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        $this->assertFalse($incompleteStatus->isCompleted());
        $this->assertTrue($completeStatus->isCompleted());
    }

    #[Test]
    public function it_calculates_completion_percentage()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $noProgressStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $partialProgressStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $completeStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        $this->assertEquals(0, $noProgressStatus->getCompletionPercentage());
        $this->assertEquals(33, $partialProgressStatus->getCompletionPercentage());
        $this->assertEquals(100, $completeStatus->getCompletionPercentage());
    }

    #[Test]
    public function it_gets_next_status_to_complete()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $noProgressStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $partialProgressStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $almostCompleteStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);

        $completeStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        $this->assertEquals('picking', $noProgressStatus->getNextStatus());
        $this->assertEquals('packing', $partialProgressStatus->getNextStatus());
        $this->assertEquals('picked_up', $almostCompleteStatus->getNextStatus());
        $this->assertNull($completeStatus->getNextStatus());
    }

    #[Test]
    public function it_can_scope_by_picking_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $pickingStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $nonPickingStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $pickingOrders = OrderStatus::picking()->get();
        $nonPickingOrders = OrderStatus::picking(false)->get();

        $this->assertCount(1, $pickingOrders);
        $this->assertCount(1, $nonPickingOrders);
        $this->assertEquals($pickingStatus->id, $pickingOrders->first()->id);
        $this->assertEquals($nonPickingStatus->id, $nonPickingOrders->first()->id);
    }

    #[Test]
    public function it_can_scope_by_packing_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $packingStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => true,
            'picked_up' => false,
        ]);

        $nonPackingStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $packingOrders = OrderStatus::packing()->get();
        $nonPackingOrders = OrderStatus::packing(false)->get();

        $this->assertCount(1, $packingOrders);
        $this->assertCount(1, $nonPackingOrders);
        $this->assertEquals($packingStatus->id, $packingOrders->first()->id);
        $this->assertEquals($nonPackingStatus->id, $nonPackingOrders->first()->id);
    }

    #[Test]
    public function it_can_scope_by_picked_up_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $pickedUpStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => true,
        ]);

        $nonPickedUpStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $pickedUpOrders = OrderStatus::pickedUp()->get();
        $nonPickedUpOrders = OrderStatus::pickedUp(false)->get();

        $this->assertCount(1, $pickedUpOrders);
        $this->assertCount(1, $nonPickedUpOrders);
        $this->assertEquals($pickedUpStatus->id, $pickedUpOrders->first()->id);
        $this->assertEquals($nonPickedUpStatus->id, $nonPickedUpOrders->first()->id);
    }

    #[Test]
    public function it_can_scope_by_completed_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $completedStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        $incompleteStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $completedOrders = OrderStatus::completed()->get();

        $this->assertCount(1, $completedOrders);
        $this->assertEquals($completedStatus->id, $completedOrders->first()->id);
    }

    #[Test]
    public function it_can_scope_by_pending_status()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $pendingStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        $completedStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);

        $pendingOrders = OrderStatus::pending()->get();

        $this->assertCount(1, $pendingOrders);
        $this->assertEquals($pendingStatus->id, $pendingOrders->first()->id);
    }

    #[Test]
    public function it_records_history_when_marking_status()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $orderStatus->markPicking($user);

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('status_type', 'picking')
            ->where('status_value', true)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('picking', $history->status_type);
        $this->assertTrue($history->status_value);
        $this->assertEquals($user->id, $history->user_id);
    }

    #[Test]
    public function it_records_history_when_unmarking_status()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
            'picking_marked_by' => $user->id,
            'picking_marked_at' => now(),
        ]);

        $orderStatus->unmarkPicking($user);

        $history = OrderStatusHistory::where('order_id', $order->id)
            ->where('status_type', 'picking')
            ->where('status_value', false)
            ->where('user_id', $user->id)
            ->first();

        $this->assertNotNull($history);
        $this->assertEquals('picking', $history->status_type);
        $this->assertFalse($history->status_value);
        $this->assertEquals($user->id, $history->user_id);
    }

    #[Test]
    public function it_casts_boolean_fields_correctly()
    {
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => 1, // Will be cast to boolean
            'packing' => 0, // Will be cast to boolean
            'picked_up' => true,
        ]);

        $this->assertIsBool($orderStatus->picking);
        $this->assertIsBool($orderStatus->packing);
        $this->assertIsBool($orderStatus->picked_up);
        $this->assertTrue($orderStatus->picking);
        $this->assertFalse($orderStatus->packing);
        $this->assertTrue($orderStatus->picked_up);
    }

    #[Test]
    public function it_casts_datetime_fields_correctly()
    {
        $user = User::factory()->create(['role' => 'admin']);
        $order = Order::create([
            'system' => 'collmepro',
            'order_number' => 'WC001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos',
            'order_date' => now(),
        ]);

        $orderStatus = OrderStatus::create([
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        $orderStatus->markPicking($user);

        $this->assertInstanceOf(\DateTime::class, $orderStatus->fresh()->picking_marked_at);
        $this->assertInstanceOf(\DateTime::class, $orderStatus->fresh()->created_at);
        $this->assertInstanceOf(\DateTime::class, $orderStatus->fresh()->updated_at);
    }
}
