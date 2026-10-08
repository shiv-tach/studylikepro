<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MarkingOrderStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\PaperMarkingOrder;
use App\Models\Refund;
use App\Services\Payments\EarningsService;
use App\Services\Payments\RefundService;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin oversight of paper marking orders, including disputed results.
 */
class PaperMarkingController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly RefundService $refunds,
        private readonly EarningsService $earnings,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(MarkingOrderStatus::cases(), 'value'))],
        ]);

        $orders = PaperMarkingOrder::query()
            ->with(['purchase.student', 'teacherProfile.user', 'subject'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.marking.index', [
            'orders' => $orders,
            'statuses' => MarkingOrderStatus::cases(),
            'selectedStatus' => $filters['status'] ?? null,
        ]);
    }

    public function show(Request $request, PaperMarkingOrder $order): View
    {
        $order->load(['purchase.student', 'purchase.payments.refunds', 'teacherProfile.user', 'subject', 'offering']);

        return view('admin.marking.show', [
            'order' => $order,
        ]);
    }

    /**
     * Resolve a disputed order: side with the teacher (student keeps the result)
     * or side with the student (refund and close).
     */
    public function resolve(Request $request, PaperMarkingOrder $order): RedirectResponse
    {
        abort_unless($order->status === MarkingOrderStatus::Disputed, 404);

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:accept,refund'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $purchase = $order->purchase;

        if ($validated['decision'] === 'accept') {
            $order->forceFill([
                'status' => MarkingOrderStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $purchase->forceFill([
                'status' => PurchaseStatus::Completed,
                'completed_at' => $purchase->completed_at ?? now(),
            ])->save();

            $this->earnings->markEligibleForPurchase($purchase);
        } else {
            $payment = $this->purchases->capturedFor($purchase);

            if ($payment !== null) {
                $this->refunds->refund(
                    $payment,
                    100,
                    Refund::INITIATED_BY_ADMIN,
                    (string) ($validated['note'] ?? __('Dispute resolved in the student\'s favour.')),
                );
            }

            $order->forceFill([
                'status' => MarkingOrderStatus::Resolved,
                'resolved_at' => now(),
            ])->save();

            $purchase->forceFill([
                'status' => PurchaseStatus::Resolved,
                'cancellation_reason' => (string) ($validated['note'] ?? ''),
            ])->save();
        }

        return redirect()
            ->route('admin.marking.show', $order)
            ->with('status', 'marking-resolved');
    }

    /**
     * Refund a marking purchase without resolving a dispute (e.g. SLA breach).
     */
    public function refund(Request $request, PaperMarkingOrder $order): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $payment = $this->purchases->capturedFor($order->purchase);

        if ($payment !== null) {
            $this->refunds->refund(
                $payment,
                100,
                Refund::INITIATED_BY_ADMIN,
                $validated['reason'],
            );
        }

        $order->forceFill([
            'status' => MarkingOrderStatus::Resolved,
            'resolved_at' => now(),
        ])->save();

        $order->purchase->forceFill([
            'status' => PurchaseStatus::Resolved,
            'cancellation_reason' => $validated['reason'],
        ])->save();

        return redirect()
            ->route('admin.marking.show', $order)
            ->with('status', 'marking-refunded');
    }
}
