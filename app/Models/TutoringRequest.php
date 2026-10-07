<?php

namespace App\Models;

use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TutoringRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TutoringRequest extends Model
{
    /** @use HasFactory<TutoringRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'subject_id',
        'grade_id',
        'lesson_id',
        'description',
        'classification_status',
        'ai_confidence',
        'ai_payload',
        'image_hash',
        'preferred_windows',
        'budget_minor',
        'status',
        'expires_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'classification_status' => ClassificationStatus::class,
            'status' => RequestStatus::class,
            'preferred_windows' => 'array',
            'ai_payload' => 'array',
            'ai_confidence' => 'float',
            'expires_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(RequestAttachment::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(RequestResponse::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    /**
     * Requests that can still receive teacher proposals.
     */
    public function scopeMatchable(Builder $query): Builder
    {
        return $query->where('status', RequestStatus::Open->value)
            ->whereNotNull('lesson_id');
    }

    public function scopeAwaitingClassification(Builder $query): Builder
    {
        return $query->where('classification_status', ClassificationStatus::Pending->value);
    }

    /**
     * @return list<array{0: CarbonImmutable, 1: CarbonImmutable}> UTC windows
     */
    public function windows(): array
    {
        return collect($this->preferred_windows ?? [])
            ->map(fn (array $window) => [
                CarbonImmutable::parse($window['starts_at']),
                CarbonImmutable::parse($window['ends_at']),
            ])
            ->all();
    }

    public function windowLabels(?string $timezone = null): array
    {
        $timezone = $timezone ?? config('studylikepro.default_display_timezone');

        return collect($this->preferred_windows ?? [])
            ->map(function (array $window) use ($timezone) {
                $start = CarbonImmutable::parse($window['starts_at'])->setTimezone($timezone);
                $end = CarbonImmutable::parse($window['ends_at'])->setTimezone($timezone);

                return $start->format('D d M').' · '.$start->format('H:i').'–'.$end->format('H:i');
            })
            ->all();
    }

    public function isMatchable(): bool
    {
        return $this->status === RequestStatus::Open && $this->lesson_id !== null;
    }

    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Open;
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        return $this->status === RequestStatus::Open
            && $this->expires_at !== null
            && $this->expires_at->lessThanOrEqualTo($at ?? now());
    }

    public function hasPendingResponses(): bool
    {
        return $this->responses()->where('status', 'pending')->exists();
    }
}
