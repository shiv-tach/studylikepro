<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The unsaved answers of the teacher onboarding wizard, one row per teacher.
 *
 * `payload` is keyed by wizard step (`about`, `preferences`, `availability`)
 * and only ever holds the fields of that step, so restoring a step cannot leak
 * answers from another one. The Blade pages merge it under `old()` and over the
 * stored profile values.
 */
class TeacherOnboardingDraft extends Model
{
    /** @use HasFactory<\Database\Factories\TeacherOnboardingDraftFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payload',
        'current_step',
        'saved_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'saved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The saved answers of one step, or an empty array when nothing was drafted.
     *
     * @return array<string, mixed>
     */
    public function step(string $step): array
    {
        $payload = $this->payload ?? [];

        return is_array($payload[$step] ?? null) ? $payload[$step] : [];
    }

    public function hasStep(string $step): bool
    {
        return $this->step($step) !== [];
    }

    /**
     * True when no step still holds a draft (for example after every step of
     * the wizard was saved or explicitly discarded).
     */
    public function isEmpty(): bool
    {
        foreach (($this->payload ?? []) as $step => $data) {
            if (is_array($data) && $data !== []) {
                return false;
            }
        }

        return true;
    }
}
