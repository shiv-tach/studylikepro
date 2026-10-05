<?php

namespace App\Enums;

enum ResponseStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Proposal sent',
            self::Accepted => 'Accepted — hold created',
            self::Declined => 'Declined',
            self::Expired => 'Expired',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-primary/10 text-primary',
            self::Accepted => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Declined => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Expired => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        };
    }
}
