<?php

namespace Tests\Unit;

use App\Services\CollmeProBlingService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BlingCacheLayerTest extends TestCase
{
    private CollmeProBlingService $blingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blingService = app(CollmeProBlingService::class);
        
        // Clear cache before each test
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }

    /** @test */
    public function it_can_set_and_get_cached_order_hash()
    {
        $blingId = 12345;
        $hash = 'abc123def456';

        $this->blingService->setCachedOrderHash($blingId, $hash);
        $cached = $this->blingService->getCachedOrderHash($blingId);

        $this->assertEquals($hash, $cached);
    }

    /** @test */
    public function it_returns_null_for_cache_miss()
    {
        $blingId = 99999;

        $cached = $this->blingService->getCachedOrderHash($blingId);

        $this->assertNull($cached);
    }

    /** @test */
    public function it_can_invalidate_cached_order_hash()
    {
        $blingId = 12345;
        $hash = 'abc123def456';

        // Set cache
        $this->blingService->setCachedOrderHash($blingId, $hash);
        $this->assertEquals($hash, $this->blingService->getCachedOrderHash($blingId));

        // Invalidate
        $this->blingService->invalidateOrderCache($blingId);
        $cached = $this->blingService->getCachedOrderHash($blingId);

        $this->assertNull($cached, 'Cache should be null after invalidation');
    }

    /** @test */
    public function it_respects_cache_ttl()
    {
        $blingId = 12345;
        $hash = 'abc123def456';

        // Set cache with very short TTL (1 second for testing)
        $this->blingService->setCachedOrderHash($blingId, $hash, 0); // 0 minutes = expires immediately

        // Should return null immediately as TTL is 0
        sleep(1);
        $cached = $this->blingService->getCachedOrderHash($blingId);

        $this->assertNull($cached, 'Cache should expire based on TTL');
    }

    /** @test */
    public function it_can_store_multiple_order_hashes()
    {
        $orders = [
            ['bling_id' => 111, 'hash' => 'hash111'],
            ['bling_id' => 222, 'hash' => 'hash222'],
            ['bling_id' => 333, 'hash' => 'hash333'],
        ];

        // Set multiple caches
        foreach ($orders as $order) {
            $this->blingService->setCachedOrderHash($order['bling_id'], $order['hash']);
        }

        // Verify all are cached
        foreach ($orders as $order) {
            $cached = $this->blingService->getCachedOrderHash($order['bling_id']);
            $this->assertEquals($order['hash'], $cached);
        }
    }

    /** @test */
    public function it_can_update_cached_hash()
    {
        $blingId = 12345;
        $oldHash = 'old_hash_123';
        $newHash = 'new_hash_456';

        // Set initial cache
        $this->blingService->setCachedOrderHash($blingId, $oldHash);
        $this->assertEquals($oldHash, $this->blingService->getCachedOrderHash($blingId));

        // Update cache
        $this->blingService->setCachedOrderHash($blingId, $newHash);
        $cached = $this->blingService->getCachedOrderHash($blingId);

        $this->assertEquals($newHash, $cached, 'Cache should be updated with new hash');
        $this->assertNotEquals($oldHash, $cached);
    }

    /** @test */
    public function it_generates_correct_cache_key_format()
    {
        $blingId = 12345;
        $hash = 'test_hash';

        $this->blingService->setCachedOrderHash($blingId, $hash);

        // Verify cache key format directly
        $expectedKey = "bling_order_hash_{$blingId}";
        $cachedValue = Cache::get($expectedKey);

        $this->assertEquals($hash, $cachedValue);
    }

    /** @test */
    public function it_provides_cache_statistics()
    {
        // Create some test orders in database
        $orders = [
            \App\Models\OrderSummary::factory()->create(['bling_id' => 111]),
            \App\Models\OrderSummary::factory()->create(['bling_id' => 222]),
            \App\Models\OrderSummary::factory()->create(['bling_id' => 333]),
        ];

        // Cache first two
        $this->blingService->setCachedOrderHash(111, 'hash111');
        $this->blingService->setCachedOrderHash(222, 'hash222');
        // Leave 333 uncached

        $stats = $this->blingService->getCacheStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('sampled', $stats);
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        
        $this->assertGreaterThanOrEqual(0, $stats['hits']);
        $this->assertGreaterThanOrEqual(0, $stats['misses']);
        $this->assertGreaterThanOrEqual(0, $stats['hit_rate']);
        $this->assertLessThanOrEqual(100, $stats['hit_rate']);
    }
}


