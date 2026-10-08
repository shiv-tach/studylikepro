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
     * The teacher delivers the marked copy. Completes the purchase so the
     * earning becomes eligible, and marks the order returned.
     *
     * @param  list<UploadedFile>  $markedFiles
     * @param  array<int, array{label: string, value: string}>|null  $marks
     */
    public function mark(PaperMarkingOrder $order, array $markedFiles, ?string $feedback, ?array $marks): PaperMarkingOrder
    {
        $purchase = $order->purchase;

        if ($purchase === null || ! in_array($purchase->status, [PurchaseStatus::Active, PurchaseStatus::Completed], true)) {
            throw ValidationException::withMessages([
                'order' => __('This paper can only be marked once the student has paid.'),
            ]);
        }

        if (! in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true)) {
            throw ValidationException::withMessages([
                'order' => __('This order has already been returned or closed.'),
            ]);
        }

        return DB::transaction(function () use ($order, $markedFiles, $feedback, $marks) {
            $order->forceFill([
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
                'marks' => $marks,
            ])->save();

            $this->purchases->complete($order->purchase);

            return $order;
        });
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
