<?php

namespace App\Enums;

enum BookingStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case NoShow = 'no_show';
    case Disputed = 'disputed';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Hold expired',
            self::NoShow => 'No-show',
            self::Disputed => 'Disputed',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PendingPayment => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Confirmed => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::InProgress => 'bg-primary/10 text-primary',
            self::Completed => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Cancelled, self::Expired => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::NoShow, self::Disputed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            self::Resolved => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    /**
     * Statuses that still occupy the teacher's slot.
     */
    public function holdsSlot(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed, self::InProgress], true);
    }

    /**
     * Whether the lesson can still move forward.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Expired, self::NoShow, self::Resolved], true);
    }

    public function isLive(): bool
    {
        return in_array($this, [self::Confirmed, self::InProgress], true);
    }
}
