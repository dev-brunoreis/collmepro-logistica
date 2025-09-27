<?php

namespace Tests\Unit\Services;

use App\Services\ShopifyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShopifyServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShopifyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ShopifyService();
    }

    #[Test]
    public function it_can_fetch_orders_from_collme_beauty()
    {
        // Mock response from Shopify API
        $mockResponse = [
            'orders' => [
                [
                    'id' => 1001,
                    'name' => '#1001',
                    'created_at' => '2025-09-27T15:34:34Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Maria',
                        'last_name' => 'Silva Santos',
                        'email' => 'maria@example.com'
                    ],
                    'note_attributes' => [
                        ['name' => 'seller', 'value' => 'Ana Costa']
                    ]
                ]
            ]
        ];

        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        $this->assertCount(1, $orders);
        $this->assertEquals('#1001', $orders[0]['number']);
        $this->assertEquals('Maria Silva Santos', $orders[0]['customer_name']);
        $this->assertEquals('Ana Costa', $orders[0]['seller_name']);
    }

    #[Test]
    public function it_filters_only_paid_orders()
    {
        $mockResponse = [
            'orders' => [
                [
                    'id' => 1001,
                    'name' => '#1001',
                    'created_at' => '2025-09-27T15:34:34Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Maria',
                        'last_name' => 'Silva',
                        'email' => 'maria@example.com'
                    ]
                ],
                [
                    'id' => 1002,
                    'name' => '#1002',
                    'created_at' => '2025-09-27T16:00:00Z',
                    'financial_status' => 'pending',
                    'customer' => [
                        'first_name' => 'João',
                        'last_name' => 'Santos',
                        'email' => 'joao@example.com'
                    ]
                ],
                [
                    'id' => 1003,
                    'name' => '#1003',
                    'created_at' => '2025-09-27T17:00:00Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Ana',
                        'last_name' => 'Costa',
                        'email' => 'ana@example.com'
                    ]
                ]
            ]
        ];

        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        // Should return only paid orders
        $this->assertCount(2, $orders);
        $this->assertEquals('#1001', $orders[0]['number']);
        $this->assertEquals('#1003', $orders[1]['number']);
    }

    #[Test]
    public function it_validates_required_order_data()
    {
        $mockResponse = [
            'orders' => [
                [
                    'id' => 1001,
                    'name' => '#1001',
                    'created_at' => '2025-09-27T15:34:34Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Maria',
                        'last_name' => 'Silva',
                        'email' => 'maria@example.com'
                    ]
                ],
                [
                    // Incomplete order - no customer
                    'id' => 1002,
                    'name' => '#1002',
                    'created_at' => '2025-09-27T16:00:00Z',
                    'financial_status' => 'paid'
                ],
                [
                    // Incomplete order - no name
                    'id' => 1003,
                    'created_at' => '2025-09-27T17:00:00Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Ana',
                        'last_name' => 'Costa',
                        'email' => 'ana@example.com'
                    ]
                ]
            ]
        ];

        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        // Should return only the valid order
        $this->assertCount(1, $orders);
        $this->assertEquals('#1001', $orders[0]['number']);
    }

    #[Test]
    public function it_formats_customer_name_correctly()
    {
        $mockResponse = [
            'orders' => [
                [
                    'id' => 1001,
                    'name' => '#1001',
                    'created_at' => '2025-09-27T15:34:34Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Maria',
                        'last_name' => 'Silva Santos',
                        'email' => 'maria@example.com'
                    ]
                ]
            ]
        ];

        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        $this->assertEquals('Maria Silva Santos', $orders[0]['customer_name']);
    }

    #[Test]
    public function it_handles_missing_seller_data()
    {
        $mockResponse = [
            'orders' => [
                [
                    'id' => 1001,
                    'name' => '#1001',
                    'created_at' => '2025-09-27T15:34:34Z',
                    'financial_status' => 'paid',
                    'customer' => [
                        'first_name' => 'Maria',
                        'last_name' => 'Silva',
                        'email' => 'maria@example.com'
                    ]
                    // No note_attributes with seller
                ]
            ]
        ];

        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        $this->assertCount(1, $orders);
        $this->assertEquals('N/A', $orders[0]['seller_name']);
    }

    #[Test]
    public function it_handles_api_errors_gracefully()
    {
        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response([], 500)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        $this->assertEmpty($orders);
    }

    #[Test]
    public function it_handles_network_timeout()
    {
        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response([], 408)
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        $this->assertEmpty($orders);
    }

    #[Test]
    public function it_uses_correct_api_endpoint()
    {
        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response(['orders' => []])
        ]);

        $this->service->fetchOrders('collme_beauty');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'collmebeauty.myshopify.com/admin/api/2024-01/orders.json') &&
                   $request->hasHeader('X-Shopify-Access-Token');
        });
    }

    #[Test]
    public function it_includes_authentication_headers()
    {
        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response(['orders' => []])
        ]);

        $this->service->fetchOrders('collme_beauty');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Shopify-Access-Token') &&
                   $request->hasHeader('Content-Type');
        });
    }

    #[Test]
    public function it_handles_invalid_system()
    {
        $orders = $this->service->fetchOrders('invalid_system');

        $this->assertEmpty($orders);
    }

    #[Test]
    public function it_handles_empty_response()
    {
        Http::fake([
            'collmebeauty.myshopify.com/admin/api/2024-01/orders.json*' => Http::response(['orders' => []])
        ]);

        $orders = $this->service->fetchOrders('collme_beauty');

        $this->assertEmpty($orders);
    }
}
