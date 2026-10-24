<?php

namespace App\Models;

use App\Enums\ServiceType;
use Database\Factories\ServiceOfferingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A teacher's priced catalog entry for one service type at a lesson/subject scope.
 */
class ServiceOffering extends Model
{
    /** @use HasFactory<ServiceOfferingFactory> */
    use HasFactory;

    protected $fillable = [
        'teacher_profile_id',
        'service_type',
        'subject_id',
        'lesson_id',
        'grade_id',
        'title',
        'description',
        'price_minor',
        'currency',
        'allocation_minutes',
        'default_session_minutes',
        'max_sessions',
        'max_pages',
        'turnaround_hours',
        'rubric',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'service_type' => ServiceType::class,
            'price_minor' => 'integer',
            'allocation_minutes' => 'integer',
            'default_session_minutes' => 'integer',
            'max_sessions' => 'integer',
            'max_pages' => 'integer',
            'turnaround_hours' => 'integer',
            'rubric' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The grading criteria this offering marks against, in display order.
     * An empty list means the studio falls back to free-form marks rows.
     *
     * @return list<array{key: string, title: string, max: float, description: string|null}>
     */
    public function rubricCriteria(): array
    {
        return self::normalizeRubric($this->rubric);
    }

    /**
     * Drop blank rows and stamp every kept row with a stable key and a
     * positive maximum, so both the composer and the studio read one shape.
     *
     * @param  array<int, mixed>|null  $rows
     * @return list<array{key: string, title: string, max: float, description: string|null}>
     */
    public static function normalizeRubric(?array $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $criteria = [];
        $seen = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $title = trim((string) ($row['title'] ?? ''));
            $max = is_numeric($row['max'] ?? null) ? (float) $row['max'] : 0.0;

            if ($title === '' || $max <= 0) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));

            if ($key === '' || isset($seen[$key])) {
                $key = 'c'.($index + 1);
            }

            while (isset($seen[$key])) {
                $key .= 'x';
            }

            $seen[$key] = true;

            $criteria[] = [
                'key' => $key,
                'title' => $title,
                'max' => $max,
                'description' => filled($row['description'] ?? null)
                    ? trim((string) $row['description'])
                    : null,
            ];
        }

        return $criteria;
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(ServicePurchase::class, 'offering_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForType(Builder $query, ServiceType|string $type): Builder
    {
        return $query->where('service_type', $type instanceof ServiceType ? $type->value : $type);
    }

    public function scopeForSubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeForLesson(Builder $query, int $lessonId): Builder
    {
        return $query->where('lesson_id', $lessonId);
    }

    /**
     * Offerings a student at the given grade can buy: ones pinned to that grade
     * directly or through their lesson, plus grade-less subject-wide services
     * for subjects the teacher covers that grade on.
     *
     * @param  array<int, int>  $subjectIds  subjects the teacher teaches at the grade
     */
    public function scopeForGrade(Builder $query, int $gradeId, array $subjectIds): Builder
    {
        return $query->where(function (Builder $query) use ($gradeId, $subjectIds) {
            $query->where('grade_id', $gradeId)
                ->orWhereHas('lesson', fn (Builder $lesson) => $lesson->where('grade_id', $gradeId))
                ->orWhere(function (Builder $query) use ($subjectIds) {
                    $query->whereNull('grade_id')
                        ->whereNull('lesson_id')
                        ->whereIn('subject_id', $subjectIds);
                });
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function type(): ServiceType
    {
        return $this->service_type;
    }
}
