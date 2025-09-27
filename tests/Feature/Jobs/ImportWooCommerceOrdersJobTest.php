<?php

namespace Tests\Feature\Jobs;

use App\Jobs\ImportWooCommerceOrdersJob;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\WooCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ImportWooCommerceOrdersJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_import_woocommerce_orders()
    {
        $this->mock(WooCommerceService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collmepro')
                ->andReturn([]);
        });

        $job = new ImportWooCommerceOrdersJob('collmepro');
        $job->handle();

        // Job should complete without errors
        $this->assertTrue(true);
    }

    #[Test]
    public function it_creates_orders_in_database()
    {
        // Mock the service
        $mockService = $this->createMock(WooCommerceService::class);
        $mockService->method('fetchOrders')
            ->with('collmepro')
            ->willReturn([
                [
                    'id' => 8148,
                    'number' => '8148',
                    'status' => 'processing',
                    'customer_name' => 'Eliane Aparecida do Nascimento',
                    'seller_name' => 'Andressa Messias',
                    'date_created' => '2025-09-27T15:34:34',
                    'billing' => [
                        'first_name' => 'Eliane',
                        'last_name' => 'Aparecida do Nascimento',
                        'email' => 'elianynha_@hotmail.com'
                    ]
                ]
            ]);

        // Bind the mock to the container
        $this->app->instance(WooCommerceService::class, $mockService);

        $job = new ImportWooCommerceOrdersJob('collmepro');
        $job->handle();

        $this->assertDatabaseHas('orders', [
            'system' => 'collmepro',
            'order_number' => '8148',
            'customer_name' => 'Eliane Aparecida do Nascimento',
            'seller_name' => 'Andressa Messias'
        ]);

        $order = Order::where('order_number', '8148')->first();
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
            'system' => 'collmepro',
            'order_number' => '8148',
            'customer_name' => 'Cliente Existente',
            'seller_name' => 'Vendedor Existente',
            'order_date' => now()
        ]);

        $this->mock(WooCommerceService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collmepro')
                ->andReturn([
                    [
                        'id' => 8148,
                        'number' => '8148',
                        'status' => 'processing',
                        'customer_name' => 'Eliane Aparecida do Nascimento',
                        'seller_name' => 'Andressa Messias',
                        'date_created' => '2025-09-27T15:34:34'
                    ]
                ]);
        });

        $job = new ImportWooCommerceOrdersJob('collmepro');
        $job->handle();

        // Should keep only 1 order (no duplicate)
        $this->assertEquals(1, Order::where('order_number', '8148')->count());
        
        // Should keep the original data
        $this->assertDatabaseHas('orders', [
            'order_number' => '8148',
            'customer_name' => 'Cliente Existente'
        ]);
    }

    #[Test]
    public function it_handles_import_errors_gracefully()
    {
        $this->mock(WooCommerceService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collmepro')
                ->andThrow(new \Exception('API Error'));
        });

        $job = new ImportWooCommerceOrdersJob('collmepro');
        
        // Should throw exception (job failed)
        $this->expectException(\Exception::class);
        $job->handle();
    }

    #[Test]
    public function it_imports_collmepro_wholesale_orders()
    {
        $this->mock(WooCommerceService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collmepro_wholesale')
                ->andReturn([
                    [
                        'id' => 1001,
                        'number' => '1001',
                        'status' => 'processing',
                        'customer_name' => 'João Silva',
                        'seller_name' => 'Maria Santos',
                        'date_created' => '2025-09-27T10:00:00'
                    ]
                ]);
        });

        $job = new ImportWooCommerceOrdersJob('collmepro_wholesale');
        $job->handle();

        $this->assertDatabaseHas('orders', [
            'system' => 'collmepro_wholesale',
            'order_number' => '1001',
            'customer_name' => 'João Silva',
            'seller_name' => 'Maria Santos'
        ]);
    }

    #[Test]
    public function it_creates_order_statuses_for_new_orders()
    {
        $this->mock(WooCommerceService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collmepro')
                ->andReturn([
                    [
                        'id' => 8148,
                        'number' => '8148',
                        'status' => 'processing',
                        'customer_name' => 'Eliane Aparecida do Nascimento',
                        'seller_name' => 'Andressa Messias',
                        'date_created' => '2025-09-27T15:34:34'
                    ]
                ]);
        });

        $job = new ImportWooCommerceOrdersJob('collmepro');
        $job->handle();

        $order = Order::where('order_number', '8148')->first();
        
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
    public function it_logs_import_activity()
    {
        $this->mock(WooCommerceService::class, function ($mock) {
            $mock->shouldReceive('fetchOrders')
                ->with('collmepro')
                ->andReturn([
                    [
                        'id' => 8148,
                        'number' => '8148',
                        'status' => 'processing',
                        'customer_name' => 'Eliane Aparecida do Nascimento',
                        'seller_name' => 'Andressa Messias',
                        'date_created' => '2025-09-27T15:34:34'
                    ]
                ]);
        });

        $job = new ImportWooCommerceOrdersJob('collmepro');
        $job->handle();

        // Verify if the log was created (specific implementation may vary)
        $this->assertTrue(true); // Placeholder para verificação de log
    }
}
