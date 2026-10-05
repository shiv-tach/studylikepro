<?php

namespace Database\Factories;

use App\Models\RequestAttachment;
use App\Models\TutoringRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestAttachment>
 */
class RequestAttachmentFactory extends Factory
{
    protected $model = RequestAttachment::class;

    public function definition(): array
    {
        return [
            'tutoring_request_id' => TutoringRequest::factory(),
            'path' => 'requests/'.fake()->uuid().'.jpg',
            'original_name' => 'question.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(20_000, 900_000),
        ];
    }
}
