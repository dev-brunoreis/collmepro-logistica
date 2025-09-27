<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test orders with statuses
        $this->createTestOrders();
    }

    private function createTestOrders(): void
    {
        $orders = [
            [
                'system' => 'collmepro',
                'order_number' => 'WC001',
                'customer_name' => 'João Silva',
                'seller_name' => 'Maria Santos',
                'order_date' => now()->subDays(1),
            ],
            [
                'system' => 'collmepro_wholesale',
                'order_number' => 'WH001',
                'customer_name' => 'Ana Costa',
                'seller_name' => 'Pedro Lima',
                'order_date' => now()->subDays(2),
            ],
            [
                'system' => 'collme_beauty',
                'order_number' => 'SB001',
                'customer_name' => 'Carlos Oliveira',
                'seller_name' => 'N/A',
                'order_date' => now()->subDays(3),
            ],
        ];

        foreach ($orders as $orderData) {
            $order = Order::create($orderData);
            OrderStatus::create([
                'order_id' => $order->id,
                'picking' => false,
                'packing' => false,
                'picked_up' => false,
            ]);
        }
    }

    public function test_admin_can_view_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/orders');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 3)
            ->has('systems')
            ->has('tracking_statuses')
            ->has('logistics_providers')
        );
    }

    public function test_manager_can_view_orders(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        
        $response = $this->actingAs($manager)->get('/orders');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 3)
        );
    }

    public function test_sales_cannot_view_orders(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        
        $response = $this->actingAs($sales)->get('/orders');
        
        $response->assertStatus(403);
    }

    public function test_guest_cannot_view_orders(): void
    {
        $response = $this->get('/orders');
        
        $response->assertRedirect('/login');
    }

    public function test_can_search_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/orders?search=João');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 1)
            ->where('filters.search', 'João')
        );
    }

    public function test_can_filter_by_system(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/orders?system=collmepro');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 1)
            ->where('filters.system', 'collmepro')
        );
    }

    public function test_can_view_specific_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::first();
        
        $response = $this->actingAs($admin)->get("/orders/{$order->id}");
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Show')
            ->has('order')
            ->where('order.id', $order->id)
        );
    }

    public function test_admin_can_update_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::first();
        
        $response = $this->actingAs($admin)->patch("/orders/{$order->id}", [
            'action' => 'picking',
            'status' => true,
        ]);
        
        $response->assertRedirect();
        
        $order->refresh();
        $this->assertTrue($order->orderStatus->picking);
        $this->assertEquals($admin->id, $order->orderStatus->picking_marked_by);
    }

    public function test_manager_can_update_order_status(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $order = Order::first();
        
        $response = $this->actingAs($manager)->patch("/orders/{$order->id}", [
            'action' => 'packing',
            'status' => true,
        ]);
        
        $response->assertRedirect();
        
        $order->refresh();
        $this->assertTrue($order->orderStatus->packing);
        $this->assertEquals($manager->id, $order->orderStatus->packing_marked_by);
    }

    public function test_sales_cannot_update_order_status(): void
    {
        $sales = User::factory()->create(['role' => 'sales']);
        $order = Order::first();
        
        $response = $this->actingAs($sales)->patch("/orders/{$order->id}", [
            'action' => 'picking',
            'status' => true,
        ]);
        
        $response->assertStatus(403);
    }

    public function test_cannot_update_order_status_without_order_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = Order::factory()->create();
        // Don't create OrderStatus for this order
        
        $response = $this->actingAs($admin)->patch("/orders/{$order->id}", [
            'action' => 'picking',
            'status' => true,
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('error', 'Status do pedido não encontrado.');
    }
}
