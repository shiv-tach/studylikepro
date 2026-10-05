<?php

namespace App\Enums;

enum MeetingStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Preparing',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }

    /**
     * Semantic status colors (independent of the primary accent theme).
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Ready => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Failed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        };
    }

    public function isReady(): bool
    {
        return $this === self::Ready;
    }
}
