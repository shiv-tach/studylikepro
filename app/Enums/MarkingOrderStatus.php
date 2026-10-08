<?php

namespace App\Enums;

enum MarkingOrderStatus: string
{
    case Submitted = 'submitted';
    case InProgress = 'in_progress';
    case Returned = 'returned';
    case Accepted = 'accepted';
    case Disputed = 'disputed';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::InProgress => 'Being marked',
            self::Returned => 'Returned',
            self::Accepted => 'Accepted',
            self::Disputed => 'Disputed',
            self::Resolved => 'Resolved',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Submitted => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::InProgress => 'bg-primary/10 text-primary',
            self::Returned => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
            self::Accepted => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Disputed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            self::Resolved, self::Cancelled => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Accepted, self::Resolved, self::Cancelled], true);
    }
}
