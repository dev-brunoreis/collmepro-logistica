<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $fillable = [
        'order_id',
        'sync_type',
        'system',
        'status',
        'attempts',
        'max_attempts',
        'message',
        'error_message',
        'error_trace',
        'request_data',
        'response_data',
        'started_at',
        'completed_at',
        'duration_ms',
        'external_id',
        'job_id',
        'metadata',
    ];

    protected $casts = [
        'error_trace' => 'array',
        'request_data' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Sync type constants
     */
    const TYPE_ORDER_IMPORT = 'order_import';
    const TYPE_INVOICE_SYNC = 'invoice_sync';
    const TYPE_TRACKING_FETCH = 'tracking_fetch';

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_SKIPPED = 'skipped';

    /**
     * Get the order that owns the sync log.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Marca o log como iniciado
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => self::STATUS_PROCESSING,
            'started_at' => now(),
        ]);
    }

    /**
     * Marca o log como bem-sucedido
     */
    public function markAsSuccess(?string $message = null, ?array $responseData = null): void
    {
        $startedAt = $this->started_at ?? now();
        $completedAt = now();
        $durationMs = $startedAt->diffInMilliseconds($completedAt);

        $this->update([
            'status' => self::STATUS_SUCCESS,
            'message' => $message,
            'response_data' => $responseData,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Marca o log como falha
     */
    public function markAsFailed(string $errorMessage, ?\Throwable $exception = null): void
    {
        $startedAt = $this->started_at ?? now();
        $completedAt = now();
        $durationMs = $startedAt->diffInMilliseconds($completedAt);

        $updateData = [
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
            'attempts' => $this->attempts + 1,
        ];

        if ($exception) {
            $updateData['error_trace'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }

        $this->update($updateData);
    }

    /**
     * Marca o log como pulado
     */
    public function markAsSkipped(string $reason): void
    {
        $startedAt = $this->started_at ?? now();
        $completedAt = now();
        $durationMs = $startedAt->diffInMilliseconds($completedAt);

        $this->update([
            'status' => self::STATUS_SKIPPED,
            'message' => $reason,
            'completed_at' => $completedAt,
            'duration_ms' => $durationMs,
        ]);
    }

    /**
     * Verifica se pode tentar novamente
     */
    public function canRetry(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('sync_type', $type);
    }

    /**
     * Scope para filtrar por status
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope para filtrar por sistema
     */
    public function scopeForSystem($query, string $system)
    {
        return $query->where('system', $system);
    }

    /**
     * Scope para logs falhos que podem ser retentados
     */
    public function scopeRetryable($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->whereColumn('attempts', '<', 'max_attempts');
    }
}
