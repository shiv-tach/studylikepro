<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class TeacherSubject extends Pivot
{
    protected $table = 'teacher_subjects';

    protected function casts(): array
    {
        return [
            'grade_levels' => 'array',
        ];
    }
}
