<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\MeetingStatus;
use Carbon\CarbonInterface;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    public const CANCELLED_BY_STUDENT = 'student';

    public const CANCELLED_BY_TEACHER = 'teacher';

    public const CANCELLED_BY_ADMIN = 'admin';

    protected $fillable = [
        'student_id',
        'teacher_profile_id',
        'tutoring_request_id',
        'subject_id',
        'topic_id',
        'starts_at',
        'ends_at',
        'status',
        'price_minor',
        'commission_percent',
        'platform_fee_minor',
        'teacher_payout_minor',
        'currency',
        'learner_name',
        'learner_grade',
        'expires_at',
        'confirmed_at',
        'started_at',
        'completed_at',
        'reminder_sent_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'meeting_provider',
        'meeting_url',
        'meeting_status',
        'meeting_external_id',
        'host_meeting_url',
        'meeting_error',
        'meeting_started_at',
        'meeting_ended_at',
        'meeting_recording_url',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meeting_status' => MeetingStatus::class,
            'meeting_started_at' => 'datetime',
            'meeting_ended_at' => 'datetime',
            'price_minor' => 'integer',
            'commission_percent' => 'integer',
            'platform_fee_minor' => 'integer',
            'teacher_payout_minor' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function tutoringRequest(): BelongsTo
    {
        return $this->belongsTo(TutoringRequest::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function earning(): HasOne
    {
        return $this->hasOne(TeacherEarning::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    /**
     * Reports either party raised about this lesson.
     */
    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Bookings that still block the teacher's slot for the given instant.
     */
    public function scopeBlockingSlot(Builder $query): Builder
    {
        return $query->whereIn('status', array_map(
            fn (BookingStatus $status) => $status->value,
            array_filter(BookingStatus::cases(), fn (BookingStatus $status) => $status->holdsSlot())
        ));
    }

    public function scopeOverlapping(Builder $query, CarbonInterface $startsAt, CarbonInterface $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }

    /**
     * Lessons that have not started yet (including live holds).
     */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())
            ->whereNotIn('status', [
                BookingStatus::Cancelled->value,
                BookingStatus::Expired->value,
                BookingStatus::NoShow->value,
            ]);
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where(function (Builder $query) {
            $query->where('starts_at', '<', now())
                ->orWhereIn('status', [
                    BookingStatus::Cancelled->value,
                    BookingStatus::Expired->value,
                    BookingStatus::NoShow->value,
                    BookingStatus::Completed->value,
                ]);
        });
    }

    public function isHold(): bool
    {
        return $this->status === BookingStatus::PendingPayment;
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture() && ! $this->status->isFinal();
    }

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    /**
     * Whether the student may still pull out of this lesson on their own.
     */
    public function isInsideStudentCancelWindow(int $windowHours): bool
    {
        return $this->starts_at->lessThanOrEqualTo(now()->addHours($windowHours));
    }

    public function durationMinutes(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * When the classroom opens — a few minutes early so both sides can settle in.
     */
    public function joinOpensAt(): CarbonInterface
    {
        return $this->starts_at->copy()->subMinutes($this->joinOpensBeforeMinutes());
    }

    /**
     * When the classroom closes, counted from the scheduled end time.
     */
    public function joinClosesAt(): CarbonInterface
    {
        return $this->ends_at->copy()->addMinutes($this->joinClosesAfterMinutes());
    }

    public function joinOpensBeforeMinutes(): int
    {
        return (int) config('studylikepro.meeting.join_opens_before_minutes');
    }

    public function joinClosesAfterMinutes(): int
    {
        return (int) config('studylikepro.meeting.join_closes_after_minutes');
    }

    /**
     * The scheduled join window is open (the room itself may still be provisioning).
     */
    public function isJoinWindowOpen(): bool
    {
        return $this->status->isLive()
            && now()->betweenIncluded($this->joinOpensAt(), $this->joinClosesAt());
    }

    /**
     * There is a live room and it is time to use it.
     */
    public function canJoinNow(): bool
    {
        return $this->isJoinWindowOpen()
            && $this->meeting_status === MeetingStatus::Ready
            && $this->meetingUrlFor() !== null;
    }

    /**
     * The link for a participant: the teacher drives the room, the student joins it.
     */
    public function meetingUrlFor(?User $user = null): ?string
    {
        $hostLink = $this->host_meeting_url ?: $this->meeting_url;

        if ($user !== null && $user->id === $this->teacherProfile?->user_id) {
            return $hostLink;
        }

        return $this->meeting_url ?: $hostLink;
    }

    public function meetingIsReady(): bool
    {
        return $this->meeting_status === MeetingStatus::Ready
            && $this->meeting_url !== null;
    }

    /**
     * Human label of the other party, for lists and notifications.
     */
    public function counterpartName(User $viewer): string
    {
        return $viewer->id === $this->student_id
            ? $this->teacherProfile->user->name
            : $this->student->name;
    }

    /**
     * Lifecycle steps for the booking timeline, in order.
     *
     * @return list<array{key: string, label: string, at: CarbonInterface|null, description: string}>
     */
    public function timeline(): array
    {
        $steps = [
            [
                'key' => 'requested',
                'label' => 'Booked',
                'at' => $this->created_at,
                'description' => 'Slot reserved while payment completes.',
            ],
        ];

        if ($this->status === BookingStatus::Cancelled) {
            $steps[] = [
                'key' => 'cancelled',
                'label' => 'Cancelled',
                'at' => $this->cancelled_at,
                'description' => trim(ucfirst((string) $this->cancelled_by).' cancelled this lesson.'),
            ];
        } elseif ($this->status === BookingStatus::Expired) {
            $steps[] = [
                'key' => 'expired',
                'label' => 'Hold expired',
                'at' => $this->expires_at,
                'description' => 'Payment did not arrive in time, so the slot was released.',
            ];
        } elseif ($this->status === BookingStatus::NoShow) {
            $steps[] = [
                'key' => 'no_show',
                'label' => 'No-show',
                'at' => $this->updated_at,
                'description' => 'The lesson was not held.',
            ];
        } else {
            $steps[] = [
                'key' => 'confirmed',
                'label' => 'Confirmed',
                'at' => $this->confirmed_at,
                'description' => 'Payment received — the lesson is on.',
            ];
            $steps[] = [
                'key' => 'in_progress',
                'label' => 'Lesson started',
                'at' => $this->started_at,
                'description' => 'The live classroom is open.',
            ];
            $steps[] = [
                'key' => 'completed',
                'label' => 'Completed',
                'at' => $this->completed_at,
                'description' => 'Lesson delivered.',
            ];
        }

        return $steps;
    }
}
