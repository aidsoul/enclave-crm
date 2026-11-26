<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;

/**
 * @property-read bool $has_cloud_password
 */
class User extends Model implements Authenticatable
{
    use HasFactory;

    public bool $skipAuditLog = false;

    protected $fillable = [
        'role',
        'system_name',
        'telegram_user_id',
        'telegram_username',
        'is_active',
        'wipe_confirm_code_hash',
        'cloud_password_hash',
        'cloud_password_set_at',
    ];

    protected $casts = [
        'role' => UserRole::class,
        'is_active' => 'boolean',
        'cloud_password_set_at' => 'datetime',
    ];

    protected $hidden = [
        'wipe_confirm_code_hash',
        'cloud_password_hash',
    ];

    public function getHasCloudPasswordAttribute(): bool
    {
        return $this->hasCloudPassword();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $array = parent::toArray();
        $array['has_cloud_password'] = $this->hasCloudPassword();

        return $array;
    }

    protected $attributes = [
        'is_active' => true,
    ];

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getAttribute($this->getAuthIdentifierName());
    }

    public function getAuthPasswordName(): string
    {
        return '';
    }

    public function getAuthPassword(): mixed
    {
        return null;
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByRole($query, UserRole $role)
    {
        return $query->where('role', $role);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'manager_id');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'user_id');
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class, 'actor_id');
    }

    public function targetActionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class, 'target_user_id');
    }

    public function dailyKpis(): HasMany
    {
        return $this->hasMany(DailyUserKpi::class);
    }

    public function isOwner(): bool
    {
        return $this->role === UserRole::OWNER;
    }

    public function isManager(): bool
    {
        return $this->role === UserRole::MANAGER;
    }

    public function isCourier(): bool
    {
        return $this->role === UserRole::COURIER;
    }

    public function isActive(): bool
    {
        return (bool) $this->getAttribute('is_active');
    }

    public function wasActive(): bool
    {
        return (bool) $this->getOriginal('is_active');
    }

    public function setWipeConfirmCode(?string $code): void
    {
        $this->wipe_confirm_code_hash = $code ? Hash::make($code) : null;
        $this->save();
    }

    public function hasCloudPassword(): bool
    {
        return ! empty($this->cloud_password_hash);
    }

    public function setCloudPassword(string $password): void
    {
        $this->cloud_password_hash = Hash::make($password);
        $this->cloud_password_set_at = now();
        $this->save();
    }

    public function verifyCloudPassword(string $password): bool
    {
        if (empty($this->cloud_password_hash)) {
            return false;
        }

        return Hash::check($password, $this->cloud_password_hash);
    }

    public function resetCloudPassword(): void
    {
        $this->cloud_password_hash = null;
        $this->cloud_password_set_at = null;
        $this->save();
    }
}
