<?php

namespace App\Services;

use App\Enums\MessageType;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Booking-scoped chat. One thread per lesson, shared by the student and the
 * teacher, with system messages mirroring what happens to the booking.
 */
class ConversationService
{
    /** How many messages a thread or a poll returns at a time. */
    public const PAGE_SIZE = 50;

    /**
     * The thread for a booking, created on first use.
     */
    public function forBooking(Booking $booking): Conversation
    {
        return Conversation::query()->firstOrCreate(
            ['booking_id' => $booking->id],
            [
                'student_id' => $booking->student_id,
                'teacher_profile_id' => $booking->teacher_profile_id,
            ],
        );
    }

    /**
     * Write a platform update into the thread (no sender), creating it if needed.
     */
    public function systemMessage(Booking $booking, string $body): Message
    {
        return $this->append($this->forBooking($booking), null, MessageType::System, $body);
    }

    /**
     * Post a chat message, optionally with a photo.
     */
    public function post(Conversation $conversation, User $sender, ?string $body, ?UploadedFile $image = null): Message
    {
        $path = $image?->store('messages', 'public');

        $message = $this->append(
            $conversation,
            $sender->id,
            $image !== null ? MessageType::Image : MessageType::Text,
            $body,
            $path,
        );

        // The sender has by definition seen everything up to their own message.
        $this->markRead($conversation, $sender);

        return $message;
    }

    /**
     * The newest messages after a cursor, plus the unread count before the poll
     * acknowledged the thread.
     *
     * @return array{messages: list<array<string, mixed>>, latest_id: int, unread: int}
     */
    public function poll(Conversation $conversation, User $user, int $after = 0): array
    {
        $unread = $conversation->unreadCountFor($user);

        $messages = $conversation->messages()
            ->where('id', '>', $after)
            ->limit(self::PAGE_SIZE)
            ->get();

        $this->markRead($conversation, $user);

        return [
            'messages' => $messages->map(fn (Message $message) => $message->toThreadPayload($user))->values()->all(),
            'latest_id' => (int) ($messages->last()?->id ?? $after),
            'unread' => $unread,
        ];
    }

    /**
     * Everything in this thread is now read for this user.
     */
    public function markRead(Conversation $conversation, User $user): void
    {
        $latestId = (int) $conversation->messages()->max('id');

        if ($latestId === 0 || $latestId <= $conversation->lastReadIdFor($user)) {
            return;
        }

        $conversation->forceFill([
            $user->isTeacher() ? 'teacher_last_read_message_id' : 'student_last_read_message_id' => $latestId,
        ])->save();
    }

    /**
     * Unread messages across every thread this user takes part in.
     */
    public function unreadCountFor(User $user): int
    {
        $column = $user->isTeacher() ? 'teacher_last_read_message_id' : 'student_last_read_message_id';

        $query = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where(function ($query) use ($user) {
                $query->whereNull('messages.sender_id')
                    ->orWhere('messages.sender_id', '!=', $user->id);
            })
            ->whereRaw('messages.id > COALESCE(conversations.'.$column.', 0)');

        if ($user->isTeacher()) {
            $query->where('conversations.teacher_profile_id', $user->teacherProfile?->id ?? 0);
        } else {
            $query->where('conversations.student_id', $user->id);
        }

        return $query->count();
    }

    private function append(Conversation $conversation, ?int $senderId, MessageType $type, ?string $body, ?string $attachmentPath = null): Message
    {
        $message = $conversation->messages()->create([
            'sender_id' => $senderId,
            'type' => $type,
            'body' => $body,
            'attachment_path' => $attachmentPath,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at])->save();

        return $message;
    }
}
