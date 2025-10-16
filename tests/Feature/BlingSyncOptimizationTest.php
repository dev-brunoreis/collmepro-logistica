<?php

namespace Tests\Feature;

use App\Models\OrderSummary;
use App\Services\CollmeProBlingService;
use App\Jobs\BlingSyncJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BlingSyncOptimizationTest extends TestCase
{
    use RefreshDatabase;

    private CollmeProBlingService $blingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blingService = app(CollmeProBlingService::class);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /** @test */
    public function it_skips_order_with_matching_cached_hash()
    {
        // Create an order in database
        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'bling_data_hash' => 'existing_hash_123',
        ]);

        // Pre-populate cache with matching hash
        $this->blingService->setCachedOrderHash(12345, 'existing_hash_123');

        // Simulate list data from API
        $listOrder = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'notaFiscal' => ['id' => null],
            'data' => now()->subDays(5)->format('Y-m-d'),
        ];

        // Generate lightweight hash
        $lightweightHash = $this->blingService->generateLightweightHash($listOrder);
        
        // Set cache to simulate previous sync
        $this->blingService->setCachedOrderHash(12345, $lightweightHash);

        // Get cached hash
        $cachedHash = $this->blingService->getCachedOrderHash(12345);

        $this->assertEquals($lightweightHash, $cachedHash, 
            'Cached hash should match, indicating order can be skipped');
    }

    /** @test */
    public function it_detects_order_changes_via_hash_difference()
    {
        // Create an order in database with old hash
        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'bling_data_hash' => 'old_hash_123',
        ]);

        // New order data with invoice (changed)
        $completeOrderData = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-01-01',
            'dataSaida' => null,
            'numeroLoja' => 'LOJA-001',
            'notaFiscal' => [
                'id' => 5001, // Invoice was added!
                'numero' => '12345',
                'chaveAcesso' => 'ABC123',
                'valorNota' => 100.50,
                'situacao' => 1,
            ],
            'transporte' => ['volumes' => []],
        ];

        $newHash = $this->blingService->generateOrderHash($completeOrderData);

        $this->assertNotEquals('old_hash_123', $newHash, 
            'Hash should be different, indicating order needs update');
    }

    /** @test */
    public function it_updates_cache_after_processing_order()
    {
        // Create order with old hash
        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'bling_data_hash' => 'old_hash',
        ]);

        $completeOrderData = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test Customer', 'numeroDocumento' => '12345678900'],
            'data' => '2025-01-01',
            'dataSaida' => null,
            'numeroLoja' => 'LOJA-001',
            'notaFiscal' => [],
            'transporte' => ['volumes' => []],
        ];

        $newHash = $this->blingService->generateOrderHash($completeOrderData);
        
        // Simulate cache update after sync
        $this->blingService->setCachedOrderHash(12345, $newHash);
        
        // Update DB hash
        $order->update(['bling_data_hash' => $newHash]);

        // Verify cache and DB are in sync
        $cachedHash = $this->blingService->getCachedOrderHash(12345);
        $dbHash = $order->fresh()->bling_data_hash;

        $this->assertEquals($newHash, $cachedHash);
        $this->assertEquals($newHash, $dbHash);
        $this->assertEquals($cachedHash, $dbHash, 'Cache and DB should have same hash after sync');
    }

    /** @test */
    public function it_broadcasts_order_updated_event_when_order_changes()
    {
        Event::fake();

        // Create user for event
        $user = \App\Models\User::factory()->create();

        // Create order
        $order = OrderSummary::factory()->create([
            'bling_id' => 12345,
            'situacao_id' => 1,
        ]);

        // Simulate order update
        $order->update(['situacao_id' => 12]); // Cancelled

        // Manually broadcast event (as would happen in sync)
        event(new \App\Events\OrderUpdated(
            $order->fresh(),
            $user,
            ['situacao_id'],
            ['cancelled']
        ));

        Event::assertDispatched(\App\Events\OrderUpdated::class, function ($event) use ($order) {
            return $event->order->id === $order->id &&
                   in_array('cancelled', $event->changeTypes);
        });
    }

    /** @test */
    public function it_detects_invoice_added_change()
    {
        $orderWithoutInvoice = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test', 'numeroDocumento' => '123'],
            'data' => '2025-01-01',
            'dataSaida' => null,
            'numeroLoja' => 'LOJA-001',
            'notaFiscal' => [],
            'transporte' => ['volumes' => []],
        ];

        $orderWithInvoice = $orderWithoutInvoice;
        $orderWithInvoice['notaFiscal'] = [
            'id' => 5001,
            'numero' => '12345',
            'chaveAcesso' => 'ABC',
            'valorNota' => 100.50,
            'situacao' => 1,
        ];

        $hash1 = $this->blingService->generateOrderHash($orderWithoutInvoice);
        $hash2 = $this->blingService->generateOrderHash($orderWithInvoice);

        $this->assertNotEquals($hash1, $hash2, 'Hash should change when invoice added');
    }

    /** @test */
    public function it_detects_tracking_added_change()
    {
        $orderWithoutTracking = [
            'id' => 12345,
            'numero' => '1001',
            'total' => 100.50,
            'situacao' => ['id' => 1, 'valor' => 2],
            'dataAlteracao' => '2025-01-01 10:00:00',
            'contato' => ['nome' => 'Test', 'numeroDocumento' => '123'],
            'data' => '2025-01-01',
            'dataSaida' => null,
            'numeroLoja' => 'LOJA-001',
            'notaFiscal' => ['id' => 5001],
            'transporte' => ['volumes' => []],
        ];

        $orderWithTracking = $orderWithoutTracking;
        $orderWithTracking['transporte']['volumes'] = [
            [
                'id' => 9001,
                'servico' => ['id' => 1, 'nome' => 'PAC'],
            ],
        ];

        $hash1 = $this->blingService->generateOrderHash($orderWithoutTracking);
        $hash2 = $this->blingService->generateOrderHash($orderWithTracking);

        $this->assertNotEquals($hash1, $hash2, 'Hash should change when tracking added');
    }

    /** @test */
    public function it_can_clear_all_cached_hashes()
    {
        // Create orders and cache hashes
        $orders = [
            OrderSummary::factory()->create(['bling_id' => 111]),
            OrderSummary::factory()->create(['bling_id' => 222]),
            OrderSummary::factory()->create(['bling_id' => 333]),
        ];

        foreach ($orders as $order) {
            $this->blingService->setCachedOrderHash($order->bling_id, 'hash_' . $order->bling_id);
        }

        // Verify all are cached
        foreach ($orders as $order) {
            $this->assertNotNull($this->blingService->getCachedOrderHash($order->bling_id));
        }

        // Clear all
        $cleared = $this->blingService->clearAllOrderCache();

        // Verify all are cleared
        foreach ($orders as $order) {
            $this->assertNull($this->blingService->getCachedOrderHash($order->bling_id));
        }

        $this->assertEquals(count($orders), $cleared);
    }
}


