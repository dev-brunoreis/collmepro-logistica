<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'invoice_pdf_url',
        'danfe_url',
        'access_key',
        'issued_at',
        'operation_date',
        'status',
        'invoice_type',
        'invoice_value',
        'freight_value',
        'series',
        'raw_data',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'operation_date' => 'datetime',
        'invoice_value' => 'decimal:2',
        'freight_value' => 'decimal:2',
        'raw_data' => 'array',
    ];

    /**
     * Get the order that owns the invoice.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to filter by invoice number.
     */
    public function scopeByInvoiceNumber($query, string $invoiceNumber)
    {
        return $query->where('invoice_number', 'like', "%{$invoiceNumber}%");
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('issued_at', [$startDate, $endDate]);
    }

    /**
     * Get the formatted issued date.
     */
    public function getFormattedIssuedDateAttribute(): string
    {
        return $this->issued_at->format('d/m/Y H:i');
    }

    /**
     * Check if invoice has PDF URL.
     */
    public function hasPdfUrl(): bool
    {
        return !empty($this->invoice_pdf_url);
    }

    /**
     * Get the PDF download URL.
     */
    public function getPdfDownloadUrl(): ?string
    {
        return $this->invoice_pdf_url;
    }
}
