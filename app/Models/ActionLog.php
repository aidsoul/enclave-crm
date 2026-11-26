<?php

namespace App\Models;

use App\Enums\ActionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActionLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'order_id',
        'lead_id',
        'target_user_id',
        'customer_id',
        'site_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'action_type' => ActionType::class,
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActionLog $log) {
            if (! $log->created_at) {
                $log->created_at = now();
            }

            if ($log->ip_address === null) {
                try {
                    $ip = request()?->ip();
                    if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
                        $log->ip_address = substr($ip, 0, 45);
                    }
                } catch (\Throwable) {
                }
            }

            if ($log->user_agent === null) {
                try {
                    $ua = request()?->userAgent();
                    if ($ua) {
                        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $ua);
                        $log->user_agent = $cleaned ? mb_substr($cleaned, 0, 255) : null;
                    }
                } catch (\Throwable) {
                }
            }
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function targetUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function scopeForOrder($query, int $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    public function scopeForLead($query, int $leadId)
    {
        return $query->where('lead_id', $leadId);
    }

    public function scopeForCustomer($query, int $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('target_user_id', $userId);
    }

    public function scopeForSite($query, int $siteId)
    {
        return $query->where('site_id', $siteId);
    }

    public function scopeByActor($query, int $actorId)
    {
        return $query->where('actor_id', $actorId);
    }

    public function scopeByActionType($query, ActionType $type)
    {
        return $query->where('action_type', $type);
    }

    public function scopeEdited($query)
    {
        return $query->whereIn('field_name', ['total_price', 'purchase_price']);
    }
}
