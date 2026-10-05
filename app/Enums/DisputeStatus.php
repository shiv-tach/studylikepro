<?php

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Reviewed => 'In review',
            self::Resolved => 'Resolved',
            self::Dismissed => 'Dismissed',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Open => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            self::Reviewed => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Resolved => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Dismissed => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolved, self::Dismissed], true);
    }
}
