<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Created = 'created';
    case Authorized = 'authorized';
    case Captured = 'captured';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Awaiting payment',
            self::Authorized => 'Authorized',
            self::Captured => 'Paid',
            self::Failed => 'Failed',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Created, self::Authorized => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Captured => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Failed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
            self::PartiallyRefunded, self::Refunded => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public function isSettled(): bool
    {
        return in_array($this, [self::Captured, self::PartiallyRefunded, self::Refunded], true);
    }
}
