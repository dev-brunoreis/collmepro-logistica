<?php

namespace App\Traits;

use App\Events\OrderCreated;
use App\Events\OrderUpdated;
use App\Models\OrderSummary;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling order broadcasting with resilience and error handling.
 * Implements circuit breaker pattern to prevent cascading failures.
 */
trait BroadcastsOrderUpdates
{
    /**
     * Circuit breaker cache key
     */
    private function getCircuitBreakerKey(): string
    {
        return 'broadcasting:circuit_breaker';
    }

    /**
     * Check if circuit breaker is open (too many failures)
     */
    private function isCircuitBreakerOpen(): bool
    {
        $failures = Cache::get($this->getCircuitBreakerKey(), 0);
        return $failures >= 5; // Open circuit after 5 consecutive failures
    }

    /**
     * Record broadcasting failure
     */
    private function recordBroadcastingFailure(): void
    {
        $key = $this->getCircuitBreakerKey();
        $failures = Cache::get($key, 0);
        Cache::put($key, $failures + 1, 300); // Store for 5 minutes
    }

    /**
     * Reset circuit breaker (after successful broadcast)
     */
    private function resetCircuitBreaker(): void
    {
        Cache::forget($this->getCircuitBreakerKey());
    }

    /**
     * Safely broadcast order update event
     * 
     * @param OrderSummary $order
     * @param array $changedFields
     * @param array $changeTypes
     * @return bool Success status
     */
    protected function safeBroadcastOrderUpdate(
        OrderSummary $order,
        array $changedFields = [],
        array $changeTypes = []
    ): bool {
        // Check if broadcasting is disabled or circuit breaker is open
        if (config('broadcasting.default') === 'null') {
            return false; // Broadcasting disabled, skip silently
        }

        if ($this->isCircuitBreakerOpen()) {
            Log::channel('bling_sync')->debug('Broadcasting circuit breaker open, skipping broadcast', [
                'order_id' => $order->id,
            ]);
            return false;
        }

        try {
            $systemUser = $this->getSystemUser();
            
            if (!$systemUser) {
                Log::channel('bling_sync')->warning('Cannot broadcast: no system user found', [
                    'order_id' => $order->id,
                ]);
                return false;
            }

            // Reload order with relationships to ensure fresh data
            $order->load(['invoice', 'orderStatus']);

            // Broadcast the update
            event(new OrderUpdated($order, $systemUser, $changedFields, $changeTypes));

            // Reset circuit breaker on success
            $this->resetCircuitBreaker();

            Log::channel('bling_sync')->debug('Order update broadcast successful', [
                'order_id' => $order->id,
                'changed_fields' => $changedFields,
            ]);

            return true;

        } catch (\Exception $e) {
            // Record failure for circuit breaker
            $this->recordBroadcastingFailure();

            // Log error but don't throw - broadcasting failure shouldn't stop sync
            Log::channel('bling_sync')->warning('Broadcasting failed (non-critical)', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Safely broadcast order created event
     * 
     * @param OrderSummary $order
     * @return bool Success status
     */
    protected function safeBroadcastOrderCreated(OrderSummary $order): bool
    {
        // Check if broadcasting is disabled or circuit breaker is open
        if (config('broadcasting.default') === 'null') {
            return false;
        }

        if ($this->isCircuitBreakerOpen()) {
            Log::channel('bling_sync')->debug('Broadcasting circuit breaker open, skipping broadcast', [
                'order_id' => $order->id,
            ]);
            return false;
        }

        try {
            $systemUser = $this->getSystemUser();
            
            if (!$systemUser) {
                Log::channel('bling_sync')->warning('Cannot broadcast: no system user found', [
                    'order_id' => $order->id,
                ]);
                return false;
            }

            // Reload order with relationships
            $order->load(['invoice', 'orderStatus']);

            // Broadcast the creation
            event(new OrderCreated($order, $systemUser));

            // Reset circuit breaker on success
            $this->resetCircuitBreaker();

            Log::channel('bling_sync')->debug('Order created broadcast successful', [
                'order_id' => $order->id,
            ]);

            return true;

        } catch (\Exception $e) {
            // Record failure for circuit breaker
            $this->recordBroadcastingFailure();

            // Log error but don't throw
            Log::channel('bling_sync')->warning('Broadcasting failed (non-critical)', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'type' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Get a system user for broadcasting
     * Prefers admin, then manager, then any user
     */
    private function getSystemUser(): ?User
    {
        static $cachedUser = null;

        if ($cachedUser !== null) {
            return $cachedUser;
        }

        // Try to get admin or manager
        $cachedUser = User::whereIn('role', ['admin', 'manager'])
            ->orderByRaw("FIELD(role, 'admin', 'manager')")
            ->first();

        // Fallback to any user
        if (!$cachedUser) {
            $cachedUser = User::first();
        }

        return $cachedUser;
    }
}

