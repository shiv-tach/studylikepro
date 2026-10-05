<?php

namespace App\Enums;

enum EarningStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Paid = 'paid';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Eligible => 'Available',
            self::Paid => 'Paid out',
            self::Reversed => 'Reversed',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Eligible => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Paid => 'bg-primary/10 text-primary',
            self::Reversed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        };
    }
}
