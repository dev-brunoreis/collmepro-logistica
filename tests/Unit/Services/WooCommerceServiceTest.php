<?php

namespace Tests\Unit\Services;

use App\Services\WooCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class WooCommerceServiceTest extends TestCase
{
    use RefreshDatabase;

    private WooCommerceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WooCommerceService();
    }

    #[Test]
    public function it_can_fetch_orders_from_collmepro()
    {
        // Mock response from WooCommerce API
        $mockResponse = [
            [
                'id' => 8148,
                'status' => 'processing',
                'number' => '8148',
                'date_created' => '2025-09-27T15:34:34',
                'billing' => [
                    'first_name' => 'Eliane',
                    'last_name' => 'Aparecida do Nascimento',
                    'email' => 'elianynha_@hotmail.com'
                ],
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ],
                'meta_data' => [
                    ['key' => '_billing_cpf', 'value' => '07475998908']
                ]
            ]
        ];

        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        $this->assertCount(1, $orders);
        $this->assertEquals('8148', $orders[0]['number']);
        $this->assertEquals('processing', $orders[0]['status']);
        $this->assertEquals('Eliane Aparecida do Nascimento', $orders[0]['customer_name']);
        $this->assertEquals('Andressa Messias', $orders[0]['seller_name']);
    }

    #[Test]
    public function it_can_fetch_orders_from_collmepro_atacadista()
    {
        $mockResponse = [
            [
                'id' => 1001,
                'status' => 'processing',
                'number' => '1001',
                'date_created' => '2025-09-27T10:00:00',
                'billing' => [
                    'first_name' => 'João',
                    'last_name' => 'Silva',
                    'email' => 'joao@example.com'
                ],
                'seller' => [
                    'id' => '5',
                    'name' => 'Maria Santos',
                    'email' => 'maria@protocoll.com.br'
                ]
            ]
        ];

        Http::fake([
            'atacadista.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collmepro_atacadista');

        $this->assertCount(1, $orders);
        $this->assertEquals('1001', $orders[0]['number']);
        $this->assertEquals('João Silva', $orders[0]['customer_name']);
        $this->assertEquals('Maria Santos', $orders[0]['seller_name']);
    }

    #[Test]
    public function it_filters_only_processing_orders()
    {
        $mockResponse = [
            [
                'id' => 8148,
                'status' => 'processing',
                'number' => '8148',
                'date_created' => '2025-09-27T15:34:34',
                'billing' => [
                    'first_name' => 'Eliane',
                    'last_name' => 'Aparecida do Nascimento',
                    'email' => 'elianynha_@hotmail.com'
                ],
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ]
            ],
            [
                'id' => 8149,
                'status' => 'pending',
                'number' => '8149',
                'date_created' => '2025-09-27T16:00:00',
                'billing' => [
                    'first_name' => 'Pedro',
                    'last_name' => 'Santos',
                    'email' => 'pedro@example.com'
                ],
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ]
            ],
            [
                'id' => 8150,
                'status' => 'processing',
                'number' => '8150',
                'date_created' => '2025-09-27T17:00:00',
                'billing' => [
                    'first_name' => 'Ana',
                    'last_name' => 'Costa',
                    'email' => 'ana@example.com'
                ],
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ]
            ]
        ];

        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        // Should return only orders with status 'processing'
        $this->assertCount(2, $orders);
        $this->assertEquals('8148', $orders[0]['number']);
        $this->assertEquals('8150', $orders[1]['number']);
        
        // Verify that there are no 'pending' orders
        $pendingOrders = array_filter($orders, fn($order) => $order['status'] === 'pending');
        $this->assertCount(0, $pendingOrders);
    }

    #[Test]
    public function it_handles_api_errors_gracefully()
    {
        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response([], 500)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        $this->assertEmpty($orders);
    }

    #[Test]
    public function it_handles_network_timeout()
    {
        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response([], 408)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        $this->assertEmpty($orders);
    }

    #[Test]
    public function it_validates_required_order_data()
    {
        $mockResponse = [
            [
                'id' => 8148,
                'status' => 'processing',
                'number' => '8148',
                'date_created' => '2025-09-27T15:34:34',
                'billing' => [
                    'first_name' => 'Eliane',
                    'last_name' => 'Aparecida do Nascimento',
                    'email' => 'elianynha_@hotmail.com'
                ],
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ]
            ],
            [
                // Incomplete order - no billing
                'id' => 8149,
                'status' => 'processing',
                'number' => '8149',
                'date_created' => '2025-09-27T16:00:00',
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ]
            ]
        ];

        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        // Should return only the valid order
        $this->assertCount(1, $orders);
        $this->assertEquals('8148', $orders[0]['number']);
    }

    #[Test]
    public function it_formats_customer_name_correctly()
    {
        $mockResponse = [
            [
                'id' => 8148,
                'status' => 'processing',
                'number' => '8148',
                'date_created' => '2025-09-27T15:34:34',
                'billing' => [
                    'first_name' => 'Eliane',
                    'last_name' => 'Aparecida do Nascimento',
                    'email' => 'elianynha_@hotmail.com'
                ],
                'seller' => [
                    'id' => '10',
                    'name' => 'Andressa Messias',
                    'email' => 'andressa.messias@protocoll.com.br'
                ]
            ]
        ];

        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        $this->assertEquals('Eliane Aparecida do Nascimento', $orders[0]['customer_name']);
    }

    #[Test]
    public function it_handles_missing_seller_data()
    {
        $mockResponse = [
            [
                'id' => 8148,
                'status' => 'processing',
                'number' => '8148',
                'date_created' => '2025-09-27T15:34:34',
                'billing' => [
                    'first_name' => 'Eliane',
                    'last_name' => 'Aparecida do Nascimento',
                    'email' => 'elianynha_@hotmail.com'
                ]
                // No seller
            ]
        ];

        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response($mockResponse, 200)
        ]);

        $orders = $this->service->fetchOrders('collmepro');

        $this->assertCount(1, $orders);
        $this->assertNull($orders[0]['seller_name']);
    }

    #[Test]
    public function it_uses_correct_api_endpoints()
    {
        Http::fake([
            'pro.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response([]),
            'atacadista.collmepro.com.br/app/wp-json/wc/v3/orders*' => Http::response([]),
        ]);

        $this->service->fetchOrders('collmepro');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'pro.collmepro.com.br/app/wp-json/wc/v3/orders') &&
                   $request->hasHeader('Authorization');
        });

        $this->service->fetchOrders('collmepro_atacadista');
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'atacadista.collmepro.com.br/app/wp-json/wc/v3/orders') &&
                   $request->hasHeader('Authorization');
        });
    }

    #[Test]
    public function it_includes_authentication_headers()
    {
        Http::fake();

        $this->service->fetchOrders('collmepro');

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization') &&
                   str_starts_with($request->header('Authorization')[0], 'Basic');
        });
    }
}
