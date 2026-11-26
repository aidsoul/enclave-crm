<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'role',
        'system_name',
        'max_activations',
        'used_count',
        'expires_at',
        'created_by',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isValid(): bool
    {
        return $this->is_active
            && $this->expires_at->isFuture()
            && $this->used_count < $this->max_activations;
    }
}
