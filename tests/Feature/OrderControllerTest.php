<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\OrderStatus;
use App\Models\OrderSummary;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions first
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        
        // Create and authenticate a user
        $this->user = User::factory()->create(['role' => 'manager']);
        $this->actingAs($this->user);
    }

    public function test_can_view_orders_index(): void
    {
        $response = $this->get('/dashboard/orders');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('orders')
            ->has('filters')
            ->has('systems')
            ->has('situacoes')
            ->has('sellers')
        );
    }

    public function test_can_filter_orders_by_situacao(): void
    {
        // Create orders with different situacoes (using real values from DB)
        OrderSummary::factory()->create([
            'situacao_valor' => 0, // Em Aberto
            'situacao_id' => 6,
            'numero' => 'ORDER-001',
        ]);
        OrderSummary::factory()->create([
            'situacao_valor' => 1, // Em Andamento
            'situacao_id' => 9,
            'numero' => 'ORDER-002',
        ]);
        OrderSummary::factory()->create([
            'situacao_valor' => 11, // Atendido
            'situacao_id' => 24,
            'numero' => 'ORDER-003',
        ]);
        OrderSummary::factory()->create([
            'situacao_valor' => 2, // Em Processamento
            'situacao_id' => 12,
            'numero' => 'ORDER-004',
        ]);

        // Filter by situacao 0 (Em Aberto)
        $response = $this->get('/dashboard/orders?situacao=0');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('orders.data.0.situacao_valor', 0)
            ->where('filters.situacao', '0')
        );

        // Filter by situacao 12 (Cancelado - special case using situacao_id)
        $response = $this->get('/dashboard/orders?situacao=12');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('orders.data.0.situacao_id', 12)
            ->where('filters.situacao', '12')
        );
    }

    public function test_can_filter_orders_by_date_range(): void
    {
        // Create orders with different dates
        OrderSummary::factory()->create([
            'data' => '2024-01-15',
            'numero' => 'ORDER-001',
        ]);
        OrderSummary::factory()->create([
            'data' => '2024-02-15',
            'numero' => 'ORDER-002',
        ]);
        OrderSummary::factory()->create([
            'data' => '2024-03-15',
            'numero' => 'ORDER-003',
        ]);

        // Filter by date range
        $response = $this->get('/dashboard/orders?date_from=2024-02-01&date_to=2024-02-28');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.date_from', '2024-02-01')
            ->where('filters.date_to', '2024-02-28')
        );
    }

    public function test_can_filter_orders_by_seller(): void
    {
        // Create seller
        $seller = Seller::factory()->create(['name' => 'John Seller']);
        
        // Create orders
        $order1 = OrderSummary::factory()->create(['numero' => 'ORDER-001']);
        $order2 = OrderSummary::factory()->create(['numero' => 'ORDER-002']);
        
        // Attach seller to first order
        $order1->seller()->attach($seller);

        // Filter by seller
        $response = $this->get('/dashboard/orders?seller=' . $seller->id);

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.seller', (string)$seller->id)
        );
    }

    public function test_can_filter_orders_by_operational_status_picking(): void
    {
        // Create orders with different statuses
        $order1 = OrderSummary::factory()->create(['numero' => 'ORDER-001']);
        $order2 = OrderSummary::factory()->create(['numero' => 'ORDER-002']);
        
        // Create order statuses
        OrderStatus::create([
            'order_summary_id' => $order1->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);
        OrderStatus::create([
            'order_summary_id' => $order2->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
        ]);

        // Filter by picking status
        $response = $this->get('/dashboard/orders?has_picking=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.has_picking', '1')
        );
    }

    public function test_can_filter_orders_by_operational_status_packing(): void
    {
        // Create orders with different statuses
        $order1 = OrderSummary::factory()->create(['numero' => 'ORDER-001']);
        $order2 = OrderSummary::factory()->create(['numero' => 'ORDER-002']);
        
        // Create order statuses
        OrderStatus::create([
            'order_summary_id' => $order1->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);
        OrderStatus::create([
            'order_summary_id' => $order2->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);

        // Filter by packing status
        $response = $this->get('/dashboard/orders?has_packing=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.has_packing', '1')
        );
    }

    public function test_can_filter_orders_by_operational_status_picked_up(): void
    {
        // Create orders with different statuses
        $order1 = OrderSummary::factory()->create(['numero' => 'ORDER-001']);
        $order2 = OrderSummary::factory()->create(['numero' => 'ORDER-002']);
        
        // Create order statuses
        OrderStatus::create([
            'order_summary_id' => $order1->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => true,
        ]);
        OrderStatus::create([
            'order_summary_id' => $order2->id,
            'picking' => true,
            'packing' => true,
            'picked_up' => false,
        ]);

        // Filter by picked_up status
        $response = $this->get('/dashboard/orders?has_picked_up=1');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.has_picked_up', '1')
        );
    }

    public function test_can_filter_orders_with_invoice(): void
    {
        // Create orders with and without invoices
        $order1 = OrderSummary::factory()->create(['numero' => 'ORDER-001']);
        $order2 = OrderSummary::factory()->create(['numero' => 'ORDER-002']);
        
        // Create invoice for first order
        Invoice::factory()->create(['order_summary_id' => $order1->id]);

        // Filter by has_invoice
        $response = $this->get('/dashboard/orders?has_invoice=with_invoice');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.has_invoice', 'with_invoice')
        );
    }

    public function test_can_filter_orders_without_invoice(): void
    {
        // Create orders with and without invoices
        $order1 = OrderSummary::factory()->create(['numero' => 'ORDER-001']);
        $order2 = OrderSummary::factory()->create(['numero' => 'ORDER-002']);
        
        // Create invoice for first order only
        Invoice::factory()->create(['order_summary_id' => $order1->id]);

        // Filter by without_invoice
        $response = $this->get('/dashboard/orders?has_invoice=without_invoice');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.has_invoice', 'without_invoice')
        );
    }

    public function test_can_filter_orders_by_value_min(): void
    {
        // Create orders with different values
        OrderSummary::factory()->create([
            'total' => 100.00,
            'numero' => 'ORDER-001',
        ]);
        OrderSummary::factory()->create([
            'total' => 500.00,
            'numero' => 'ORDER-002',
        ]);
        OrderSummary::factory()->create([
            'total' => 1000.00,
            'numero' => 'ORDER-003',
        ]);

        // Filter by value_min
        $response = $this->get('/dashboard/orders?value_min=500');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.value_min', '500')
        );
    }

    public function test_can_filter_orders_by_value_max(): void
    {
        // Create orders with different values
        OrderSummary::factory()->create([
            'total' => 100.00,
            'numero' => 'ORDER-001',
        ]);
        OrderSummary::factory()->create([
            'total' => 500.00,
            'numero' => 'ORDER-002',
        ]);
        OrderSummary::factory()->create([
            'total' => 1000.00,
            'numero' => 'ORDER-003',
        ]);

        // Filter by value_max
        $response = $this->get('/dashboard/orders?value_max=500');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.value_max', '500')
        );
    }

    public function test_can_filter_orders_by_value_range(): void
    {
        // Create orders with different values
        OrderSummary::factory()->create([
            'total' => 100.00,
            'numero' => 'ORDER-001',
        ]);
        OrderSummary::factory()->create([
            'total' => 500.00,
            'numero' => 'ORDER-002',
        ]);
        OrderSummary::factory()->create([
            'total' => 1000.00,
            'numero' => 'ORDER-003',
        ]);

        // Filter by value range
        $response = $this->get('/dashboard/orders?value_min=200&value_max=800');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.value_min', '200')
            ->where('filters.value_max', '800')
        );
    }

    public function test_can_apply_multiple_filters_combined(): void
    {
        // Create seller
        $seller = Seller::factory()->create(['name' => 'Test Seller']);
        
        // Create order matching all criteria (using real situacao values)
        $order = OrderSummary::factory()->create([
            'situacao_valor' => 1, // Em Andamento
            'situacao_id' => 9,
            'data' => '2024-02-15',
            'total' => 500.00,
            'numero' => 'ORDER-001',
        ]);
        
        // Attach seller
        $order->seller()->attach($seller);
        
        // Create order status
        OrderStatus::create([
            'order_summary_id' => $order->id,
            'picking' => true,
            'packing' => false,
            'picked_up' => false,
        ]);
        
        // Create invoice
        Invoice::factory()->create(['order_summary_id' => $order->id]);

        // Apply multiple filters
        $response = $this->get('/dashboard/orders?' . http_build_query([
            'situacao' => 1,
            'date_from' => '2024-02-01',
            'date_to' => '2024-02-28',
            'seller' => $seller->id,
            'has_picking' => 1,
            'has_invoice' => 'with_invoice',
            'value_min' => 400,
            'value_max' => 600,
        ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->where('filters.situacao', '1')
            ->where('filters.date_from', '2024-02-01')
            ->where('filters.date_to', '2024-02-28')
            ->where('filters.seller', (string)$seller->id)
            ->where('filters.has_picking', '1')
            ->where('filters.has_invoice', 'with_invoice')
            ->where('filters.value_min', '400')
            ->where('filters.value_max', '600')
        );
    }

    public function test_filters_persist_in_query_string(): void
    {
        $response = $this->get('/dashboard/orders?situacao=1&date_from=2024-01-01');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Orders/Index')
            ->has('filters', fn ($filters) => 
                $filters->where('situacao', '1')
                    ->where('date_from', '2024-01-01')
            )
        );
    }

    public function test_invalid_value_filters_are_ignored(): void
    {
        // Create orders
        OrderSummary::factory()->count(3)->create();

        // Try with invalid values
        $response = $this->get('/dashboard/orders?value_min=-100&value_max=invalid');

        $response->assertStatus(200);
        // Should still work, just ignoring invalid values
    }

    public function test_sales_user_can_view_orders(): void
    {
        $salesUser = User::factory()->create(['role' => 'sales']);
        $this->actingAs($salesUser);

        $response = $this->get('/dashboard/orders');

        $response->assertStatus(200);
    }

    public function test_admin_user_can_view_orders(): void
    {
        $adminUser = User::factory()->create(['role' => 'admin']);
        $this->actingAs($adminUser);

        $response = $this->get('/dashboard/orders');

        $response->assertStatus(200);
    }
}

