<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HiddenStockProduct extends Model
{
    use HasFactory;

    protected $table = 'hidden_stock_products';

    protected $fillable = [
        'product_id',
        'source',
        'product_name',
        'product_code',
    ];

    protected $casts = [
        'product_id' => 'integer',
    ];

    /**
     * Scope to filter by product ID
     */
    public function scopeByProductId($query, $productId)
    {
        return $query->where('product_id', $productId);
    }

    /**
     * Scope to filter by source
     */
    public function scopeBySource($query, $source)
    {
        return $query->where('source', $source);
    }
}
