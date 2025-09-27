<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'system',
        'order_number',
        'customer_name',
        'seller_name',
        'order_date',
        'nfe_number',
        'nfe_pdf_url',
        'tracking_code',
        'logistics_provider',
        'tracking_status',
    ];

    protected $casts = [
        'order_date' => 'datetime',
    ];

    /**
     * Get the order status associated with the order.
     */
    public function orderStatus(): HasOne
    {
        return $this->hasOne(OrderStatus::class);
    }

    /**
     * Get the invoices for the order.
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Get the labels for the order.
     */
    public function labels(): HasMany
    {
        return $this->hasMany(Label::class);
    }

    /**
     * Scope to filter by system.
     */
    public function scopeBySystem($query, string $system)
    {
        return $query->where('system', $system);
    }

    /**
     * Scope to filter by order number.
     */
    public function scopeByOrderNumber($query, string $orderNumber)
    {
        return $query->where('order_number', 'like', "%{$orderNumber}%");
    }

    /**
     * Scope to filter by customer name.
     */
    public function scopeByCustomerName($query, string $customerName)
    {
        return $query->where('customer_name', 'like', "%{$customerName}%");
    }

    /**
     * Scope to filter by seller name.
     */
    public function scopeBySellerName($query, string $sellerName)
    {
        return $query->where('seller_name', 'like', "%{$sellerName}%");
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('order_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by tracking status.
     */
    public function scopeByTrackingStatus($query, string $status)
    {
        return $query->where('tracking_status', $status);
    }

    /**
     * Scope to filter by logistics provider.
     */
    public function scopeByLogisticsProvider($query, string $provider)
    {
        return $query->where('logistics_provider', $provider);
    }

    /**
     * Get the formatted order date.
     */
    public function getFormattedOrderDateAttribute(): string
    {
        return $this->order_date->format('d/m/Y H:i');
    }

    /**
     * Get the customer full name.
     */
    public function getCustomerFullNameAttribute(): string
    {
        return $this->customer_name;
    }

    /**
     * Check if order has NFE.
     */
    public function hasNfe(): bool
    {
        return !empty($this->nfe_number);
    }

    /**
     * Check if order has tracking code.
     */
    public function hasTrackingCode(): bool
    {
        return !empty($this->tracking_code);
    }

    /**
     * Get the latest invoice.
     */
    public function getLatestInvoice()
    {
        return $this->invoices()->latest()->first();
    }

    /**
     * Get the latest label.
     */
    public function getLatestLabel()
    {
        return $this->labels()->latest()->first();
    }
}
