<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierHandoverOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'handover_id',
        'order_id',
        'previous_status',
    ];

    public function handover(): BelongsTo
    {
        return $this->belongsTo(CourierHandover::class, 'handover_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
