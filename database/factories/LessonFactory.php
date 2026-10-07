<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = Lesson::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'subject_id' => Subject::factory(),
            'grade_id' => Grade::factory(),
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'description' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
