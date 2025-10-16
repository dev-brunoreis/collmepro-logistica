<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait InvoiceLogger
{
    /**
     * Log info message to invoices channel
     */
    protected function logInvoiceInfo(string $message, array $context = []): void
    {
        Log::channel('invoices')->info($message, $this->sanitizeContext($context));
    }

    /**
     * Log error message to invoices channel
     */
    protected function logInvoiceError(string $message, array $context = []): void
    {
        Log::channel('invoices')->error($message, $this->sanitizeContext($context));
    }

    /**
     * Log warning message to invoices channel
     */
    protected function logInvoiceWarning(string $message, array $context = []): void
    {
        Log::channel('invoices')->warning($message, $this->sanitizeContext($context));
    }

    /**
     * Log debug message to invoices channel (only in debug mode)
     */
    protected function logInvoiceDebug(string $message, array $context = []): void
    {
        if (config('app.debug')) {
            Log::channel('invoices')->debug($message, $this->sanitizeContext($context));
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

