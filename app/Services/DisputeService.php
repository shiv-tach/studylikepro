<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\DisputeRaised;
use App\Notifications\DisputeResolved;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Raising a dispute: something one party wants support to look at. The admin
 * resolution workflow builds on top of these rows.
 */
class DisputeService
{
    public function raise(
        User $raisedBy,
        string $reason,
        ?string $details = null,
        ?Conversation $conversation = null,
        ?Booking $booking = null,
    ): Dispute {
        $booking ??= $conversation?->booking;

        $dispute = Dispute::query()->create([
            'booking_id' => $booking?->id,
            'conversation_id' => $conversation?->id,
            'raised_by' => $raisedBy->id,
            'against_id' => $this->counterpartOf($raisedBy, $booking),
            'reason' => $reason,
            'details' => $details,
            'status' => DisputeStatus::Open,
        ]);

        Notification::send(
            User::query()->role(User::ROLE_ADMIN)->get(),
            new DisputeRaised($dispute),
        );

        return $dispute;
    }

    /**
     * The other side of the lesson — whoever the report is about.
     */
    private function counterpartOf(User $raisedBy, ?Booking $booking): ?int
    {
        if ($booking === null) {
            return null;
        }

        return $booking->student_id === $raisedBy->id
            ? $booking->teacherProfile?->user_id
            : $booking->student_id;
    }

    /**
     * An admin has picked the dispute up.
     */
    public function markReviewed(Dispute $dispute, User $admin): Dispute
    {
        if ($dispute->status === DisputeStatus::Open) {
            $dispute->forceFill([
                'status' => DisputeStatus::Reviewed,
                'resolved_by' => $admin->id,
            ])->save();
        }

        return $dispute;
    }

    /**
     * Close a dispute: refund (full or partial), dismiss it, warn the reported
     * party or suspend their account. Everyone involved hears the outcome.
     */
    public function resolve(
        Dispute $dispute,
        User $admin,
        string $resolution,
        ?int $percent = null,
        ?string $notes = null,
    ): Dispute {
        DB::transaction(function () use ($dispute, $admin, $resolution, $percent, $notes) {
            $refund = null;

            if (in_array($resolution, [Dispute::RESOLUTION_REFUND_FULL, Dispute::RESOLUTION_REFUND_PARTIAL], true)) {
                $payment = app(PaymentService::class)->capturedFor($dispute->booking);

                if ($payment !== null) {
                    $refund = app(RefundService::class)->refund(
                        $payment,
                        $resolution === Dispute::RESOLUTION_REFUND_FULL ? 100 : ($percent ?? 50),
                        Refund::INITIATED_BY_ADMIN,
                        'Dispute #'.$dispute->id.($notes ? ': '.$notes : ''),
                    );
                }
            }

            if ($resolution === Dispute::RESOLUTION_SUSPENDED && $dispute->against !== null) {
                $dispute->against->suspend('Dispute #'.$dispute->id.($notes ? ': '.$notes : ''));
            }

            $dispute->forceFill([
                'status' => $resolution === Dispute::RESOLUTION_DISMISSED
                    ? DisputeStatus::Dismissed
                    : DisputeStatus::Resolved,
                'resolution' => $resolution,
                'refund_id' => $refund?->id,
                'resolved_by' => $admin->id,
                'resolved_at' => now(),
                'resolution_notes' => $notes,
            ])->save();
        });

        $dispute->refresh()->load('booking');

        Notification::send(
            collect([$dispute->raisedBy, $dispute->against])->filter()->unique('id'),
            new DisputeResolved($dispute),
        );

        return $dispute;
    }

    /**
     * A conversation already has a dispute from this user that nobody has
     * closed yet — reporting again would only create noise.
     */
    public function hasOpenFrom(Conversation $conversation, User $raisedBy): bool
    {
        return $conversation->disputes()
            ->where('raised_by', $raisedBy->id)
            ->get()
            ->contains(fn (Dispute $dispute) => ! $dispute->status->isClosed());
    }
}
