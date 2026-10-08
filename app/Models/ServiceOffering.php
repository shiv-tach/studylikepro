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
        'title',
        'description',
        'price_minor',
        'currency',
        'allocation_minutes',
        'default_session_minutes',
        'max_sessions',
        'max_pages',
        'turnaround_hours',
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
            'is_active' => 'boolean',
        ];
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

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function type(): ServiceType
    {
        return $this->service_type;
    }
}
