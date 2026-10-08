<?php

namespace App\Models;

use Database\Factories\LessonSummaryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The teacher's personalised write-up for a purchased lesson-summary service.
 * Draft until the teacher delivers it, which completes the purchase.
 */
class LessonSummary extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_DELIVERED = 'delivered';

    /** @use HasFactory<LessonSummaryFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'teacher_profile_id',
        'subject_id',
        'lesson_id',
        'content',
        'status',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ServicePurchase::class, 'purchase_id');
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

    public function scopeForTeacher(Builder $query, int $profileId): Builder
    {
        return $query->where('teacher_profile_id', $profileId);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isDelivered(): bool
    {
        return $this->status === self::STATUS_DELIVERED;
    }
}
