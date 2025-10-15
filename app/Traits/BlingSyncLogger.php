<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait BlingSyncLogger
{
    /**
     * Log info message to bling_sync channel
     */
    protected function logBlingInfo(string $message, array $context = []): void
    {
        Log::channel('bling_sync')->info($message, $this->sanitizeContext($context));
    }

    /**
     * Log error message to bling_sync channel
     */
    protected function logBlingError(string $message, array $context = []): void
    {
        Log::channel('bling_sync')->error($message, $this->sanitizeContext($context));
    }

    /**
     * Log warning message to bling_sync channel
     */
    protected function logBlingWarning(string $message, array $context = []): void
    {
        Log::channel('bling_sync')->warning($message, $this->sanitizeContext($context));
    }

    /**
     * Log debug message to bling_sync channel (only in debug mode)
     */
    protected function logBlingDebug(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            Log::channel('bling_sync')->debug($message, $this->sanitizeContext($context));
        }
    }

    /**
     * Sanitize context to remove heavy data
     */
    private function sanitizeContext(array $context): array
    {
        // Remove trace strings (muito pesado)
        if (isset($context['trace'])) {
            unset($context['trace']);
        }

        // Remove raw_data completo (pesado)
        if (isset($context['raw_data'])) {
            unset($context['raw_data']);
        }

        // Remove raw_order_data completo (pesado)
        if (isset($context['raw_order_data'])) {
            unset($context['raw_order_data']);
        }

        // Limitar body de resposta de API
        if (isset($context['body']) && is_string($context['body']) && strlen($context['body']) > 500) {
            $context['body'] = substr($context['body'], 0, 500) . '... [truncated]';
        }

        // Limitar hash logs (manter apenas primeiros 8 caracteres)
        if (isset($context['hash']) && is_string($context['hash']) && strlen($context['hash']) > 8) {
            $context['hash'] = substr($context['hash'], 0, 8);
        }

        if (isset($context['old_hash']) && is_string($context['old_hash']) && strlen($context['old_hash']) > 8) {
            $context['old_hash'] = substr($context['old_hash'], 0, 8);
        }

        if (isset($context['new_hash']) && is_string($context['new_hash']) && strlen($context['new_hash']) > 8) {
            $context['new_hash'] = substr($context['new_hash'], 0, 8);
        }

        if (isset($context['cached_hash']) && is_string($context['cached_hash']) && strlen($context['cached_hash']) > 8) {
            $context['cached_hash'] = substr($context['cached_hash'], 0, 8);
        }

        if (isset($context['lightweight_hash']) && is_string($context['lightweight_hash']) && strlen($context['lightweight_hash']) > 8) {
            $context['lightweight_hash'] = substr($context['lightweight_hash'], 0, 8);
        }

        if (isset($context['db_hash']) && is_string($context['db_hash']) && strlen($context['db_hash']) > 8) {
            $context['db_hash'] = substr($context['db_hash'], 0, 8);
        }

        if (isset($context['comprehensiveHash']) && is_string($context['comprehensiveHash']) && strlen($context['comprehensiveHash']) > 8) {
            $context['comprehensiveHash'] = substr($context['comprehensiveHash'], 0, 8);
        }

        // Simplificar changes array (remover valores vazios)
        if (isset($context['changes']) && is_array($context['changes'])) {
            $context['changes'] = array_filter($context['changes'], function ($value) {
                if (is_array($value)) {
                    // Só manter se os valores mudaram realmente
                    return count(array_unique($value)) > 1;
                }
                return true;
            });
        }

        return $context;
    }

    /**
     * Log sync summary (simplified)
     */
    protected function logSyncSummary(string $account, array $stats): void
    {
        $this->logBlingInfo("Sync completed: {$account}", [
            'account' => $account,
            'processed' => $stats['processed'] ?? 0,
            'created' => $stats['created'] ?? 0,
            'updated' => $stats['updated'] ?? 0,
            'skipped' => $stats['skipped'] ?? 0,
            'errors' => $stats['errors'] ?? 0,
            'duration_seconds' => $stats['duration_seconds'] ?? 0,
        ]);
    }

    /**
     * Log order action (simplified)
     */
    protected function logOrderAction(string $action, int $blingId, string $numero, array $extra = []): void
    {
        $context = array_merge([
            'action' => $action,
            'bling_id' => $blingId,
            'numero' => $numero,
        ], $extra);

        $this->logBlingInfo("Order {$action}: #{$numero}", $this->sanitizeContext($context));
    }
}

