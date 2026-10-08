<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\ServicePurchase;
use App\Services\BookingTransitionService;
use App\Services\Payments\RefundService;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin console for service purchases: oversight, refunds and cancellations.
 */
class ServicePurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly RefundService $refunds,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(PurchaseStatus::cases(), 'value'))],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(ServiceType::cases(), 'value'))],
        ]);

        $purchases = ServicePurchase::query()
            ->with(['student', 'teacherProfile.user', 'offering', 'subject', 'lesson'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('service_type', $type))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.purchases.index', [
            'purchases' => $purchases,
            'statuses' => PurchaseStatus::cases(),
            'types' => ServiceType::cases(),
            'selectedStatus' => $filters['status'] ?? null,
            'selectedType' => $filters['type'] ?? null,
        ]);
    }

    public function show(Request $request, ServicePurchase $purchase): View
    {
        $purchase->load([
            'student', 'teacherProfile.user', 'offering', 'subject', 'lesson',
            'sessions', 'payments.refunds', 'summary', 'markingOrder',
        ]);

        return view('admin.purchases.show', [
            'purchase' => $purchase,
            'capturedPayment' => $this->purchases->capturedFor($purchase),
        ]);
    }

    /**
     * Refund the captured payment in full and cancel the purchase.
     */
    public function refund(Request $request, ServicePurchase $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $payment = $this->purchases->capturedFor($purchase);

        if ($payment !== null) {
            $this->refunds->refund(
                $payment,
                100,
                Refund::INITIATED_BY_ADMIN,
                $validated['reason'],
            );
        }

        // Cancel outstanding sessions so slots are released.
        $purchase->sessions()
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::InProgress->value,
            ])
            ->get()
            ->each(fn ($booking) => app(BookingTransitionService::class)->cancel(
                $booking,
                Booking::CANCELLED_BY_ADMIN,
                $validated['reason'],
            ));

        $this->purchases->cancel($purchase, ServicePurchase::CANCELLED_BY_ADMIN, $validated['reason']);

        return redirect()
            ->route('admin.purchases.show', $purchase)
            ->with('status', $payment !== null ? 'purchase-refunded' : 'purchase-cancelled');
    }

    public function cancel(Request $request, ServicePurchase $purchase): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->purchases->cancel(
            $purchase,
            ServicePurchase::CANCELLED_BY_ADMIN,
            (string) ($validated['reason'] ?? ''),
        );

        return redirect()
            ->route('admin.purchases.show', $purchase)
            ->with('status', 'purchase-cancelled');
    }
}
