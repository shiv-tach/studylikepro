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
use Illuminate\Support\Collection;
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
            ->withPivot(['grade_levels', 'rate_per_hour_minor', 'grade_rates'])
            ->withTimestamps();
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'teacher_lessons')->withTimestamps();
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
     * The rate a student pays for this subject and grade, in minor units:
     * an explicit grade rate wins, then the subject override, then the base rate.
     */
    public function effectiveRateFor(?Subject $subject = null, ?int $gradeId = null): int
    {
        if ($subject) {
            $pivot = $this->subjects->firstWhere('id', $subject->id)?->pivot;

            if ($pivot) {
                if ($gradeId !== null) {
                    $gradeRate = static::gradeRatesFrom($pivot)[$gradeId] ?? null;

                    if ($gradeRate !== null) {
                        return $gradeRate;
                    }
                }

                if ($pivot->rate_per_hour_minor) {
                    return (int) $pivot->rate_per_hour_minor;
                }
            }
        }

        return (int) $this->hourly_rate_minor;
    }

    /**
     * The price for every grade this teacher covers on a subject, in grade
     * order, so public pages can show one rate per grade.
     *
     * @param  Collection<int, Grade>|null  $grades  preloaded grades keyed by id
     * @return Collection<int, array{grade: Grade, rate_minor: int}>
     */
    public function gradeRatesFor(Subject $subject, ?Collection $grades = null): Collection
    {
        $ids = static::gradeIdsFrom($this->subjects->firstWhere('id', $subject->id)?->pivot);

        if ($ids === []) {
            return collect();
        }

        return $this->resolveGrades($ids, $grades)->map(fn (Grade $grade) => [
            'grade' => $grade,
            'rate_minor' => $this->effectiveRateFor($subject, (int) $grade->id),
        ])->values();
    }

    /**
     * The grades behind a set of ids, ordered. Falls back to the database when
     * the preloaded collection does not hold every id (e.g. a subject moved to
     * another level after the teacher picked its grades).
     *
     * @param  array<int, int>  $ids
     * @param  Collection<int, Grade>|null  $grades  preloaded grades keyed by id
     * @return Collection<int, Grade>
     */
    private function resolveGrades(array $ids, ?Collection $grades): Collection
    {
        $resolved = $grades?->only($ids)->values()->sortBy('sort_order')->values();

        if ($resolved !== null && $resolved->count() === count($ids)) {
            return $resolved;
        }

        return Grade::query()->whereIn('id', $ids)->ordered()->get();
    }

    /**
     * The cheapest rate this teacher charges, in minor units: the cheapest
     * grade rate or override across their subjects, or the base rate when no
     * subject is set up. With a grade id, only subjects that cover that grade
     * count.
     */
    public function startingRateMinor(?int $gradeId = null): int
    {
        if ($gradeId !== null) {
            $covering = $this->subjects->filter(
                fn (Subject $subject) => in_array($gradeId, static::gradeIdsFrom($subject->pivot), true)
            );

            return $covering->isEmpty()
                ? (int) $this->hourly_rate_minor
                : (int) $covering->map(fn (Subject $subject) => $this->effectiveRateFor($subject, $gradeId))->min();
        }

        if ($this->subjects->isEmpty()) {
            return (int) $this->hourly_rate_minor;
        }

        return (int) $this->subjects
            ->map(fn (Subject $subject) => $this->cheapestRateFor($subject))
            ->min();
    }

    /**
     * The cheapest rate inside one subject, in minor units: its grade rates
     * when it has any, otherwise the subject override, otherwise the base rate.
     */
    public function cheapestRateFor(Subject $subject): int
    {
        $gradeIds = static::gradeIdsFrom($this->subjects->firstWhere('id', $subject->id)?->pivot);

        if ($gradeIds === []) {
            return $this->effectiveRateFor($subject);
        }

        return (int) collect($gradeIds)
            ->map(fn (int $gradeId) => $this->effectiveRateFor($subject, $gradeId))
            ->min();
    }

    /**
     * Explicit per-grade rates on a pivot row, keyed by grade id.
     *
     * @return array<int, int>
     */
    private static function gradeRatesFrom(mixed $pivot): array
    {
        return collect((array) ($pivot?->grade_rates ?? []))
            ->mapWithKeys(fn ($rate, $gradeId) => [(int) $gradeId => (int) $rate])
            ->filter(fn (int $rate) => $rate > 0)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private static function gradeIdsFrom(mixed $pivot): array
    {
        return array_values(array_unique(array_map('intval', (array) ($pivot?->grade_levels ?? []))));
    }

    /**
     * The grades this teacher covers for a subject, as a label: a contiguous
     * run collapses to "Grades 6-9", gaps fall back to the grade labels.
     *
     * @param  Collection<int, Grade>|null  $grades  preloaded grades keyed by id
     */
    public function gradeScopeLabelFor(Subject $subject, ?Collection $grades = null): ?string
    {
        return $this->gradeScopeLabelFromIds((array) ($subject->pivot->grade_levels ?? []), $grades);
    }

    /**
     * Every grade this teacher covers across all subjects, as one label.
     *
     * @param  Collection<int, Grade>|null  $grades  preloaded grades keyed by id
     */
    public function gradeScopeLabel(?Collection $grades = null): ?string
    {
        $ids = $this->subjects
            ->flatMap(fn (Subject $subject) => (array) ($subject->pivot->grade_levels ?? []))
            ->all();

        return $this->gradeScopeLabelFromIds($ids, $grades);
    }

    /**
     * @param  array<int, mixed>  $ids
     * @param  Collection<int, Grade>|null  $grades
     */
    private function gradeScopeLabelFromIds(array $ids, ?Collection $grades = null): ?string
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return null;
        }

        $grades = $this->resolveGrades($ids, $grades);

        if ($grades->isEmpty()) {
            return null;
        }

        $numbers = $grades
            ->filter(fn (Grade $grade) => $grade->number !== null)
            ->map(fn (Grade $grade) => (int) $grade->number)
            ->sort()
            ->values();

        if ($numbers->count() === $grades->count()
            && $numbers->count() >= 2
            && $numbers->last() - $numbers->first() === $numbers->count() - 1) {
            return "Grades {$numbers->first()}-{$numbers->last()}";
        }

        return $grades->pluck('label')->implode(', ');
    }

    public function isComplete(): bool
    {
        return $this->completed_at !== null;
    }

    public function hasSubmittedVerification(): bool
    {
        // Null only for an in-memory profile that has not been reloaded yet,
        // e.g. straight after firstOrNew() in the onboarding controller.
        return (bool) $this->verification_status?->isSubmitted();
    }

    /**
     * Both onboarding steps are done: profile saved and documents submitted.
     */
    public function hasCompletedOnboarding(): bool
    {
        return $this->isComplete() && $this->hasSubmittedVerification();
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
