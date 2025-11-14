<?php

namespace Tests\Feature;

use App\Models\HiddenStockProduct;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockHiddenProductsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $developer;
    protected User $manager;
    protected User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed permissions
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        
        // Create test users with different roles
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->developer = User::factory()->create(['role' => 'developer']);
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->sales = User::factory()->create(['role' => 'sales']);
    }

    /** @test */
    public function admin_can_access_get_all_products_endpoint()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('stock.products'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'product_id',
                'product_code',
                'product_name',
                'source',
            ],
        ]);
    }

    /** @test */
    public function developer_can_access_get_all_products_endpoint()
    {
        $response = $this->actingAs($this->developer)
            ->get(route('stock.products'));

        $response->assertStatus(200);
    }

    /** @test */
    public function manager_cannot_access_get_all_products_endpoint()
    {
        $response = $this->actingAs($this->manager)
            ->get(route('stock.products'));

        $response->assertStatus(403);
    }

    /** @test */
    public function sales_cannot_access_get_all_products_endpoint()
    {
        $response = $this->actingAs($this->sales)
            ->get(route('stock.products'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_get_hidden_products_endpoint()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('stock.hidden-products'));

        $response->assertStatus(200);
        $response->assertJson([]);
    }

    /** @test */
    public function developer_can_access_get_hidden_products_endpoint()
    {
        $response = $this->actingAs($this->developer)
            ->get(route('stock.hidden-products'));

        $response->assertStatus(200);
    }

    /** @test */
    public function manager_cannot_access_get_hidden_products_endpoint()
    {
        $response = $this->actingAs($this->manager)
            ->get(route('stock.hidden-products'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_save_hidden_products()
    {
        $products = [
            [
                'product_id' => 123,
                'source' => 'Pro',
                'product_name' => 'Test Product 1',
                'product_code' => 'TEST001',
            ],
            [
                'product_id' => 456,
                'source' => 'Atacadista',
                'product_name' => 'Test Product 2',
                'product_code' => 'TEST002',
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('stock.save-hidden-products'), [
                'products' => $products,
            ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'count' => 2,
        ]);

        $this->assertDatabaseCount('hidden_stock_products', 2);
        $this->assertDatabaseHas('hidden_stock_products', [
            'product_id' => 123,
            'source' => 'Pro',
        ]);
        $this->assertDatabaseHas('hidden_stock_products', [
            'product_id' => 456,
            'source' => 'Atacadista',
        ]);
    }

    /** @test */
    public function developer_can_save_hidden_products()
    {
        $products = [
            [
                'product_id' => 789,
                'source' => 'Pro',
                'product_name' => 'Test Product 3',
                'product_code' => 'TEST003',
            ],
        ];

        $response = $this->actingAs($this->developer)
            ->postJson(route('stock.save-hidden-products'), [
                'products' => $products,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('hidden_stock_products', 1);
    }

    /** @test */
    public function manager_cannot_save_hidden_products()
    {
        $response = $this->actingAs($this->manager)
            ->postJson(route('stock.save-hidden-products'), [
                'products' => [],
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function sales_cannot_save_hidden_products()
    {
        $response = $this->actingAs($this->sales)
            ->postJson(route('stock.save-hidden-products'), [
                'products' => [],
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function saving_hidden_products_replaces_existing_ones()
    {
        // Create initial hidden products
        HiddenStockProduct::create([
            'product_id' => 111,
            'source' => 'Pro',
            'product_name' => 'Old Product',
            'product_code' => 'OLD001',
        ]);

        $this->assertDatabaseCount('hidden_stock_products', 1);

        // Save new products
        $newProducts = [
            [
                'product_id' => 222,
                'source' => 'Atacadista',
                'product_name' => 'New Product',
                'product_code' => 'NEW001',
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->postJson(route('stock.save-hidden-products'), [
                'products' => $newProducts,
            ]);

        $response->assertStatus(200);

        // Old product should be gone, only new one should exist
        $this->assertDatabaseCount('hidden_stock_products', 1);
        $this->assertDatabaseMissing('hidden_stock_products', [
            'product_id' => 111,
        ]);
        $this->assertDatabaseHas('hidden_stock_products', [
            'product_id' => 222,
        ]);
    }

    /** @test */
    public function hidden_products_are_excluded_from_stock_report()
    {
        // Create a hidden product
        HiddenStockProduct::create([
            'product_id' => 999,
            'source' => 'Pro',
            'product_name' => 'Hidden Product',
            'product_code' => 'HIDDEN001',
        ]);

        // Create an order with the hidden product
        $order = OrderSummary::factory()->create([
            'source' => 'Pro',
            'raw_order_data' => [
                'itens' => [
                    [
                        'produto' => ['id' => 999],
                        'codigo' => 'HIDDEN001',
                        'descricao' => 'Hidden Product',
                        'quantidade' => 10,
                    ],
                    [
                        'produto' => ['id' => 888],
                        'codigo' => 'VISIBLE001',
                        'descricao' => 'Visible Product',
                        'quantidade' => 5,
                    ],
                ],
            ],
        ]);

        // Access stock report as admin
        $response = $this->actingAs($this->admin)
            ->get(route('stock.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => {
            $products = $page->toArray()['props']['products']['data'] ?? [];
            
            // Hidden product should not be in the results
            $hiddenProductFound = collect($products)->contains(function ($product) {
                return $product['product_id'] == 999;
            });
            
            // Visible product should be in the results
            $visibleProductFound = collect($products)->contains(function ($product) {
                return $product['product_id'] == 888;
            });
            
            return !$hiddenProductFound && $visibleProductFound;
        });
    }

    /** @test */
    public function get_hidden_products_returns_correct_structure()
    {
        HiddenStockProduct::create([
            'product_id' => 111,
            'source' => 'Pro',
            'product_name' => 'Test Product',
            'product_code' => 'TEST001',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('stock.hidden-products'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            '*' => [
                'product_id',
                'source',
                'product_name',
                'product_code',
            ],
        ]);
    }
}

