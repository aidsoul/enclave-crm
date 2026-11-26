<?php

namespace App\Models;

use App\Support\StrHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'external_id',
        'name',
        'sku',
        'description',
        'image_url',
        'url',
        'price',
        'cost_price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeSearch($query, string $search)
    {
        $escaped = StrHelper::escapeLike($search);

        return $query->where(function ($q) use ($escaped) {
            $q->where('name', 'ilike', "%{$escaped}%")
                ->orWhere('sku', 'ilike', "%{$escaped}%")
                ->orWhereHas('variants', function ($vq) use ($escaped) {
                    $vq->where('title', 'ilike', "%{$escaped}%")
                        ->orWhere('sku', 'ilike', "%{$escaped}%");
                });
        });
    }

    public function getMarginAttribute(): float
    {
        return (float) $this->price - (float) $this->cost_price;
    }

    public function deleteImageFile(): void
    {
        if (! $this->image_url) {
            return;
        }

        $parsed = parse_url($this->image_url, PHP_URL_PATH) ?? $this->image_url;
        $path = ltrim($parsed, '/');
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (Product $product) {
            $product->deleteImageFile();
        });
    }
}
