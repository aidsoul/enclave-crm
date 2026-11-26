<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id',
        'date',
        'total_revenue',
        'total_purchase',
        'courier_payouts',
        'unforeseen_expenses',
        'net_profit',
    ];

    protected $casts = [
        'date' => 'date',
        'total_revenue' => 'decimal:2',
        'total_purchase' => 'decimal:2',
        'courier_payouts' => 'decimal:2',
        'unforeseen_expenses' => 'decimal:2',
        'net_profit' => 'decimal:2',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeForPeriod($query, $from, $to)
    {
        return $query->whereBetween('date', [$from, $to]);
    }
}
