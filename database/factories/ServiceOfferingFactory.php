<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\ServiceOffering;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceOffering>
 */
class ServiceOfferingFactory extends Factory
{
    protected $model = ServiceOffering::class;

    public function definition(): array
    {
        return [
            'teacher_profile_id' => TeacherProfile::factory(),
            'service_type' => ServiceType::Qna,
            'subject_id' => Subject::factory(),
            'lesson_id' => null,
            'grade_id' => null,
            'title' => fake()->sentence(3),
            'description' => null,
            'price_minor' => fake()->numberBetween(1000, 20000),
            'currency' => 'LKR',
            'allocation_minutes' => null,
            'default_session_minutes' => null,
            'max_sessions' => null,
            'max_pages' => null,
            'turnaround_hours' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Lesson-scoped offerings imply their lesson's grade; keep the stored
     * grade_id consistent for factory-built rows.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (ServiceOffering $offering) {
            if ($offering->grade_id === null && $offering->lesson_id !== null) {
                $offering->forceFill([
                    'grade_id' => $offering->lesson?->grade_id,
                ])->saveQuietly();
            }
        });
    }

    public function forType(ServiceType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'service_type' => $type,
            'allocation_minutes' => $type === ServiceType::Lesson ? 240 : $attributes['allocation_minutes'] ?? null,
            'default_session_minutes' => $type->isLiveSession() ? 60 : ($attributes['default_session_minutes'] ?? null),
            'max_sessions' => $type === ServiceType::Lesson ? 8 : null,
            'max_pages' => $type === ServiceType::PaperMarking ? 20 : null,
            'turnaround_hours' => $type === ServiceType::PaperMarking ? 72 : null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
