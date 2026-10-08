<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\BookingStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use Database\Factories\ServicePurchaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A paid service purchase: the money-carrying unit for every service type.
 * Live sessions (lesson, Q&A, paper discussion) reference it from bookings;
 * artifacts (summary, marked paper) reference it from their own tables.
 */
class ServicePurchase extends Model implements Payable
{
    /** @use HasFactory<ServicePurchaseFactory> */
    use HasFactory;

    public const CANCELLED_BY_STUDENT = 'student';

    public const CANCELLED_BY_TEACHER = 'teacher';

    public const CANCELLED_BY_ADMIN = 'admin';

    protected $fillable = [
        'student_id',
        'teacher_profile_id',
        'offering_id',
        'service_type',
        'subject_id',
        'lesson_id',
        'status',
        'price_minor',
        'commission_percent',
        'platform_fee_minor',
        'teacher_payout_minor',
        'booking_fee_minor',
        'booking_fee_discount_minor',
        'booking_fee_promotion_id',
        'currency',
        'allocation_minutes',
        'used_minutes',
        'max_sessions',
        'turnaround_hours',
        'paid_at',
        'expires_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'service_type' => ServiceType::class,
            'price_minor' => 'integer',
            'commission_percent' => 'integer',
            'platform_fee_minor' => 'integer',
            'teacher_payout_minor' => 'integer',
            'booking_fee_minor' => 'integer',
            'booking_fee_discount_minor' => 'integer',
            'allocation_minutes' => 'integer',
            'used_minutes' => 'integer',
            'max_sessions' => 'integer',
            'turnaround_hours' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function offering(): BelongsTo
    {
        return $this->belongsTo(ServiceOffering::class, 'offering_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function earning(): HasOne
    {
        return $this->hasOne(TeacherEarning::class, 'service_purchase_id');
    }

    /**
     * The live sessions of this purchase (lesson, Q&A or discussion bookings).
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(Booking::class)->orderBy('starts_at');
    }

    public function markingOrder(): HasOne
    {
        return $this->hasOne(PaperMarkingOrder::class, 'purchase_id');
    }

    public function summary(): HasOne
    {
        return $this->hasOne(LessonSummary::class, 'purchase_id');
    }

    public function scopeAwaitingPayment(Builder $query): Builder
    {
        return $query->where('status', PurchaseStatus::PendingPayment->value);
    }

    public function scopeConsumable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PurchaseStatus::PendingPayment->value,
            PurchaseStatus::Active->value,
        ]);
    }

    public function type(): ServiceType
    {
        return $this->service_type;
    }

    public function isLessonPlan(): bool
    {
        return $this->service_type === ServiceType::Lesson;
    }

    public function isActive(): bool
    {
        return $this->status === PurchaseStatus::Active;
    }

    /**
     * Session minutes still available on a lesson plan. Sessions that were
     * cancelled or expired return their minutes to the balance.
     */
    public function remainingMinutes(): int
    {
        return max(0, (int) $this->allocation_minutes - $this->usedSessionMinutes());
    }

    /**
     * Minutes consumed by sessions that still count against the plan.
     */
    public function usedSessionMinutes(): int
    {
        return (int) $this->sessions()
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Expired->value])
            ->get()
            ->sum(fn (Booking $booking) => $booking->durationMinutes());
    }

    /**
     * Whether any session of the plan is still scheduled or live.
     */
    public function hasLiveSessions(): bool
    {
        return $this->sessions()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->exists();
    }

    /**
     * Sessions the student may still book (scheduled, not yet finished).
     */
    public function sessionsRemaining(): ?int
    {
        if ($this->max_sessions === null) {
            return null;
        }

        $booked = $this->sessions()
            ->whereNotIn('status', [BookingStatus::Cancelled->value, BookingStatus::Expired->value])
            ->count();

        return max(0, (int) $this->max_sessions - $booked);
    }

    /**
     * The student booking fee after any special-offer discount.
     */
    public function netBookingFeeMinor(): int
    {
        return max(0, $this->booking_fee_minor - $this->booking_fee_discount_minor);
    }

    /**
     * Payable implementation — what checkout charges.
     */
    public function totalMinor(): int
    {
        return $this->price_minor + $this->netBookingFeeMinor();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function studentId(): ?int
    {
        return $this->student_id;
    }

    public function teacherProfileId(): ?int
    {
        return $this->teacher_profile_id;
    }

    public function payableType(): string
    {
        return 'purchase';
    }

    public function payableLabel(): string
    {
        return $this->type()->label().' #'.$this->id;
    }

    /**
     * Scope label for the student-facing display.
     */
    public function scopeLabel(): string
    {
        return $this->lesson?->name ?? $this->subject?->name ?? $this->offering?->title ?? $this->payableLabel();
    }
}
