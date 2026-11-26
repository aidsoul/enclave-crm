<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'channel',
        'message',
        'attachment_path',
        'edited_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    protected $appends = [];

    protected static function booted(): void
    {
        static::deleting(function (OrderMessage $message) {
            $message->deleteAttachmentFile();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    public function deleteAttachmentFile(): void
    {
        if ($this->attachment_path && Storage::disk('public')->exists($this->attachment_path)) {
            Storage::disk('public')->delete($this->attachment_path);
        }
    }

    public function canBeEditedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        $order = $this->order ?? Order::find($this->order_id);
        if ($order && $order->status === OrderStatus::COMPLETED) {
            return false;
        }

        if ($user->isManager()) {
            if ((int) $this->user_id !== (int) $user->id) {
                return false;
            }

            if ($this->created_at && $this->created_at->diffInMinutes(now()) > 10) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function canBeDeletedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->isOwner();
    }
}
