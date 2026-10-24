<?php

namespace App\Models;

use App\Enums\MarkingOrderStatus;
use App\Enums\PurchaseStatus;
use Database\Factories\PaperMarkingOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A paid paper-marking job: the student's uploaded paper, the teacher's
 * marked copy, feedback and marks. Money lives on the linked purchase.
 */
class PaperMarkingOrder extends Model
{
    /** @use HasFactory<PaperMarkingOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'student_id',
        'teacher_profile_id',
        'offering_id',
        'subject_id',
        'status',
        'files',
        'marked_files',
        'feedback',
        'marks',
        'rubric_snapshot',
        'rubric_scores',
        'annotations',
        'draft_saved_at',
        'max_pages',
        'turnaround_hours',
        'page_count',
        'dispute_reason',
        'submitted_at',
        'deadline_at',
        'returned_at',
        'accepted_at',
        'disputed_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarkingOrderStatus::class,
            'files' => 'array',
            'marked_files' => 'array',
            'marks' => 'array',
            'rubric_snapshot' => 'array',
            'rubric_scores' => 'array',
            'annotations' => 'array',
            'draft_saved_at' => 'datetime',
            'max_pages' => 'integer',
            'turnaround_hours' => 'integer',
            'page_count' => 'integer',
            'submitted_at' => 'datetime',
            'deadline_at' => 'datetime',
            'returned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'disputed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ServicePurchase::class, 'purchase_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function offering(): BelongsTo
    {
        return $this->belongsTo(ServiceOffering::class, 'offering_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /**
     * The paid submission deadline: stored once the payment lands, otherwise
     * derived from the payment time and the agreed turnaround.
     */
    public function deadlineAt(): ?Carbon
    {
        if ($this->deadline_at !== null) {
            return $this->deadline_at;
        }

        $paidAt = $this->purchase?->paid_at;

        if ($paidAt === null) {
            return null;
        }

        return $paidAt->copy()->addHours($this->effectiveTurnaroundHours());
    }

    public function effectiveTurnaroundHours(): int
    {
        return $this->turnaround_hours
            ?? (int) config('studylikepro.services.marking_default_turnaround_hours', 72);
    }

    /**
     * The teacher ran past the paid deadline without returning the paper.
     */
    public function isOverdue(): bool
    {
        if (! in_array($this->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true)) {
            return false;
        }

        $deadline = $this->deadlineAt();

        return $deadline !== null && $deadline->isPast();
    }

    public function scopeAwaitingWork(Builder $query): Builder
    {
        return $query->whereIn('status', [
            MarkingOrderStatus::Submitted->value,
            MarkingOrderStatus::InProgress->value,
        ]);
    }

    /**
     * Only orders the student actually paid for. An unpaid submission is
     * invisible to the teacher everywhere: inbox, rail counts and sidebar badge.
     */
    public function scopePaid(Builder $query): Builder
    {
        return $query->whereHas('purchase', fn (Builder $purchase) => $purchase->whereIn('status', [
            PurchaseStatus::Active->value,
            PurchaseStatus::Completed->value,
        ]));
    }

    public function scopeForTeacher(Builder $query, int $profileId): Builder
    {
        return $query->where('teacher_profile_id', $profileId);
    }

    /**
     * The criteria this paper is marked against: the snapshot taken when the
     * marking started, falling back to the offering's live rubric.
     *
     * @return list<array{key: string, title: string, max: float, description: string|null}>
     */
    public function rubricCriteria(): array
    {
        if (is_array($this->rubric_snapshot) && $this->rubric_snapshot !== []) {
            return ServiceOffering::normalizeRubric($this->rubric_snapshot);
        }

        return $this->offering?->rubricCriteria() ?? [];
    }

    /**
     * Saved scores keyed by criterion key.
     *
     * @return array<string, float>
     */
    public function rubricScoreMap(): array
    {
        $map = [];

        foreach (is_array($this->rubric_scores) ? $this->rubric_scores : [] as $row) {
            if (! is_array($row) || ! isset($row['key']) || ! is_numeric($row['score'] ?? null)) {
                continue;
            }

            $map[(string) $row['key']] = (float) $row['score'];
        }

        return $map;
    }

    public function rubricTotal(): float
    {
        return array_sum($this->rubricScoreMap());
    }

    public function rubricMax(): float
    {
        return array_sum(array_map(
            fn (array $criterion) => (float) $criterion['max'],
            $this->rubricCriteria(),
        ));
    }

    public function hasRubric(): bool
    {
        return $this->rubricCriteria() !== [];
    }

    /**
     * Whether the studio has markup to resume from.
     */
    public function hasDraft(): bool
    {
        if ($this->draft_saved_at !== null) {
            return true;
        }

        $pages = is_array($this->annotations) ? ($this->annotations['pages'] ?? null) : null;

        return is_array($pages) && $pages !== [];
    }

    /**
     * A points value the way a teacher reads it: 18, 12.5, never 18.0.
     */
    public static function formatPoints(float|int $points): string
    {
        $formatted = rtrim(rtrim(number_format((float) $points, 2, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-' ? '0' : $formatted;
    }
}
