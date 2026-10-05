<?php

namespace App\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Processed = 'processed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Refund pending',
            self::Processed => 'Refunded',
            self::Failed => 'Refund failed',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Processed => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Failed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        };
    }
}
