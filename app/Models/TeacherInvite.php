<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TeacherInvite extends Model
{
    protected $fillable = [
        'token',
        'expires_at',
        'consumed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * An invite is usable while it has not been consumed or expired.
     */
    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    /**
     * Store a new invite whose plaintext token is returned exactly once.
     */
    public static function createWithToken(?int $expiresInDays, ?int $createdBy): array
    {
        $plain = Str::random(64);

        $invite = static::query()->create([
            'token' => hash('sha256', $plain),
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
            'created_by' => $createdBy,
        ]);

        return [$invite, $plain];
    }
}
