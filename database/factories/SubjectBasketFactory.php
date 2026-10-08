<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use App\Models\SubjectBasket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SubjectBasket>
 */
class SubjectBasketFactory extends Factory
{
    protected $model = SubjectBasket::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'education_level_id' => EducationLevel::factory(),
            'key' => Str::slug($name),
            'name' => Str::title($name),
            'icon' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
