<?php

namespace App\Models;

use App\Enums\LeadStatus;
use App\Enums\LeadType;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'customer_id',
        'manager_id',
        'converted_order_id',
        'type',
        'status',
        'name',
        'phone',
        'email',
        'message',
        'product_name',
        'product_sku',
        'page_url',
        'page_title',
        'external_id',
        'extra_data',
    ];

    protected $casts = [
        'type' => LeadType::class,
        'status' => LeadStatus::class,
        'extra_data' => 'array',
    ];

    protected $appends = [
        'formatted_phone',
        'phone_with_plus',
        'type_label',
        'status_label',
        'status_color',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
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

    protected function typeLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type instanceof LeadType ? $this->type->label() : ($this->type ?? 'Заказ звонка'),
        );
    }

    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status instanceof LeadStatus ? $this->status->label() : ($this->status ?? 'Новая'),
        );
    }

    protected function statusColor(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status instanceof LeadStatus ? $this->status->color() : 'primary',
        );
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForStatus($query, LeadStatus|string $status)
    {
        $val = $status instanceof LeadStatus ? $status->value : $status;

        return $query->where('status', $val);
    }
}
