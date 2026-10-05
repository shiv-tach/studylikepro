<?php

namespace Database\Factories;

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherVerificationDocument>
 */
class TeacherVerificationDocumentFactory extends Factory
{
    protected $model = TeacherVerificationDocument::class;

    public function definition(): array
    {
        return [
            'teacher_profile_id' => TeacherProfile::factory(),
            'type' => 'id_proof',
            'original_name' => 'government-id.pdf',
            'path' => 'verification-documents/'.fake()->numberBetween(1, 1000).'/id-proof.pdf',
        ];
    }
}
