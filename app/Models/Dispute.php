<?php

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something one party wants a human to look at — raised from the chat report
 * action or by support. Admins triage these in the moderation queue.
 */
class Dispute extends Model
{
    /** @use HasFactory<DisputeFactory> */
    use HasFactory;

    public const REASON_NO_SHOW = 'no_show';

    public const REASON_PAYMENT = 'payment';

    public const REASON_BEHAVIOUR = 'behaviour';

    public const REASON_QUALITY = 'quality';

    public const REASON_OTHER = 'other';

    /** How support closed a dispute. */
    public const RESOLUTION_REFUND_FULL = 'refund_full';

    public const RESOLUTION_REFUND_PARTIAL = 'refund_partial';

    public const RESOLUTION_DISMISSED = 'dismissed';

    public const RESOLUTION_WARNED = 'warned';

    public const RESOLUTION_SUSPENDED = 'suspended';

    /**
     * The ways an admin can close a dispute.
     *
     * @var array<string, string>
     */
    public const RESOLUTIONS = [
        self::RESOLUTION_REFUND_FULL => 'Full refund to the student',
        self::RESOLUTION_REFUND_PARTIAL => 'Partial refund to the student',
        self::RESOLUTION_DISMISSED => 'No action — dismiss the report',
        self::RESOLUTION_WARNED => 'Warn the reported party',
        self::RESOLUTION_SUSPENDED => 'Suspend the reported account',
    ];

    /**
     * The reasons the chat report form offers.
     *
     * @var array<string, string>
     */
    public const REASONS = [
        self::REASON_NO_SHOW => 'The other side did not show up',
        self::REASON_PAYMENT => 'A payment or refund problem',
        self::REASON_BEHAVIOUR => 'Inappropriate behaviour',
        self::REASON_QUALITY => 'The lesson did not match the description',
        self::REASON_OTHER => 'Something else',
    ];

    protected $fillable = [
        'booking_id',
        'conversation_id',
        'raised_by',
        'against_id',
        'reason',
        'details',
        'status',
        'resolution',
        'refund_id',
        'resolved_by',
        'resolved_at',
        'resolution_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function against(): BelongsTo
    {
        return $this->belongsTo(User::class, 'against_id');
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Disputes support has not closed yet.
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [DisputeStatus::Open->value, DisputeStatus::Reviewed->value]);
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function resolutionLabel(): ?string
    {
        return $this->resolution === null
            ? null
            : (self::RESOLUTIONS[$this->resolution] ?? ucfirst(str_replace('_', ' ', $this->resolution)));
    }
}
