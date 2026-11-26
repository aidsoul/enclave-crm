<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\StrHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property array<string, mixed>|null $extra_data
 */
class Order extends Model
{
    use HasFactory;

    public bool $skipAuditLog = false;

    protected $fillable = [
        'site_id',
        'external_id',
        'manager_id',
        'courier_id',
        'customer_id',
        'total_price',
        'purchase_price',
        'status',
        'is_edited',
        'is_valuable',
        'has_unread_driver_message',
        'has_unread_manager_message',
        'unread_driver_messages_count',
        'unread_manager_messages_count',
        'last_message_at',
        'extra_data',
    ];

    protected $casts = [
        'status' => OrderStatus::class,
        'is_edited' => 'boolean',
        'is_valuable' => 'boolean',
        'has_unread_driver_message' => 'boolean',
        'has_unread_manager_message' => 'boolean',
        'unread_driver_messages_count' => 'integer',
        'unread_manager_messages_count' => 'integer',
        'last_message_at' => 'datetime',
        'total_price' => 'decimal:2',
        'purchase_price' => 'decimal:2',
        'extra_data' => 'array',
        'external_id' => 'string',
    ];

    protected $attributes = [
        'is_edited' => false,
        'is_valuable' => false,
        'has_unread_driver_message' => false,
        'has_unread_manager_message' => false,
        'unread_driver_messages_count' => 0,
        'unread_manager_messages_count' => 0,
    ];

    public function requiresClientSignature(): bool
    {
        if ($this->is_valuable) {
            return true;
        }

        $threshold = (float) config('orders.valuable_threshold', 50000.0);

        return $threshold > 0 && (float) $this->total_price >= $threshold;
    }

    public function getExternalIdAttribute($value): ?string
    {
        if ($value !== null && $value !== '') {
            return (string) $value;
        }

        $fallback = $this->extra_data['external_order_id'] ?? $this->extra_data['external_id'] ?? null;

        return $fallback !== null && $fallback !== '' ? (string) $fallback : null;
    }

    public function scopeForManager($query, int $managerId)
    {
        return $query->where('manager_id', $managerId);
    }

    public function scopeForCourier($query, int $courierId)
    {
        return $query->where(function ($q) use ($courierId) {
            $q->where('courier_id', $courierId)
                ->orWhereHas('delivery', function ($q2) use ($courierId) {
                    $q2->where('user_id', $courierId);
                });
        });
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeCreatedToday($query)
    {
        return $query->whereDate('created_at', now()->toDateString());
    }

    public function scopeByStatus($query, OrderStatus $status)
    {
        return $query->where('status', $status);
    }

    public function scopeEdited($query)
    {
        return $query->where('is_edited', true);
    }

    public function scopeSearch($query, string $search)
    {
        $escaped = StrHelper::escapeLike($search);

        return $query->where(function ($q) use ($search, $escaped) {
            if (ctype_digit($search)) {
                $q->orWhere('id', (int) $search);
            }

            $q->orWhereHas('customer', function ($q2) use ($escaped) {
                $q2->where('name', 'ilike', "%{$escaped}%")
                    ->orWhere('phone', 'ilike', "%{$escaped}%")
                    ->orWhere('email', 'ilike', "%{$escaped}%");
            });
        });
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function getSiteDisplayNameAttribute(): string
    {
        return $this->site?->display_name ?? '—';
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function delivery(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(OrderComment::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderMessage::class);
    }

    public function profit(): float
    {
        return (float) $this->total_price - (float) $this->purchase_price;
    }

    protected static function booted(): void
    {
        static::deleting(function (Order $order) {
            foreach ($order->delivery as $delivery) {
                $delivery->deletePhotoProofFile();
                $delivery->deleteSignatureFile();
            }

            foreach ($order->messages as $msg) {
                $msg->deleteAttachmentFile();
            }

            if (Storage::disk('public')->exists("chat/{$order->id}")) {
                Storage::disk('public')->deleteDirectory("chat/{$order->id}");
            }

            $productIds = $order->items()
                ->whereNotNull('product_id')
                ->pluck('product_id')
                ->unique();

            if ($productIds->isNotEmpty()) {
                $productsWithOtherOrders = OrderItem::whereIn('product_id', $productIds)
                    ->where('order_id', '!=', $order->id)
                    ->pluck('product_id')
                    ->unique();

                $orphanedIds = $productIds->diff($productsWithOtherOrders);
                if ($orphanedIds->isNotEmpty()) {
                    $products = Product::whereIn('id', $orphanedIds)->get();
                    foreach ($products as $product) {
                        $product->deleteImageFile();
                    }
                }
            }
        });
    }
}
