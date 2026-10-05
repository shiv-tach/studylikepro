<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ReviewFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A student's verdict on a completed lesson: one per booking, editable for a
 * week, flaggable by the teacher and hideable by support.
 */
class Review extends Model
{
    /** @use HasFactory<ReviewFactory> */
    use HasFactory;

    /** Reasons a teacher can give when reporting a review. */
    public const FLAG_REASONS = [
        'unfair' => 'It is unfair or inaccurate',
        'abusive' => 'It contains abusive language',
        'spam' => 'It looks like spam',
        'other' => 'Something else',
    ];

    protected $fillable = [
        'booking_id',
        'student_id',
        'teacher_profile_id',
        'rating',
        'comment',
        'edited_at',
        'flagged_at',
        'flagged_by',
        'flag_reason',
        'flag_notes',
        'hidden_at',
        'hidden_by',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'edited_at' => 'datetime',
            'flagged_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function flaggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'flagged_by');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereNull('hidden_at');
    }

    public function scopeFlagged(Builder $query): Builder
    {
        return $query->whereNotNull('flagged_at');
    }

    public function scopeForTeacher(Builder $query, int $teacherProfileId): Builder
    {
        return $query->where('teacher_profile_id', $teacherProfileId);
    }

    public function isVisible(): bool
    {
        return $this->hidden_at === null;
    }

    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /**
     * When the review stops being editable by its author.
     */
    public function editableUntil(): CarbonInterface
    {
        return $this->created_at->copy()->addDays((int) config('studylikepro.reviews.edit_window_days'));
    }

    public function isEditable(): bool
    {
        return $this->isVisible() && now()->lessThanOrEqualTo($this->editableUntil());
    }

    public function flagReasonLabel(): ?string
    {
        return $this->flag_reason === null
            ? null
            : (self::FLAG_REASONS[$this->flag_reason] ?? ucfirst($this->flag_reason));
    }

    /**
     * How the student is shown publicly — first name plus initial.
     */
    public function reviewerName(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->student?->name));

        if (count($parts) < 2) {
            return $this->student?->name ?? 'A student';
        }

        return $parts[0].' '.mb_substr(end($parts), 0, 1).'.';
    }
}
