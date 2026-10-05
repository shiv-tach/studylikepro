<?php

namespace App\Models;

use Database\Factories\TeacherAvailabilitySlotFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherAvailabilitySlot extends Model
{
    /** @use HasFactory<TeacherAvailabilitySlotFactory> */
    use HasFactory;

    /**
     * Weekly recurring range, expressed in the teacher's timezone.
     * Minutes are counted from midnight; day 0 is Sunday (Carbon convention).
     */
    protected $fillable = [
        'teacher_profile_id',
        'day_of_week',
        'start_minute',
        'end_minute',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
            'start_minute' => 'integer',
            'end_minute' => 'integer',
        ];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function dayLabel(): string
    {
        return config('studylikepro.weekdays')[$this->day_of_week] ?? '';
    }

    public function startLabel(): string
    {
        return self::formatMinute($this->start_minute);
    }

    public function endLabel(): string
    {
        return self::formatMinute($this->end_minute);
    }

    public function durationMinutes(): int
    {
        return max(0, $this->end_minute - $this->start_minute);
    }

    public function overlaps(int $startMinute, int $endMinute): bool
    {
        return $this->start_minute < $endMinute && $this->end_minute > $startMinute;
    }

    public static function formatMinute(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }

    public static function toMinute(string $time): int
    {
        [$hours, $minutes] = array_pad(explode(':', $time), 2, '0');

        return ((int) $hours) * 60 + ((int) $minutes);
    }
}
