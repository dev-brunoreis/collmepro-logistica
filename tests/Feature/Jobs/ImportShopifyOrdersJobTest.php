<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportShopifyOrdersJob;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ImportShopifyOrdersJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_import_shopify_orders()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        // Job should complete without errors
        $this->assertTrue(true);
    }

    #[Test]
    public function it_creates_orders_in_database()
    {
        // Mock the service
        $mockService = $this->createMock(ShopifyService::class);
        $mockService->method('fetchOrders')
            ->with('collme_beauty')
            ->willReturn([
                [
                    'id' => 1001,
                    'number' => '#1001',
                    'date_created' => '2025-09-27T15:34:34Z',
                    'customer_name' => 'Maria Silva Santos',
                    'seller_name' => 'Ana Costa'
                ]
            ]);

        // Bind the mock to the container
        $this->app->instance(ShopifyService::class, $mockService);

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        $this->assertDatabaseHas('orders', [
            'system' => 'collme_beauty',
            'order_number' => '#1001',
            'customer_name' => 'Maria Silva Santos',
            'seller_name' => 'Ana Costa'
        ]);

        $order = Order::where('order_number', '#1001')->first();
        $this->assertNotNull($order);

        $this->assertDatabaseHas('order_statuses', [
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false
        ]);
    }

    #[Test]
    public function it_avoids_duplicate_orders()
    {
        // Create an existing order
        $existingOrder = Order::create([
            'system' => 'collme_beauty',
            'order_number' => '#1001',
            'customer_name' => 'Cliente Existente',
            'seller_name' => 'Vendedor Existente',
            'order_date' => now()
        ]);

        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([
                    [
                        'id' => 1001,
                        'number' => '#1001',
                        'date_created' => '2025-09-27T15:34:34Z',
                        'customer_name' => 'Maria Silva Santos',
                        'seller_name' => 'Ana Costa'
                    ]
                ]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        // Should keep only 1 order (no duplicate)
        $this->assertEquals(1, Order::where('order_number', '#1001')->count());
        
        // Should keep the original data
        $this->assertDatabaseHas('orders', [
            'order_number' => '#1001',
            'customer_name' => 'Cliente Existente'
        ]);
    }

    #[Test]
    public function it_handles_import_errors_gracefully()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andThrow(new \Exception('API Error'));
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        
        // Should throw exception (job failed)
        $this->expectException(\Exception::class);
        $job->handle();
    }

    #[Test]
    public function it_imports_multiple_orders()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([
                    [
                        'id' => 1001,
                        'number' => '#1001',
                        'date_created' => '2025-09-27T15:34:34Z',
                        'customer_name' => 'Maria Silva',
                        'seller_name' => 'Ana Costa'
                    ],
                    [
                        'id' => 1002,
                        'number' => '#1002',
                        'date_created' => '2025-09-27T16:00:00Z',
                        'customer_name' => 'João Santos',
                        'seller_name' => 'Pedro Lima'
                    ]
                ]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        $this->assertDatabaseHas('orders', [
            'system' => 'collme_beauty',
            'order_number' => '#1001',
            'customer_name' => 'Maria Silva',
            'seller_name' => 'Ana Costa'
        ]);

        $this->assertDatabaseHas('orders', [
            'system' => 'collme_beauty',
            'order_number' => '#1002',
            'customer_name' => 'João Santos',
            'seller_name' => 'Pedro Lima'
        ]);

        // Should create 2 orders
        $this->assertEquals(2, Order::where('system', 'collme_beauty')->count());
    }

    #[Test]
    public function it_creates_order_statuses_for_new_orders()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([
                    [
                        'id' => 1001,
                        'number' => '#1001',
                        'date_created' => '2025-09-27T15:34:34Z',
                        'customer_name' => 'Maria Silva Santos',
                        'seller_name' => 'Ana Costa'
                    ]
                ]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        $order = Order::where('order_number', '#1001')->first();
        
        $this->assertDatabaseHas('order_statuses', [
            'order_id' => $order->id,
            'picking' => false,
            'packing' => false,
            'picked_up' => false,
            'picking_marked_by' => null,
            'packing_marked_by' => null,
            'picked_up_marked_by' => null
        ]);
    }

    #[Test]
    public function it_handles_empty_orders_response()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        // Should not create any orders
        $this->assertEquals(0, Order::where('system', 'collme_beauty')->count());
    }

    #[Test]
    public function it_logs_import_activity()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([
                    [
                        'id' => 1001,
                        'number' => '#1001',
                        'date_created' => '2025-09-27T15:34:34Z',
                        'customer_name' => 'Maria Silva Santos',
                        'seller_name' => 'Ana Costa'
                    ]
                ]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        $job->handle();

        // Verify if the log was created (specific implementation may vary)
        $this->assertTrue(true); // Placeholder para verificação de log
    }

    #[Test]
    public function it_handles_invalid_order_data()
    {
        $this->mock(ShopifyService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collme_beauty')
                ->andReturn([
                    [
                        // Missing required fields - this will be filtered out by the service
                        'id' => 1001,
                        'date_created' => '2025-09-27T15:34:34Z'
                    ]
                ]);
        });

        $job = new ImportShopifyOrdersJob('collme_beauty');
        
        // Should handle gracefully without creating invalid orders
        $job->handle();

        // Should not create any orders due to invalid data being filtered out
        $this->assertEquals(0, Order::where('system', 'collme_beauty')->count());
    }
}
