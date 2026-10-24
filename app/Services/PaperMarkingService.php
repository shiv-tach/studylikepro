<?php

namespace App\Services;

use App\Enums\MarkingOrderStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\PaperMarkingOrder;
use App\Models\ServiceOffering;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the paper-marking lifecycle: submitting the paper against a priced
 * offering, the teacher delivering the marked copy, and the student
 * accepting or disputing the result. Money is handled by the purchase.
 */
class PaperMarkingService
{
    public function __construct(private readonly PurchaseService $purchases) {}

    /**
     * Student submits a paper: creates the pending-payment purchase and the
     * marking order in one transaction. Uploaded files have already been
     * validated by the form request.
     *
     * @param  list<UploadedFile>  $files
     */
    public function submit(ServiceOffering $offering, User $student, array $files, ?int $pageCount): PaperMarkingOrder
    {
        abort_unless(
            $offering->service_type === ServiceType::PaperMarking,
            403,
            __('This offering is not a paper-marking service.'),
        );

        return DB::transaction(function () use ($offering, $student, $files, $pageCount) {
            $purchase = $this->purchases->create($offering, $student);

            $order = PaperMarkingOrder::query()->create([
                'purchase_id' => $purchase->id,
                'student_id' => $student->id,
                'teacher_profile_id' => $offering->teacher_profile_id,
                'offering_id' => $offering->id,
                'subject_id' => $offering->subject_id,
                'status' => MarkingOrderStatus::Submitted,
                'max_pages' => $offering->max_pages,
                'turnaround_hours' => $offering->turnaround_hours,
                'page_count' => $pageCount,
                // Freeze the criteria the student paid for. A later offering
                // edit cannot change how this paper is scored.
                'rubric_snapshot' => $offering->rubricCriteria() ?: null,
                'submitted_at' => now(),
            ]);

            $order->forceFill([
                'files' => collect($files)
                    ->map(fn ($file) => [
                        'path' => $file->store("marking-orders/{$order->id}", 'local'),
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $file->getSize(),
                    ])
                    ->values()
                    ->all(),
            ])->save();

            return $order;
        });
    }

    /**
     * The teacher picks the order up for marking.
     */
    public function startMarking(PaperMarkingOrder $order): void
    {
        if ($order->status !== MarkingOrderStatus::Submitted) {
            return;
        }

        $order->forceFill(['status' => MarkingOrderStatus::InProgress])->save();
    }

    /**
     * Persist the studio's work in progress. Only the keys present in the
     * payload change, so an autosave of the annotations never clears a rubric
     * score the teacher typed a moment earlier.
     *
     * @param  array{annotations?: array<string, mixed>|null, rubric_scores?: list<array{key: string, score: float}>, feedback?: string|null}  $changes
     */
    public function saveDraft(PaperMarkingOrder $order, array $changes): PaperMarkingOrder
    {
        $this->assertPaid($order);

        if (! in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true)) {
            throw ValidationException::withMessages([
                'order' => __('This order has already been returned or closed.'),
            ]);
        }

        return DB::transaction(function () use ($order, $changes) {
            $this->startMarking($order);

            $attributes = ['draft_saved_at' => now()];

            if ($order->rubric_snapshot === null) {
                $criteria = $order->offering?->rubricCriteria() ?? [];

                if ($criteria !== []) {
                    $attributes['rubric_snapshot'] = $criteria;
                }
            }

            if (array_key_exists('annotations', $changes)) {
                $attributes['annotations'] = $changes['annotations'];
            }

            if (array_key_exists('rubric_scores', $changes)) {
                $attributes['rubric_scores'] = $this->normalizeScores($order, $changes['rubric_scores'] ?? []);
            }

            if (array_key_exists('feedback', $changes)) {
                $attributes['feedback'] = $changes['feedback'];
            }

            $order->forceFill($attributes)->save();

            return $order;
        });
    }

    /**
     * The teacher delivers the marked copy. Completes the purchase so the
     * earning becomes eligible, and marks the order returned.
     *
     * @param  list<UploadedFile>  $markedFiles
     * @param  array<int, array{label: string, value: string}>|null  $marks
     * @param  list<array{key: string, score: float}>|null  $rubricScores
     */
    public function mark(
        PaperMarkingOrder $order,
        array $markedFiles,
        ?string $feedback,
        ?array $marks,
        ?array $rubricScores = null,
        ?array $annotations = null,
        bool $replaceAnnotations = false,
    ): PaperMarkingOrder {
        $this->assertPaid($order);

        if (! in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true)) {
            throw ValidationException::withMessages([
                'order' => __('This order has already been returned or closed.'),
            ]);
        }

        return DB::transaction(function () use ($order, $markedFiles, $feedback, $marks, $rubricScores, $annotations, $replaceAnnotations) {
            $attributes = [
                'status' => MarkingOrderStatus::Returned,
                'returned_at' => now(),
                'deadline_at' => null,
                'marked_files' => collect($markedFiles)
                    ->map(fn ($file) => [
                        'path' => $file->store("marking-orders/{$order->id}/marked", 'local'),
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $file->getSize(),
                    ])
                    ->values()
                    ->all(),
                'feedback' => $feedback,
            ];

            if ($order->rubric_snapshot === null) {
                $criteria = $order->offering?->rubricCriteria() ?? [];

                if ($criteria !== []) {
                    $attributes['rubric_snapshot'] = $criteria;
                }
            }

            if ($rubricScores !== null) {
                $normalized = $this->normalizeScores($order, $rubricScores);

                $attributes['rubric_scores'] = $normalized;
                // The student-facing page and the legacy mark rows keep one
                // shape: a criterion label next to "18 / 25".
                $attributes['marks'] = self::rubricMarks($normalized);
            } else {
                $attributes['marks'] = $marks;
            }

            if ($replaceAnnotations) {
                $attributes['annotations'] = $annotations;
            }

            $order->forceFill($attributes)->save();

            $this->purchases->complete($order->purchase);

            return $order;
        });
    }

    /**
     * Clamp each score to its criterion's maximum and reject keys that are not
     * part of this order's snapshot.
     *
     * @param  list<array{key: string, score: float}>  $scores
     * @return list<array{key: string, title: string, score: float, max: float}>
     */
    private function normalizeScores(PaperMarkingOrder $order, array $scores): array
    {
        $criteria = collect($order->rubricCriteria())->keyBy('key');
        $normalized = [];
        $errors = [];

        foreach ($scores as $row) {
            $key = (string) ($row['key'] ?? '');
            $criterion = $criteria->get($key);

            if ($criterion === null) {
                $errors[] = __(':key is not part of this marking rubric.', ['key' => $key]);

                continue;
            }

            $normalized[$key] = [
                'key' => $key,
                'title' => $criterion['title'],
                'score' => min((float) $criterion['max'], max(0, (float) ($row['score'] ?? 0))),
                'max' => (float) $criterion['max'],
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['rubric_scores' => $errors]);
        }

        return array_values($normalized);
    }

    /**
     * @param  list<array{key: string, title: string, score: float, max: float}>  $scores
     * @return list<array{label: string, value: string}>
     */
    private static function rubricMarks(array $scores): array
    {
        return array_map(fn (array $row) => [
            'label' => $row['title'],
            'value' => PaperMarkingOrder::formatPoints($row['score']).' / '.PaperMarkingOrder::formatPoints($row['max']),
        ], $scores);
    }

    /**
     * A paper can only be marked once its purchase is live.
     */
    private function assertPaid(PaperMarkingOrder $order): void
    {
        $purchase = $order->purchase;

        if ($purchase === null || ! in_array($purchase->status, [PurchaseStatus::Active, PurchaseStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'order' => __('This paper can only be marked once the student has paid.'),
            ]);
        }
    }

    /**
     * The student accepts the marked paper as delivered.
     */
    public function accept(PaperMarkingOrder $order): void
    {
        if ($order->status !== MarkingOrderStatus::Returned) {
            return;
        }

        $order->forceFill([
            'status' => MarkingOrderStatus::Accepted,
            'accepted_at' => now(),
        ])->save();
    }

    /**
     * The student disputes the returned paper. The purchase is flagged
     * disputed so its payout stays frozen until an admin resolves it.
     */
    public function dispute(PaperMarkingOrder $order, string $reason): void
    {
        if ($order->status !== MarkingOrderStatus::Returned) {
            return;
        }

        DB::transaction(function () use ($order, $reason) {
            $order->forceFill([
                'status' => MarkingOrderStatus::Disputed,
                'disputed_at' => now(),
                'dispute_reason' => $reason,
            ])->save();

            $order->purchase?->forceFill([
                'status' => PurchaseStatus::Disputed,
            ])->save();
        });
    }

    /**
     * An admin closes a dispute.
     */
    public function resolve(PaperMarkingOrder $order): void
    {
        if ($order->status !== MarkingOrderStatus::Disputed) {
            return;
        }

        $order->forceFill([
            'status' => MarkingOrderStatus::Resolved,
            'resolved_at' => now(),
        ])->save();
    }

    /**
     * Stamp the paid deadline on the order once the payment has landed.
     */
    public function syncDeadline(PaperMarkingOrder $order): void
    {
        if ($order->deadline_at !== null) {
            return;
        }

        $paidAt = $order->purchase?->paid_at;

        if ($paidAt === null) {
            return;
        }

        $order->forceFill([
            'deadline_at' => $paidAt->copy()->addHours($order->effectiveTurnaroundHours()),
        ])->save();
    }
}
