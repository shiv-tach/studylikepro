<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Not submitted',
            self::Pending => 'Pending review',
            self::Approved => 'Verified',
            self::Rejected => 'Action needed',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Approved => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Rejected => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        };
    }

    /**
     * Documents can only change before review or after a rejection.
     */
    public function allowsDocumentEditing(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /**
     * The teacher has sent the application in for review at least once.
     */
    public function isSubmitted(): bool
    {
        return $this !== self::Draft;
    }
}
