<?php

namespace App\Enums;

enum ClassificationStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case LowConfidence = 'low_confidence';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Reading your question',
            self::Completed => 'Lesson identified',
            self::LowConfidence => 'Almost — please confirm the lesson',
            self::Failed => 'Pick the lesson yourself',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
            self::Completed => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::LowConfidence => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            self::Failed => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        };
    }

    public function isSettled(): bool
    {
        return $this !== self::Pending;
    }
}
