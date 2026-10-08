<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\LessonSummary;
use App\Models\ServicePurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonSummary>
 */
class LessonSummaryFactory extends Factory
{
    protected $model = LessonSummary::class;

    public function definition(): array
    {
        $purchase = ServicePurchase::factory()->forType(ServiceType::Summary)->create();

        return [
            'purchase_id' => $purchase->id,
            'teacher_profile_id' => $purchase->teacher_profile_id,
            'subject_id' => $purchase->subject_id,
            'lesson_id' => $purchase->lesson_id,
            'content' => fake()->paragraphs(3, true),
            'status' => LessonSummary::STATUS_DRAFT,
        ];
    }
}
