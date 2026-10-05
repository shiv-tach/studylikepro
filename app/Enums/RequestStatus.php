<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Open = 'open';
    case Matched = 'matched';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Looking for a teacher',
            self::Matched => 'Teacher found',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
            self::Closed => 'Closed',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-primary/10 text-primary',
            self::Matched => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Cancelled => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Expired => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Closed => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public function isOpenForResponses(): bool
    {
        return $this === self::Open;
    }
}
