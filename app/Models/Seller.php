<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Seller extends Model
{
    use HasFactory;

    protected $fillable = [
        'woocommerce_id',
        'name',
        'email',
    ];

    /**
     * Get all orders associated with this seller
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(OrderSummary::class, 'order_summary_seller')
            ->withTimestamps();
    }
}

