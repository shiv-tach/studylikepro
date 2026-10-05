<?php

namespace Database\Factories;

use App\Models\RequestResponse;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestResponse>
 */
class RequestResponseFactory extends Factory
{
    protected $model = RequestResponse::class;

    public function definition(): array
    {
        return [
            'tutoring_request_id' => TutoringRequest::factory(),
            'teacher_profile_id' => TeacherProfile::factory()->approved(),
            'status' => 'pending',
            'message' => fake()->sentence(),
            'starts_at' => now()->addDay()->setTime(18, 0),
            'ends_at' => now()->addDay()->setTime(19, 0),
            'price_minor' => 70000,
            'responded_at' => now(),
        ];
    }
}
