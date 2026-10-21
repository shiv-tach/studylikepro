<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TeacherSubject extends Pivot
{
    protected $table = 'teacher_subjects';

    protected function casts(): array
    {
        return [
            'grade_levels' => 'array',
            'grade_rates' => 'array',
            'status' => ApprovalStatus::class,
        ];
    }
}
