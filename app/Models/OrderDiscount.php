<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDiscount extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'type',
        'value',
        'code',
    ];

    protected $casts = [
        'type' => DiscountType::class,
        'value' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
