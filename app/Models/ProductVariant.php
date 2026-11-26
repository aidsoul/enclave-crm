<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'external_id',
        'sku',
        'title',
        'attributes',
        'price',
        'purchase_price',
        'is_active',
    ];

    protected $casts = [
        'attributes' => 'array',
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getEffectivePriceAttribute(): float
    {
        if ($this->price !== null) {
            return (float) $this->price;
        }

        return (float) ($this->product?->price ?? 0);
    }

    public function getEffectivePurchasePriceAttribute(): float
    {
        if ($this->purchase_price !== null) {
            return (float) $this->purchase_price;
        }

        return (float) ($this->product?->cost_price ?? 0);
    }
}
