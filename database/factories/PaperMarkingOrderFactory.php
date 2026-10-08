<?php

namespace Database\Factories;

use App\Enums\MarkingOrderStatus;
use App\Enums\ServiceType;
use App\Models\PaperMarkingOrder;
use App\Models\ServicePurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaperMarkingOrder>
 */
class PaperMarkingOrderFactory extends Factory
{
    protected $model = PaperMarkingOrder::class;

    public function definition(): array
    {
        $purchase = ServicePurchase::factory()->forType(ServiceType::PaperMarking)->create();

        return [
            'purchase_id' => $purchase->id,
            'student_id' => $purchase->student_id,
            'teacher_profile_id' => $purchase->teacher_profile_id,
            'offering_id' => $purchase->offering_id,
            'subject_id' => $purchase->subject_id,
            'status' => MarkingOrderStatus::Submitted,
            'files' => [
                [
                    'path' => 'marking-orders/1/sample.pdf',
                    'original_name' => 'sample.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 1000,
                ],
            ],
            'marked_files' => null,
            'feedback' => null,
            'marks' => null,
            'max_pages' => $purchase->offering?->max_pages,
            'turnaround_hours' => $purchase->offering?->turnaround_hours,
            'page_count' => null,
            'dispute_reason' => null,
            'submitted_at' => now(),
            'deadline_at' => null,
            'returned_at' => null,
            'accepted_at' => null,
            'disputed_at' => null,
            'resolved_at' => null,
        ];
    }

    public function forPurchase(ServicePurchase $purchase): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_id' => $purchase->id,
            'student_id' => $purchase->student_id,
            'teacher_profile_id' => $purchase->teacher_profile_id,
            'offering_id' => $purchase->offering_id,
            'subject_id' => $purchase->subject_id,
            'max_pages' => $purchase->offering?->max_pages,
            'turnaround_hours' => $purchase->offering?->turnaround_hours,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarkingOrderStatus::InProgress,
        ]);
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarkingOrderStatus::Returned,
            'returned_at' => now(),
            'marked_files' => [
                [
                    'path' => 'marking-orders/1/marked/sample.pdf',
                    'original_name' => 'sample-marked.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 1100,
                ],
            ],
            'feedback' => 'Good work overall — check the circled steps.',
        ]);
    }

    public function disputed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => MarkingOrderStatus::Disputed,
            'returned_at' => now()->subDay(),
            'disputed_at' => now(),
            'dispute_reason' => 'The marked copy does not match the paper I submitted.',
        ]);
    }
}
