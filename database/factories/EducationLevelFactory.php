<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EducationLevel>
 */
class EducationLevelFactory extends Factory
{
    protected $model = EducationLevel::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'grade_min' => null,
            'grade_max' => null,
            'icon' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
