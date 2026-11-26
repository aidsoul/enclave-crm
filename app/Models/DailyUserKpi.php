<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyUserKpi extends Model
{
    use HasFactory;

    protected $table = 'daily_user_kpis';

    protected $fillable = [
        'user_id',
        'date',
        'role',
        'accepted_orders',
        'completed_orders',
        'cancelled_orders',
        'in_progress_orders',
        'total_response_time_seconds',
        'response_orders_count',
        'completed_deliveries',
        'total_collected',
        'cash_amount',
        'terminal_amount',
        'other_amount',
        'payout_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'role' => UserRole::class,
        'accepted_orders' => 'integer',
        'completed_orders' => 'integer',
        'cancelled_orders' => 'integer',
        'in_progress_orders' => 'integer',
        'total_response_time_seconds' => 'integer',
        'response_orders_count' => 'integer',
        'completed_deliveries' => 'integer',
        'total_collected' => 'decimal:2',
        'cash_amount' => 'decimal:2',
        'terminal_amount' => 'decimal:2',
        'other_amount' => 'decimal:2',
        'payout_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
