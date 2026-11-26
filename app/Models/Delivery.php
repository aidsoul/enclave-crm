<?php

namespace App\Models;

use App\Enums\DeliveryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Delivery extends Model
{
    use HasFactory;

    protected $touches = ['order'];

    protected $fillable = [
        'order_id',
        'type',
        'city',
        'user_id',
        'courier_id',
        'courier_comment',
        'pickup_point',
        'delivery_address',
        'delivery_cost',
        'delivery_price',
        'delivery_date',
        'delivery_slot',
        'photo_proof_path',
        'photo_proof_paths',
        'photo_proof_at',
        'signature_path',
        'signature_at',
    ];

    protected $casts = [
        'type' => DeliveryType::class,
        'delivery_cost' => 'decimal:2',
        'delivery_price' => 'decimal:2',
        'delivery_date' => 'date',
        'photo_proof_paths' => 'array',
        'photo_proof_at' => 'datetime',
        'signature_at' => 'datetime',
    ];

    protected $appends = [
        'photo_proof_url',
        'photo_proof_urls',
        'signature_url',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Delivery $delivery) {
            $delivery->deletePhotoProofFile();
            $delivery->deleteSignatureFile();
        });
    }

    public function getPhotoProofUrlsAttribute(): array
    {
        $paths = $this->photo_proof_paths;
        if (! is_array($paths) || empty($paths)) {
            if ($this->photo_proof_path && $this->photo_proof_path !== '0') {
                $paths = [$this->photo_proof_path];
            } else {
                return [];
            }
        }

        return array_values(array_map(function ($p) {
            return '/storage/'.ltrim($p, '/');
        }, array_filter($paths, fn ($p) => ! empty($p) && $p !== '0' && Storage::disk('public')->exists($p))));
    }

    public function getPhotoProofUrlAttribute(): ?string
    {
        $urls = $this->photo_proof_urls;

        return ! empty($urls) ? $urls[0] : null;
    }

    public function deletePhotoProofFile(?string $specificPath = null): void
    {
        $disk = Storage::disk('public');
        $allPaths = is_array($this->photo_proof_paths) ? $this->photo_proof_paths : [];
        if ($this->photo_proof_path && ! in_array($this->photo_proof_path, $allPaths, true)) {
            $allPaths[] = $this->photo_proof_path;
        }

        if ($specificPath !== null && $specificPath !== '') {
            $parsed = parse_url($specificPath, PHP_URL_PATH) ?? $specificPath;
            $rel = str_starts_with($parsed, '/storage/') ? substr($parsed, 9) : ltrim($parsed, '/');

            $matches = false;
            $remaining = [];
            foreach ($allPaths as $p) {
                $pParsed = parse_url($p, PHP_URL_PATH) ?? $p;
                $normP = str_starts_with($pParsed, '/storage/') ? substr($pParsed, 9) : ltrim($pParsed, '/');

                if ($normP === $rel) {
                    $matches = true;
                } else {
                    $remaining[] = $p;
                }
            }

            if ($matches && $disk->exists($rel)) {
                $disk->delete($rel);
            }

            $this->update([
                'photo_proof_paths' => ! empty($remaining) ? array_values($remaining) : null,
                'photo_proof_path' => ! empty($remaining) ? array_values($remaining)[0] : null,
                'photo_proof_at' => ! empty($remaining) ? $this->photo_proof_at : null,
            ]);

            return;
        }

        foreach ($allPaths as $path) {
            if ($path) {
                $parsed = parse_url($path, PHP_URL_PATH) ?? $path;
                $rel = str_starts_with($parsed, '/storage/') ? substr($parsed, 9) : ltrim($parsed, '/');
                if ($disk->exists($rel)) {
                    $disk->delete($rel);
                }
            }
        }

        $this->update([
            'photo_proof_paths' => null,
            'photo_proof_path' => null,
            'photo_proof_at' => null,
        ]);
    }

    public function getSignatureUrlAttribute(): ?string
    {
        if (! $this->signature_path || $this->signature_path === '0') {
            return null;
        }

        if (! Storage::disk('public')->exists($this->signature_path)) {
            return null;
        }

        return '/storage/'.ltrim($this->signature_path, '/');
    }

    public function hasSignature(): bool
    {
        if (empty($this->signature_path) || $this->signature_path === '0') {
            return false;
        }

        return Storage::disk('public')->exists($this->signature_path);
    }

    public function deleteSignatureFile(bool $updateAttributes = true): void
    {
        if (! $this->signature_path) {
            return;
        }

        $disk = Storage::disk('public');
        $parsed = parse_url($this->signature_path, PHP_URL_PATH) ?? $this->signature_path;
        $rel = str_starts_with($parsed, '/storage/') ? substr($parsed, 9) : ltrim($parsed, '/');

        if ($disk->exists($rel)) {
            $disk->delete($rel);
        }

        if ($updateAttributes) {
            $this->update([
                'signature_path' => null,
                'signature_at' => null,
            ]);
        }
    }

    public function getCourierIdAttribute(): ?int
    {
        return $this->user_id !== null ? (int) $this->user_id : null;
    }

    public function setCourierIdAttribute($value): void
    {
        $this->attributes['user_id'] = $value;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function courier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
