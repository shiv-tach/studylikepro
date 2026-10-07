<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_TEACHER = 'teacher';

    public const ROLE_STUDENT = 'student';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'theme',
        'notification_preferences',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'theme' => 'array',
            'notification_preferences' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Get the user's notification preferences with defaults for any missing keys.
     */
    public function getNotificationPreferencesAttribute(mixed $value): array
    {
        $defaults = ['email' => true];

        if (is_null($value)) {
            return $defaults;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return $defaults;
        }

        return array_merge($defaults, $value);
    }

    /**
     * Suspended accounts cannot sign in and are signed out on their next request.
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function suspend(string $reason): void
    {
        $this->forceFill([
            'suspended_at' => now(),
            'suspension_reason' => $reason,
        ])->save();
    }

    public function reactivate(): void
    {
        $this->forceFill([
            'suspended_at' => null,
            'suspension_reason' => null,
        ])->save();
    }

    /**
     * Whether Studylikepro may email this user about their lessons and money.
     * In-app notifications are always recorded, whatever this says.
     */
    public function wantsEmailNotifications(): bool
    {
        return (bool) ($this->notification_preferences['email'] ?? true);
    }

    /**
     * Get the user's theme preferences with defaults for any missing keys.
     */
    public function getThemeAttribute(mixed $value): array
    {
        $defaults = [
            'preset' => 'classic',
            'accent' => 'indigo',
            'mode' => 'system',
            'sidebarStyle' => 'flat',
        ];

        if (is_null($value)) {
            return $defaults;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return $defaults;
        }

        return array_merge($defaults, $value);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isTeacher(): bool
    {
        return $this->hasRole(self::ROLE_TEACHER);
    }

    public function isStudent(): bool
    {
        return $this->hasRole(self::ROLE_STUDENT);
    }

    public function studentProfile(): HasOne
    {
        return $this->hasOne(StudentProfile::class);
    }

    public function teacherProfile(): HasOne
    {
        return $this->hasOne(TeacherProfile::class);
    }

    public function interestedSubjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'student_subject_interests')->withTimestamps();
    }

    public function interestedTopics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'student_topic_interests')->withTimestamps();
    }

    public function tutoringRequests(): HasMany
    {
        return $this->hasMany(TutoringRequest::class, 'student_id')->latest();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id')->latest('starts_at');
    }

    public function hasCompletedOnboarding(): bool
    {
        return match (true) {
            $this->isStudent() => (bool) $this->studentProfile?->completed_at,
            $this->isTeacher() => (bool) $this->teacherProfile?->hasCompletedOnboarding(),
            default => true,
        };
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    /**
     * Route name of the dashboard this user should land on.
     */
    public function dashboardRoute(): string
    {
        return match (true) {
            $this->isAdmin() => 'admin.dashboard',
            $this->isTeacher() => 'teacher.dashboard',
            $this->isStudent() => 'student.dashboard',
            default => 'home',
        };
    }
}
