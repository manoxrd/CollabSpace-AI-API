<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the refresh token has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Find a refresh token by its raw (unhashed) value.
     */
    public static function findByRawToken(string $rawToken): ?self
    {
        return static::where('token', hash('sha256', $rawToken))->first();
    }

    /**
     * Generate a cryptographically secure raw token and return it.
     * Store only the SHA-256 hash in the DB — never the raw token.
     *
     * @return array{raw: string, hash: string}
     */
    public static function generateToken(): array
    {
        $raw = bin2hex(random_bytes(64)); // 128-character hex string

        return [
            'raw' => $raw,
            'hash' => hash('sha256', $raw),
        ];
    }
}
