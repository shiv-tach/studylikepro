<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Database\Factories\TeacherProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TeacherProfile extends Model
{
    /** @use HasFactory<TeacherProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'headline',
        'bio',
        'experience_years',
        'education',
        'languages',
        'timezone',
        'lesson_duration_minutes',
        'hourly_rate_minor',
        'verification_status',
        'verification_notes',
        'submitted_at',
        'verified_at',
        'completed_at',
        'agreement_accepted_at',
        'agreement_version',
    ];

    protected function casts(): array
    {
        return [
            'languages' => 'array',
            'verification_status' => VerificationStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
            'agreement_accepted_at' => 'datetime',
            'rating_avg' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TeacherVerificationDocument::class);
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'teacher_subjects')
            ->using(TeacherSubject::class)
            ->withPivot(['grade_levels', 'rate_per_hour_minor'])
            ->withTimestamps();
    }

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'teacher_topics')->withTimestamps();
    }

    public function availabilitySlots(): HasMany
    {
        return $this->hasMany(TeacherAvailabilitySlot::class)
            ->orderBy('day_of_week')
            ->orderBy('start_minute');
    }

    public function timeOff(): HasMany
    {
        return $this->hasMany(TeacherTimeOff::class)->orderBy('starts_on');
    }

    public function requestResponses(): HasMany
    {
        return $this->hasMany(RequestResponse::class)->latest();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class)->latest('starts_at');
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(TeacherEarning::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    /**
     * Reviews the public can see.
     */
    public function visibleReviews(): HasMany
    {
        return $this->hasMany(Review::class)->visible()->latest();
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class)->latest();
    }

    /**
     * Payout money that has been earned but not yet batched.
     */
    public function availableBalanceMinor(): int
    {
        return (int) $this->earnings()->eligible()->sum(DB::raw('amount_minor - reversed_minor'));
    }

    public function pendingBalanceMinor(): int
    {
        return (int) $this->earnings()->pending()->sum(DB::raw('amount_minor - reversed_minor'));
    }

    /**
     * Publicly visible teachers.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('verification_status', VerificationStatus::Approved);
    }

    public function lessonDuration(): int
    {
        return $this->lesson_duration_minutes ?: 60;
    }

    /**
     * The rate a student pays for this subject, in minor units.
     */
    public function effectiveRateFor(?Subject $subject = null): int
    {
        if ($subject) {
            $pivotRate = $this->subjects
                ->firstWhere('id', $subject->id)?->pivot->rate_per_hour_minor;

            if ($pivotRate) {
                return (int) $pivotRate;
            }
        }

        return (int) $this->hourly_rate_minor;
    }

    /**
     * The cheapest rate across the base rate and per-subject overrides.
     */
    public function startingRateMinor(): int
    {
        $overrides = $this->subjects
            ->map(fn (Subject $subject) => $subject->pivot->rate_per_hour_minor)
            ->filter()
            ->map(fn ($rate) => (int) $rate);

        return $overrides->isEmpty()
            ? (int) $this->hourly_rate_minor
            : min((int) $this->hourly_rate_minor, $overrides->min());
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function isApproved(): bool
    {
        return $this->verification_status === VerificationStatus::Approved;
    }

    public function hourlyRate(): float
    {
        return $this->hourly_rate_minor / 100;
    }
}
