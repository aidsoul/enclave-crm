<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'api_token',
        'domain',
        'default_purchase_percent',
        'is_active',
    ];

    protected $casts = [
        'default_purchase_percent' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    protected $hidden = [
        'api_token_hash',
        'api_token',
    ];

    protected $appends = [
        'display_name',
        'has_token',
        'api_token',
    ];

    /**
     * In-memory transient plain token (populated on generation, rotation, or explicit set).
     */
    protected ?string $plainApiToken = null;

    /**
     * In-memory previous plain token for audit or tracking.
     */
    protected ?string $oldPlainApiToken = null;

    public function getDisplayNameAttribute(): string
    {
        return $this->domain ?: '—';
    }

    public function getHasTokenAttribute(): bool
    {
        return ! empty($this->api_token_hash);
    }

    public function setApiTokenAttribute(?string $value): void
    {
        $this->oldPlainApiToken = $this->plainApiToken;

        if ($value === null || $value === '') {
            $this->plainApiToken = null;
            $this->attributes['api_token_hash'] = null;
        } else {
            $this->plainApiToken = $value;
            $this->attributes['api_token_hash'] = hash('sha256', $value);
        }
    }

    public function getApiTokenAttribute(): ?string
    {
        if ($this->plainApiToken !== null) {
            return $this->plainApiToken;
        }

        if (! empty($this->attributes['api_token_hash'])) {
            return '••••••••••••••••••••••••••••••••';
        }

        return null;
    }

    public function setPlainApiToken(?string $token): self
    {
        $this->plainApiToken = $token;

        return $this;
    }

    public function getPlainApiToken(): ?string
    {
        return $this->plainApiToken;
    }

    public function getOldPlainApiToken(): ?string
    {
        return $this->oldPlainApiToken;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($site) {
            if (empty($site->api_token_hash) && empty($site->plainApiToken)) {
                $plain = Str::uuid()->toString();
                $site->plainApiToken = $plain;
                $site->attributes['api_token_hash'] = hash('sha256', $plain);
            }
        });
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getCachedActive()
    {
        return Cache::remember('sites:reference_list', 86400, function () {
            return static::active()
                ->orderBy('domain')
                ->get(['id', 'domain', 'name', 'default_purchase_percent']);
        });
    }

    public static function findByTokenCached(?string $token): ?self
    {
        if (! $token || ! Str::isUuid($token)) {
            return null;
        }

        $hash = hash('sha256', $token);
        $cacheKey = "site:token_hash:{$hash}";

        $cached = Cache::get($cacheKey);
        if ($cached === '__missing__') {
            return null;
        }

        if ($cached instanceof self) {
            $cached->setPlainApiToken($token);

            return $cached;
        }

        $site = static::where('api_token_hash', $hash)->active()->first();
        if (! $site) {
            // Negative caching for 60s to protect against cache penetration / DoS with random UUIDs
            Cache::put($cacheKey, '__missing__', 60);

            return null;
        }

        // Store clean model in Redis (ensuring no plaintext token is serialized into Redis)
        $site->plainApiToken = null;
        Cache::put($cacheKey, $site, 3600);

        $site->setPlainApiToken($token);

        return $site;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function financialStats(): HasMany
    {
        return $this->hasMany(FinancialStat::class);
    }

    public function actionLogs(): HasMany
    {
        return $this->hasMany(ActionLog::class);
    }

    public function rotateApiToken(): string
    {
        return DB::transaction(function () {
            $this->lockForUpdate();
            $oldHash = $this->api_token_hash;

            $newToken = Str::uuid()->toString();
            $this->plainApiToken = $newToken;
            $this->attributes['api_token_hash'] = hash('sha256', $newToken);
            $this->save();

            if ($oldHash) {
                Cache::forget("site:token_hash:{$oldHash}");
            }

            return $newToken;
        });
    }

    public function revokeApiToken(): void
    {
        DB::transaction(function () {
            $this->lockForUpdate();
            $oldHash = $this->api_token_hash;

            $this->plainApiToken = null;
            $this->attributes['api_token_hash'] = null;
            $this->save();

            if ($oldHash) {
                Cache::forget("site:token_hash:{$oldHash}");
            }
        });
    }
}
