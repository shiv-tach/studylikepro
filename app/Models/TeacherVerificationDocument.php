<?php

namespace App\Models;

use Database\Factories\TeacherVerificationDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherVerificationDocument extends Model
{
    /** @use HasFactory<TeacherVerificationDocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'type',
        'original_name',
        'path',
    ];

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function typeLabel(): string
    {
        return config('studylikepro.verification_document_types')[$this->type] ?? ucfirst($this->type);
    }
}
