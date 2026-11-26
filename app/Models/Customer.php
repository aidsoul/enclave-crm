<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Support\PhoneNumber;
use App\Support\StrHelper;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'name',
        'phone',
        'email',
        'address',
        'is_blacklisted',
        'blacklist_reason',
        'tags',
    ];

    protected $casts = [
        'is_blacklisted' => 'boolean',
        'tags' => 'array',
    ];

    protected $attributes = [
        'is_blacklisted' => false,
    ];

    protected $appends = [
        'formatted_phone',
        'phone_with_plus',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class);
    }

    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => PhoneNumber::normalize($value) ?? (preg_replace('/\D/', '', (string) $value) ?: null),
        );
    }

    protected function phoneWithPlus(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => PhoneNumber::withPlus($attributes['phone'] ?? null),
        );
    }

    protected function formattedPhone(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => PhoneNumber::format($attributes['phone'] ?? null),
        );
    }

    public function scopeSearch($query, string $search)
    {
        $escaped = StrHelper::escapeLike($search);
        $digits = preg_replace('/\D+/', '', $search);

        return $query->where(function ($q) use ($escaped, $digits) {
            $q->where('name', 'ilike', "%{$escaped}%")
                ->orWhere('phone', 'ilike', "%{$escaped}%")
                ->orWhere('email', 'ilike', "%{$escaped}%")
                ->orWhere('address', 'ilike', "%{$escaped}%");

            if (! empty($digits) && strlen($digits) >= 3) {
                $q->orWhere('phone', 'like', "%{$digits}%");
            }
        });
    }

    public function calculateLtv(): float
    {
        return (float) $this->orders()
            ->where('status', '!=', OrderStatus::CANCELLED)
            ->sum('total_price');
    }

    public function isBlacklisted(): bool
    {
        return (bool) $this->is_blacklisted;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }

    public function scopeBlacklisted($query, bool $blacklisted = true)
    {
        return $query->where('is_blacklisted', $blacklisted);
    }

    public function scopeWithTag($query, string $tag)
    {
        return $query->whereJsonContains('tags', $tag);
    }
}
