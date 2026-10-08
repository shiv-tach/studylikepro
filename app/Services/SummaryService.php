<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\LessonSummary;
use App\Models\ServicePurchase;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The lesson-summary artifact: draft creation, content saves and delivery,
 * which completes the purchase and unlocks the teacher's earning.
 */
class SummaryService
{
    public function __construct(
        private readonly PurchaseService $purchases,
    ) {}

    /**
     * The draft summary row for a purchased summary service (created on first use).
     */
    public function ensureForPurchase(ServicePurchase $purchase): LessonSummary
    {
        if ($purchase->type() !== ServiceType::Summary) {
            throw ValidationException::withMessages([
                'purchase' => __('Only lesson-summary purchases carry a summary.'),
            ]);
        }

        return LessonSummary::query()->firstOrCreate(
            ['purchase_id' => $purchase->id],
            [
                'teacher_profile_id' => $purchase->teacher_profile_id,
                'subject_id' => $purchase->subject_id,
                'lesson_id' => $purchase->lesson_id,
                'status' => LessonSummary::STATUS_DRAFT,
            ],
        );
    }

    /**
     * Persist the teacher's write-up. Saving never changes the status, so a
     * draft stays a draft until it is explicitly delivered.
     */
    public function save(LessonSummary $summary, string $content): LessonSummary
    {
        $summary->forceFill(['content' => $content])->save();

        return $summary;
    }

    /**
     * Hand the summary to the student: mark it delivered and close out the
     * purchase, which makes the teacher's earning eligible for payout.
     */
    public function deliver(LessonSummary $summary): LessonSummary
    {
        $purchase = $summary->purchase;

        if ($purchase === null || ! $purchase->isActive()) {
            throw ValidationException::withMessages([
                'purchase' => __('This purchase is no longer active, so the summary cannot be delivered.'),
            ]);
        }

        $summary->forceFill([
            'status' => LessonSummary::STATUS_DELIVERED,
            'delivered_at' => now(),
        ])->save();

        $this->purchases->complete($purchase);

        return $summary;
    }

    /**
     * Summary purchases awaiting (or already holding) the teacher's write-up:
     * paid-for summary services, newest first, with the student and scope
     * eager loaded for the console list.
     */
    public function pendingFor(TeacherProfile $profile): Collection
    {
        return ServicePurchase::query()
            ->where('teacher_profile_id', $profile->id)
            ->where('service_type', ServiceType::Summary->value)
            ->whereIn('status', [
                PurchaseStatus::Active->value,
                PurchaseStatus::Completed->value,
            ])
            ->with(['student', 'lesson', 'subject'])
            ->latest()
            ->get();
    }
}
