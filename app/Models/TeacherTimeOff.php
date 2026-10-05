<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\TeacherTimeOffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherTimeOff extends Model
{
    /** @use HasFactory<TeacherTimeOffFactory> */
    use HasFactory;

    protected $table = 'teacher_time_off';

    protected $fillable = [
        'teacher_profile_id',
        'starts_on',
        'ends_on',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    /**
     * Whether the given calendar date (in the teacher's timezone) is excluded.
     */
    public function coversDate(CarbonInterface $date): bool
    {
        return $date->toDateString() >= $this->starts_on->toDateString()
            && $date->toDateString() <= $this->ends_on->toDateString();
    }

    public function dayCount(): int
    {
        return $this->starts_on->diffInDays($this->ends_on) + 1;
    }
}
