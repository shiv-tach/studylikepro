<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        // Real seeded grades use numbers 1-13; factory grades start above
        // that range so they never collide with them.
        return [
            'education_level_id' => EducationLevel::factory(),
            'number' => fake()->unique()->numberBetween(50, 500),
            'label' => fn (array $attributes) => 'Grade '.$attributes['number'],
            'sort_order' => fn (array $attributes) => $attributes['number'],
            'is_active' => true,
        ];
    }

    /**
     * The single "Other" grade of a level.
     */
    public function other(): static
    {
        return $this->state(fn (array $attributes) => [
            'number' => null,
            'label' => 'Other / Adult',
            'sort_order' => 100,
        ]);
    }
}
