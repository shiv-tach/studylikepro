<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A teacher's request to teach a subject: either one that already exists in
 * the catalog (subject_id set) or a brand-new one they propose (proposed_name
 * set). Both kinds wait for an admin decision.
 */
class SubjectRequest extends Model
{
    protected $fillable = [
        'subject_id',
        'education_level_id',
        'proposed_name',
        'note',
        'status',
        'admin_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'resolved_at' => 'datetime',
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

    public function educationLevel(): BelongsTo
    {
        return $this->belongsTo(EducationLevel::class);
    }

    /**
     * The request proposes a subject that is not in the catalog yet.
     */
    public function isNewSubject(): bool
    {
        return $this->proposed_name !== null;
    }

    /**
     * @param  Builder<SubjectRequest>  $query
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', ApprovalStatus::Pending);
    }
}
