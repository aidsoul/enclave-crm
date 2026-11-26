<?php

namespace App\Models;

use App\Services\AnalyticsService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'external_product_id',
        'product_variant_id',
        'external_variant_id',
        'name',
        'variant_title',
        'sku',
        'qty',
        'price',
        'purchase_price',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'product_variant_id' => 'integer',
        'qty' => 'integer',
        'price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function subtotal(): float
    {
        return (float) $this->price * (int) $this->qty;
    }

    protected static function booted(): void
    {
        static::saved(function () {
            AnalyticsService::invalidateDashboardStatsCache();
        });
        static::deleted(function () {
            AnalyticsService::invalidateDashboardStatsCache();
        });
    }
}
