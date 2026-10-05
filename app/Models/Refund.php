<?php

namespace App\Models;

use App\Enums\RefundStatus;
use Database\Factories\RefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    /** @use HasFactory<RefundFactory> */
    use HasFactory;

    public const INITIATED_BY_STUDENT = 'student';

    public const INITIATED_BY_TEACHER = 'teacher';

    public const INITIATED_BY_ADMIN = 'admin';

    public const INITIATED_BY_SYSTEM = 'system';

    protected $fillable = [
        'payment_id',
        'booking_id',
        'amount_minor',
        'percent',
        'status',
        'initiated_by',
        'reason',
        'gateway_refund_id',
        'refunded_at',
        'gateway_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount_minor' => 'integer',
            'percent' => 'integer',
            'refunded_at' => 'datetime',
            'gateway_payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function isFull(): bool
    {
        return $this->percent >= 100;
    }

    public function isProcessed(): bool
    {
        return $this->status === RefundStatus::Processed;
    }
}
