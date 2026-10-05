<?php

namespace App\Models;

use App\Enums\MessageType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A chat message. Messages are immutable: there is no edit and no delete.
 */
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'sender_id',
        'type',
        'body',
        'attachment_path',
    ];

    protected function casts(): array
    {
        return [
            'type' => MessageType::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isSystem(): bool
    {
        return $this->type->isSystem();
    }

    public function wasSentBy(User $user): bool
    {
        return $this->sender_id !== null && $this->sender_id === $user->id;
    }

    public function attachmentUrl(): ?string
    {
        return $this->attachment_path
            ? Storage::disk('public')->url($this->attachment_path)
            : null;
    }

    /**
     * The payload the polling endpoint hands to the chat client.
     *
     * @return array<string, mixed>
     */
    public function toThreadPayload(User $viewer): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'mine' => $this->wasSentBy($viewer),
            'sender' => $this->isSystem() ? null : ($this->sender?->name ?? 'Someone'),
            'body' => $this->body,
            'attachment_url' => $this->attachmentUrl(),
            'at' => $this->created_at->format('H:i'),
            'day' => $this->created_at->toDateString(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
