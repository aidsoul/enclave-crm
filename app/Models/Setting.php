<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'scope', 'user_id', 'role'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
