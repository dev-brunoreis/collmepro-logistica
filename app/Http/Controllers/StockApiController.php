<?php

namespace App\Http\Controllers;

use App\Services\CollmeProBlingService;
use App\Services\CollmeAtacadistaBlingService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class StockApiController extends Controller
{
    /**
     * Return current stock for one or many product codes, per source.
     * Query params:
     * - source: Pro|Atacadista
     * - codes[]: array of product_code (SKU) or comma-separated in `codes`
     * - refresh: optional bool to bypass cache
     */
    public function current(Request $request, CollmeProBlingService $pro, CollmeAtacadistaBlingService $atacadista)
    {
        $validated = $request->validate([
            'source' => 'required|in:Pro,Atacadista',
            'codes' => 'sometimes|array|min:1',
            'codes.*' => 'string|min:1|max:255',
            'product_code' => 'sometimes|string|min:1|max:255',
            'refresh' => 'sometimes|boolean',
        ]);

        // Normalize codes input to array
        $codes = [];
        if ($request->has('codes')) {
            $codes = array_values(array_filter((array) $request->input('codes')));
        } elseif ($request->filled('product_code')) {
            $codes = [$request->string('product_code')->toString()];
        } elseif ($request->filled('codes')) {
            // Support comma-separated string fallback
            $codes = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('codes')))));
        }

        if (empty($codes)) {
            throw ValidationException::withMessages([
                'codes' => ['At least one product code is required.'],
            ]);
        }

        $source = $validated['source'];
        $service = $source === 'Pro' ? $pro : $atacadista;

        $cacheTtl = (int) config('bling.defaults.stock_ttl', 1800); // seconds
        $bypassCache = (bool) ($validated['refresh'] ?? false);

        // Throttle bursts to protect upstream even if frontend misbehaves
        $rateKey = 'stock_api:' . $source;
        if (!RateLimiter::attempt($rateKey, $perMinute = 30, function () {})) {
            return response()->json(['message' => 'Too Many Requests'], 429);
        }

        $result = [];

        foreach ($codes as $code) {
            $cacheKey = "stock:{$source}:{$code}";
            if ($bypassCache) {
                Cache::forget($cacheKey);
            }

            $value = Cache::remember($cacheKey, $cacheTtl, function () use ($service, $code) {
                $stock = $service->getProductStockByCodes([$code]);
                return isset($stock[$code]) ? (float) $stock[$code] : 0.0;
            });

            $result[$code] = [
                'current_stock' => is_numeric($value) ? (float) $value : 0.0,
                'cached_at' => now()->toIso8601String(),
                'ttl_seconds' => $cacheTtl,
            ];
        }

        return response()->json(['data' => $result]);
    }

    /**
     * Get stock for multiple products by their Bling IDs
     * Query params:
     * - source: Pro|Atacadista (required)
     * - ids[]: array of Bling product IDs (required)
     */
    public function getStockByIds(Request $request, CollmeProBlingService $pro, CollmeAtacadistaBlingService $atacadista)
    {
        $validated = $request->validate([
            'source' => 'required|in:Pro,Atacadista',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|min:1',
        ]);

        $source = $validated['source'];
        $ids = $validated['ids'];
        $service = $source === 'Pro' ? $pro : $atacadista;

        // Throttle to protect upstream
        $rateKey = 'stock_ids_api:' . $source;
        if (!RateLimiter::attempt($rateKey, $perMinute = 30, function () {})) {
            return response()->json(['message' => 'Too Many Requests'], 429);
        }

        $cacheTtl = (int) config('bling.defaults.stock_ttl', 1800); // 30 minutes
        $cacheKey = "stock_by_ids:{$source}:" . md5(implode(',', $ids));

        $stocks = Cache::remember($cacheKey, $cacheTtl, function () use ($service, $ids) {
            return $service->getProductStockByIds($ids);
        });

        return response()->json([
            'data' => $stocks,
            'cached_at' => now()->toIso8601String(),
            'ttl_seconds' => $cacheTtl,
        ]);
    }
}


