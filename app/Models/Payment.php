<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'student_id',
        'gateway',
        'gateway_order_id',
        'gateway_payment_id',
        'amount_minor',
        'currency',
        'status',
        'method',
        'captured_at',
        'failed_at',
        'refunded_at',
        'failure_reason',
        'gateway_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount_minor' => 'integer',
            'captured_at' => 'datetime',
            'failed_at' => 'datetime',
            'refunded_at' => 'datetime',
            'gateway_payload' => 'array',
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

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function earning(): HasOne
    {
        return $this->hasOne(TeacherEarning::class);
    }

    public function scopeSettled(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PaymentStatus::Captured->value,
            PaymentStatus::PartiallyRefunded->value,
            PaymentStatus::Refunded->value,
        ]);
    }

    /**
     * Payments that never reached a final state and may need reconciling.
     */
    public function scopeStuck(Builder $query, int $minutes = 30): Builder
    {
        return $query->whereIn('status', [PaymentStatus::Created->value, PaymentStatus::Authorized->value])
            ->where('created_at', '<=', now()->subMinutes($minutes));
    }

    public function refundedMinor(): int
    {
        return (int) $this->refunds()->where('status', RefundStatus::Processed->value)->sum('amount_minor');
    }

    public function refundableMinor(): int
    {
        return max(0, $this->amount_minor - $this->refundedMinor());
    }

    public function canBeRefunded(): bool
    {
        return $this->status->isSettled() && $this->refundableMinor() > 0;
    }

    public function isCapture(): bool
    {
        return $this->status === PaymentStatus::Captured;
    }
}
