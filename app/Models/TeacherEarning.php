<?php

namespace App\Models;

use App\Enums\EarningStatus;
use Database\Factories\TeacherEarningFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ledger row per paid lesson holding the teacher's payout, plus any amount
 * later reversed by a refund.
 */
class TeacherEarning extends Model
{
    /** @use HasFactory<TeacherEarningFactory> */
    use HasFactory;

    protected $fillable = [
        'teacher_profile_id',
        'booking_id',
        'payment_id',
        'payout_id',
        'amount_minor',
        'reversed_minor',
        'currency',
        'status',
        'available_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EarningStatus::class,
            'amount_minor' => 'integer',
            'reversed_minor' => 'integer',
            'available_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', EarningStatus::Pending->value);
    }

    public function scopeEligible(Builder $query): Builder
    {
        return $query->where('status', EarningStatus::Eligible->value);
    }

    /**
     * Rows that count towards a balance: everything not fully reversed.
     */
    public function scopeBalanceable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            EarningStatus::Pending->value,
            EarningStatus::Eligible->value,
            EarningStatus::Paid->value,
        ]);
    }

    public function netMinor(): int
    {
        return max(0, $this->amount_minor - $this->reversed_minor);
    }
}
