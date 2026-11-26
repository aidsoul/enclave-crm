<?php

namespace App\Models;

use App\Enums\CourierHandoverReason;
use App\Enums\CourierHandoverStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourierHandover extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_code',
        'from_courier_id',
        'to_courier_id',
        'initiated_by_user_id',
        'status',
        'reason',
        'reason_comment',
        'transfer_cash',
        'system_cash_amount',
        'declared_cash_amount',
        'actual_cash_amount',
        'discrepancy',
        'discrepancy_reason',
        'discrepancy_photo',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => CourierHandoverStatus::class,
        'reason' => CourierHandoverReason::class,
        'transfer_cash' => 'boolean',
        'system_cash_amount' => 'decimal:2',
        'declared_cash_amount' => 'decimal:2',
        'actual_cash_amount' => 'decimal:2',
        'discrepancy' => 'decimal:2',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function fromCourier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_courier_id');
    }

    public function toCourier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_courier_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    public function handoverOrders(): HasMany
    {
        return $this->hasMany(CourierHandoverOrder::class, 'handover_id');
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'courier_handover_orders', 'handover_id', 'order_id')
            ->withPivot('previous_status')
            ->withTimestamps();
    }

    public function isPending(): bool
    {
        return $this->status === CourierHandoverStatus::PENDING && $this->expires_at->isFuture();
    }

    public function isExpired(): bool
    {
        return $this->status === CourierHandoverStatus::PENDING && $this->expires_at->isPast();
    }
}
