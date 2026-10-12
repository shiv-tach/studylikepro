<?php

namespace App\Enums;

/**
 * The grades of its level a subject is taught in. "Junior" covers the primary
 * and junior-secondary years up to Grade 9, "senior" the O/L Grades 10-11, so
 * an admin can offer one subject to either end of a level without a second
 * row. Every subject starts on "all grades". The labels are relative to a
 * level - see labelWithin() - so the same band reads "Grades 6-9" for O/L.
 */
enum GradeBand: string
{
    case All = 'all';
    case Junior = 'junior';
    case Senior = 'senior';

    public function label(): string
    {
        return match ($this) {
            self::All => 'All grades',
            self::Junior => 'Grades 1-9',
            self::Senior => 'Grades 10-11',
        };
    }

    /**
     * The band's label for one level: a band that only covers part of a level
     * is named by the grades it actually spans there, so the junior band of
     * the O/L level (which starts at Grade 6) reads "Grades 6-9" instead of
     * "Grades 1-9". Levels without a numeric range, and bands that miss the
     * level's range altogether, keep the general label.
     */
    public function labelWithin(?int $levelMin, ?int $levelMax): string
    {
        if ($this === self::All || $levelMin === null || $levelMax === null) {
            return $this->label();
        }

        $start = max($this->min() ?? $levelMin, $levelMin);
        $end = min($this->max() ?? $levelMax, $levelMax);

        if ($start > $end) {
            return $this->label();
        }

        return "Grades {$start}-{$end}";
    }

    /**
     * The lowest grade number the band covers, or null for every grade.
     */
    public function min(): ?int
    {
        return match ($this) {
            self::All => null,
            self::Junior => 1,
            self::Senior => 10,
        };
    }

    /**
     * The highest grade number the band covers, or null for every grade.
     */
    public function max(): ?int
    {
        return match ($this) {
            self::All => null,
            self::Junior => 9,
            self::Senior => 11,
        };
    }

    /**
     * Whether a grade number falls inside the band. The "Other" grade has no
     * number and only belongs to the All band.
     */
    public function includes(?int $number): bool
    {
        if ($this === self::All) {
            return true;
        }

        $min = $this->min();
        $max = $this->max();

        return $number !== null
            && $min !== null
            && $max !== null
            && $number >= $min
            && $number <= $max;
    }
}
