<?php

namespace Database\Factories;

use App\Models\StudentProfile;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create the user with the admin role.
     */
    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_ADMIN));
    }

    /**
     * Create the user with the teacher role.
     */
    public function teacher(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_TEACHER));
    }

    /**
     * Create the user with the student role.
     */
    public function student(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(User::ROLE_STUDENT));
    }

    /**
     * Create a completed profile matching the user's role. Teachers also have
     * their verification submitted, i.e. they can reach the teacher area.
     */
    public function onboarded(): static
    {
        return $this->afterCreating(function (User $user) {
            if ($user->isStudent()) {
                StudentProfile::factory()->for($user)->create();
            }

            if ($user->isTeacher()) {
                TeacherProfile::factory()->pending()->for($user)->create();
            }
        });
    }
}
