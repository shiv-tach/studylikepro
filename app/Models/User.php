<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'first_name',
        'last_name',
        'email',
        'mobile',
        'mobile_verified_at',
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
            'mobile_verified_at' => 'datetime',
            'password' => 'hashed',
            'theme' => 'array',
            'notification_preferences' => 'array',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Keep the legacy `name` column in step with the split first/last name.
     *
     * `name` stays the canonical full name so search, sorting, receipts, CSV
     * exports and every existing reader keep working. Assigning only `name`
     * (factories, seeders, imports) splits it back into first and last.
     */
    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('first_name') || $user->isDirty('last_name')) {
                $user->name = static::joinName($user->first_name, $user->last_name);

                return;
            }

            if ($user->isDirty('name')) {
                [$user->first_name, $user->last_name] = static::splitName((string) $user->name);
            }
        });
    }

    /**
     * Split a full name on its last space: "Asha Perera" → [Asha, Perera].
     *
     * @return array{0: string, 1: string|null}
     */
    public static function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);

        if ($name === '') {
            return ['', null];
        }

        if (preg_match('/^(.*)\s(\S+)$/u', $name, $matches) === 1) {
            return [trim($matches[1]), $matches[2]];
        }

        return [$name, null];
    }

    /**
     * Build the canonical full name from the two stored parts.
     */
    public static function joinName(?string $firstName, ?string $lastName): string
    {
        $parts = array_filter(
            [trim((string) $firstName), trim((string) $lastName)],
            fn (string $part) => $part !== '',
        );

        return implode(' ', $parts);
    }

    /**
     * Store every mobile number in the canonical local form ("0769828874"),
     * so login lookups, uniqueness checks and admin search all compare the
     * same digits however the visitor typed it.
     */
    protected function mobile(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                if ($value === null || trim($value) === '') {
                    return null;
                }

                // Keep an unusable value as typed rather than silently dropping
                // it; validation happens at the form/request layer.
                return PhoneNumber::normalize($value) ?? trim($value);
            },
        );
    }

    /**
     * The stored number as people read it, e.g. "076 982 88 74".
     */
    public function mobileFormatted(): ?string
    {
        return PhoneNumber::format($this->mobile);
    }

    /**
     * Whether the mobile number was proved with an SMS passcode.
     */
    public function hasVerifiedMobile(): bool
    {
        return $this->mobile_verified_at !== null;
    }

    /**
     * The name the app greets the user with — the first name by default.
     */
    public function displayName(): string
    {
        $firstName = trim((string) $this->first_name);

        return $firstName !== '' ? $firstName : (string) $this->name;
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
     *
     * New users (students and teachers) start on the light palette so their
     * first onboarding screens never open in dark mode.
     */
    public function getThemeAttribute(mixed $value): array
    {
        $defaults = [
            'preset' => 'classic',
            'accent' => 'indigo',
            'mode' => 'light',
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

    public function interestedLessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'student_lesson_interests')->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id')->latest('starts_at');
    }

    public function servicePurchases(): HasMany
    {
        return $this->hasMany(ServicePurchase::class, 'student_id')->latest();
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
