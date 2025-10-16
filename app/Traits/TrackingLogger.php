<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait TrackingLogger
{
    /**
     * Log info message to tracking channel
     */
    protected function logTrackingInfo(string $message, array $context = []): void
    {
        Log::channel('tracking')->info($message, $this->sanitizeContext($context));
    }

    /**
     * Log error message to tracking channel
     */
    protected function logTrackingError(string $message, array $context = []): void
    {
        Log::channel('tracking')->error($message, $this->sanitizeContext($context));
    }

    /**
     * Log warning message to tracking channel
     */
    protected function logTrackingWarning(string $message, array $context = []): void
    {
        Log::channel('tracking')->warning($message, $this->sanitizeContext($context));
    }

    /**
     * Log debug message to tracking channel (only in debug mode)
     */
    protected function logTrackingDebug(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            Log::channel('tracking')->debug($message, $this->sanitizeContext($context));
        }
    }

    /**
     * Sanitize context to remove heavy data
     */
    private function sanitizeContext(array $context): array
    {
        // Remove trace strings
        if (isset($context['trace'])) {
            unset($context['trace']);
        }

        // Remove raw_data completo
        if (isset($context['raw_data'])) {
            unset($context['raw_data']);
        }

        // Remove raw_order_data completo
        if (isset($context['raw_order_data'])) {
            unset($context['raw_order_data']);
        }

        // Limitar body de resposta de API
        if (isset($context['body']) && is_string($context['body']) && strlen($context['body']) > 500) {
            $context['body'] = substr($context['body'], 0, 500) . '... [truncated]';
        }

        // Simplificar changes array
        if (isset($context['changes']) && is_array($context['changes'])) {
            $context['changes'] = array_filter($context['changes'], function ($value) {
                if (is_array($value)) {
                    return count(array_unique($value)) > 1;
                }
                return true;
            });
        }

        return $context;
    }
}

