<?php

namespace App\Models;

use Database\Factories\RequestAttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestAttachment extends Model
{
    /** @use HasFactory<RequestAttachmentFactory> */
    use HasFactory;

    protected $fillable = [
        'tutoring_request_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    public function tutoringRequest(): BelongsTo
    {
        return $this->belongsTo(TutoringRequest::class);
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    public function humanSize(): string
    {
        return $this->size >= 1024 * 1024
            ? number_format($this->size / (1024 * 1024), 1).' MB'
            : number_format($this->size / 1024).' KB';
    }
}
