<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Conversation;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'student_id' => User::factory()->student(),
            'teacher_profile_id' => TeacherProfile::factory()->approved(),
            'last_message_at' => now(),
        ];
    }
}
