<?php

namespace Tests\Feature;

use App\Models\OrderSummary;
use App\Models\OrderData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        
        // Create test user with manager role (which should have reports permission)
        $this->user = User::factory()->create([
            'role' => 'manager',
        ]);
    }

    /** @test */
    public function authenticated_users_with_permission_can_access_sales_report()
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Reports/Sales')
        );
    }

    /** @test */
    public function users_without_permission_cannot_access_sales_report()
    {
        $userWithoutPermission = User::factory()->create([
            'role' => 'sales',
        ]);

        $response = $this->actingAs($userWithoutPermission)
            ->get(route('reports.sales'));

        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_users_cannot_access_sales_report()
    {
        $response = $this->get(route('reports.sales'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function sales_report_filters_by_source_pro()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['source' => 'Pro']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products.data')
                ->where('filters.source', 'Pro')
        );
    }

    /** @test */
    public function sales_report_filters_by_source_atacadista()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['source' => 'Atacadista']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products.data')
                ->where('filters.source', 'Atacadista')
        );
    }

    /** @test */
    public function sales_report_filters_by_date_range()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', [
                'start_month' => '2025-10',
                'end_month' => '2025-10'
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('filters.start_month', '2025-10')
                ->where('filters.end_month', '2025-10')
        );
    }

    /** @test */
    public function sales_report_respects_pagination_parameters()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['per_page' => 10]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->where('filters.per_page', 10)
        );
    }

    /** @test */
    public function sales_report_aggregates_products_correctly()
    {
        // Create test orders with specific product data
        $this->createTestOrdersWithProducts();

        $response = $this->actingAs($this->user)
            ->get(route('reports.sales'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products.data')
                ->has('months')
        );

        // Verify that products are aggregated correctly
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertNotEmpty($products);
        
        // Check that each product has the expected structure
        foreach ($products as $product) {
            $this->assertArrayHasKey('product_id', $product);
            $this->assertArrayHasKey('product_code', $product);
            $this->assertArrayHasKey('product_name', $product);
            $this->assertArrayHasKey('current_stock', $product);
            $this->assertArrayHasKey('sales_by_month', $product);
        }
    }

    /** @test */
    public function sales_report_returns_correct_month_columns()
    {
        // Create test data
        $this->createTestOrders();

        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', [
                'start_month' => '2025-09',
                'end_month' => '2025-10'
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('months')
                ->where('months', ['2025-10', '2025-09'])
        );
    }

    /** @test */
    public function sales_report_handles_empty_results()
    {
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', [
                'start_month' => '2020-01',
                'end_month' => '2020-01'
            ]));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products.data')
                ->where('products.data', [])
        );
    }

    /** @test */
    public function sales_report_filters_by_product_name_search()
    {
        // Create test data with specific product names
        $this->createTestOrdersWithSearchableProducts();

        // Search for a specific product
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['search' => 'Produto Específico']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products.data')
                ->where('filters.search', 'Produto Específico')
        );

        // Verify that only matching products are returned
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertNotEmpty($products);
        
        foreach ($products as $product) {
            $this->assertStringContainsString('Produto Específico', $product['product_name']);
        }
    }

    /** @test */
    public function sales_report_handles_search_with_no_results()
    {
        // Create test data
        $this->createTestOrders();

        // Search for a non-existent product
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['search' => 'Produto Inexistente']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->has('products.data')
                ->where('filters.search', 'Produto Inexistente')
        );

        // Verify that no products are returned
        $products = $response->viewData('page')['props']['products']['data'];
        $this->assertEmpty($products);
    }

    /** @test */
    public function sales_report_validates_input_parameters()
    {
        // Test invalid source
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['source' => 'Invalid']));

        $response->assertStatus(302); // Validation error redirect

        // Test invalid date format
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['start_month' => 'invalid-date']));

        $response->assertStatus(302); // Validation error redirect

        // Test invalid per_page
        $response = $this->actingAs($this->user)
            ->get(route('reports.sales', ['per_page' => 1000]));

        $response->assertStatus(302); // Validation error redirect
    }

    /**
     * Create test orders with different sources
     */
    private function createTestOrders()
    {
        // Create Pro orders
        $proOrder = OrderSummary::factory()->create([
            'source' => 'Pro',
            'data' => '2025-10-15',
        ]);

        OrderData::factory()->create([
            'order_summary_id' => $proOrder->id,
            'itens' => [
                [
                    'produto' => ['id' => 1, 'codigo' => 'PROD-001'],
                    'codigo' => 'PROD-001',
                    'descricao' => 'Produto Pro 1',
                    'quantidade' => 5
                ]
            ]
        ]);

        // Create Atacadista orders
        $atacadistaOrder = OrderSummary::factory()->create([
            'source' => 'Atacadista',
            'data' => '2025-10-20',
        ]);

        OrderData::factory()->create([
            'order_summary_id' => $atacadistaOrder->id,
            'itens' => [
                [
                    'produto' => ['id' => 2, 'codigo' => 'PROD-002'],
                    'codigo' => 'PROD-002',
                    'descricao' => 'Produto Atacadista 1',
                    'quantidade' => 10
                ]
            ]
        ]);
    }

    /**
     * Create test orders with specific product data for aggregation testing
     */
    private function createTestOrdersWithProducts()
    {
        // Create multiple orders with the same product to test aggregation
        $productId = 12345;
        $productCode = 'TEST-PROD';
        $productName = 'Test Product';

        for ($i = 0; $i < 3; $i++) {
            OrderSummary::factory()->create([
                'source' => 'Pro',
                'data' => '2025-10-' . (15 + $i),
                'raw_order_data' => [
                    'id' => 1000 + $i,
                    'numero' => 'ORDER-' . (1000 + $i),
                    'itens' => [
                        [
                            'produto' => ['id' => $productId, 'codigo' => $productCode],
                            'codigo' => $productCode,
                            'descricao' => $productName,
                            'quantidade' => 2
                        ]
                    ]
                ]
            ]);
        }
    }

    /**
     * Create test orders with searchable product names
     */
    private function createTestOrdersWithSearchableProducts()
    {
        // Create order with specific product name for search testing
        OrderSummary::factory()->create([
            'source' => 'Pro',
            'data' => '2025-10-15',
            'raw_order_data' => [
                'id' => 2000,
                'numero' => 'ORDER-2000',
                'itens' => [
                    [
                        'produto' => ['id' => 2001, 'codigo' => 'PROD-2001'],
                        'codigo' => 'PROD-2001',
                        'descricao' => 'Produto Específico para Teste',
                        'quantidade' => 3
                    ],
                    [
                        'produto' => ['id' => 2002, 'codigo' => 'PROD-2002'],
                        'codigo' => 'PROD-2002',
                        'descricao' => 'Outro Produto Diferente',
                        'quantidade' => 1
                    ]
                ]
            ]
        ]);

        // Create another order with different product
        OrderSummary::factory()->create([
            'source' => 'Atacadista',
            'data' => '2025-10-20',
            'raw_order_data' => [
                'id' => 2001,
                'numero' => 'ORDER-2001',
                'itens' => [
                    [
                        'produto' => ['id' => 2003, 'codigo' => 'PROD-2003'],
                        'codigo' => 'PROD-2003',
                        'descricao' => 'Produto Específico Atacadista',
                        'quantidade' => 5
                    ]
                ]
            ]
        ]);
    }
}
