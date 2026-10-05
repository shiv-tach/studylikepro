<?php

namespace App\Models;

use App\Enums\ResponseStatus;
use Database\Factories\RequestResponseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestResponse extends Model
{
    /** @use HasFactory<RequestResponseFactory> */
    use HasFactory;

    protected $fillable = [
        'tutoring_request_id',
        'teacher_profile_id',
        'status',
        'message',
        'starts_at',
        'ends_at',
        'price_minor',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ResponseStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'responded_at' => 'datetime',
            'price_minor' => 'integer',
        ];
    }

    public function tutoringRequest(): BelongsTo
    {
        return $this->belongsTo(TutoringRequest::class);
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function isPending(): bool
    {
        return $this->status === ResponseStatus::Pending;
    }
}
