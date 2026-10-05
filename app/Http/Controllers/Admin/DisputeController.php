<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Services\ActivityLogger;
use App\Services\DisputeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The dispute desk: read the lesson, the money and the chat, then close the
 * case with a refund, a warning, a suspension or a dismissal.
 */
class DisputeController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(DisputeStatus::cases(), 'value'))],
            'reason' => ['nullable', 'string', 'max:40'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $disputes = Dispute::query()
            ->with(['booking.student', 'booking.teacherProfile.user', 'raisedBy', 'against'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['reason'] ?? null, fn (Builder $query, string $reason) => $query->where('reason', $reason))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('raisedBy', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('against', fn (Builder $user) => $user->where('name', 'like', "%{$search}%"))
                        ->orWhere('booking_id', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.disputes.index', [
            'disputes' => $disputes,
            'filters' => $filters,
            'statuses' => DisputeStatus::cases(),
            'reasons' => Dispute::REASONS,
            'counts' => [
                'open' => Dispute::query()->open()->count(),
                'resolved' => Dispute::query()->where('status', DisputeStatus::Resolved->value)->count(),
                'dismissed' => Dispute::query()->where('status', DisputeStatus::Dismissed->value)->count(),
            ],
        ]);
    }

    /**
     * Everything support needs to decide: the lesson, its money, the chat and
     * the reporter's own words.
     */
    public function show(Dispute $dispute): View
    {
        $dispute->load([
            'booking.student',
            'booking.teacherProfile.user',
            'booking.subject',
            'booking.topic',
            'booking.payments.refunds',
            'conversation.messages.sender',
            'raisedBy',
            'against',
            'refund',
            'resolvedBy',
        ]);

        return view('admin.disputes.show', [
            'dispute' => $dispute,
            'payment' => $dispute->booking
                ?->payments()
                ->whereIn('status', [
                    PaymentStatus::Captured->value,
                    PaymentStatus::PartiallyRefunded->value,
                    PaymentStatus::Refunded->value,
                ])
                ->latest()
                ->first(),
            'resolutions' => Dispute::RESOLUTIONS,
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    public function review(Dispute $dispute, DisputeService $disputes, ActivityLogger $activity): RedirectResponse
    {
        abort_if($dispute->status->isClosed(), 404);

        $disputes->markReviewed($dispute, auth()->user());

        $activity->describe('Picked up dispute #'.$dispute->id.' for review');

        return back()->with('status', 'dispute-reviewing');
    }

    public function resolve(Request $request, Dispute $dispute, DisputeService $disputes, ActivityLogger $activity): RedirectResponse
    {
        abort_if($dispute->status->isClosed(), 404);

        $validated = $request->validate([
            'resolution' => ['required', 'string', 'in:'.implode(',', array_keys(Dispute::RESOLUTIONS))],
            'percent' => ['nullable', 'integer', 'in:25,50,75'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $dispute = $disputes->resolve(
            $dispute,
            $request->user(),
            $validated['resolution'],
            $validated['percent'] ?? null,
            $validated['notes'] ?? null,
        );

        $activity->describe(
            'Resolved dispute #'.$dispute->id.': '.
            ($dispute->resolutionLabel() ?? 'closed').
            ($dispute->refund_id ? ' (refund #'.$dispute->refund_id.')' : '')
        );

        return redirect()
            ->route('admin.disputes.show', $dispute)
            ->with('status', 'dispute-resolved');
    }
}
