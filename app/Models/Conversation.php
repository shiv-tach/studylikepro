<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The booking-scoped chat between a student and a teacher: one thread per
 * lesson, kept open for questions before and after it.
 */
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'student_id',
        'teacher_profile_id',
        'student_last_read_message_id',
        'teacher_last_read_message_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function teacherProfile(): BelongsTo
    {
        return $this->belongsTo(TeacherProfile::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function isParticipant(User $user): bool
    {
        return $this->student_id === $user->id
            || $this->teacherProfile?->user_id === $user->id;
    }

    /**
     * The name of the person on the other end, from this user's point of view.
     */
    public function counterpartName(User $user): string
    {
        return $this->student_id === $user->id
            ? $this->teacherProfile->user->name
            : $this->student->name;
    }

    /**
     * The id of the last message this user has acknowledged.
     */
    public function lastReadIdFor(User $user): int
    {
        return (int) ($this->student_id === $user->id
            ? $this->student_last_read_message_id
            : $this->teacher_last_read_message_id);
    }

    /**
     * Messages this user has not seen yet. System updates count as well, the
     * user's own messages never do.
     */
    public function unreadCountFor(User $user): int
    {
        return $this->messages()
            ->where('id', '>', $this->lastReadIdFor($user))
            ->where(function ($query) use ($user) {
                $query->whereNull('sender_id')->orWhere('sender_id', '!=', $user->id);
            })
            ->count();
    }

    /**
     * Threads this user takes part in, most recent first.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->when(
            $user->isTeacher(),
            fn (Builder $query) => $query->where('teacher_profile_id', $user->teacherProfile?->id ?? 0),
            fn (Builder $query) => $query->where('student_id', $user->id),
        );
    }

    /**
     * Threads with anything the user has not read yet.
     */
    public function scopeWithUnreadFor(Builder $query, User $user): Builder
    {
        $column = $user->isTeacher() ? 'teacher_last_read_message_id' : 'student_last_read_message_id';

        return $query->whereHas('messages', fn (Builder $messages) => $messages
            ->whereRaw('messages.id > COALESCE(conversations.'.$column.', 0)'));
    }

    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByRaw('COALESCE(last_message_at, created_at) DESC');
    }
}
