<?php

namespace App\Http\Controllers\Student;

use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Services\Payments\PaymentService;
use App\Services\PurchaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * My Purchases — the student's service catalog wallet: lesson plans with
 * remaining balances, paper marking orders, discussions and Q&A.
 */
class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchases,
        private readonly PaymentService $payments,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'string', 'in:active,past'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(ServiceType::cases(), 'value'))],
        ]);

        $tab = ($filters['tab'] ?? null) === 'past' ? 'past' : 'active';

        $purchases = $this->bucket($request->user()->servicePurchases(), $tab)
            ->with(['offering', 'teacherProfile.user', 'subject', 'lesson', 'markingOrder', 'payments'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('service_type', $type))
            ->latest()
            ->paginate(8)
            ->withQueryString();

        return view('student.purchases.index', [
            'purchases' => $purchases,
            'tab' => $tab,
            'types' => ServiceType::cases(),
            'selectedType' => $filters['type'] ?? null,
            'counts' => [
                'active' => $this->bucket($request->user()->servicePurchases(), 'active')->count(),
                'past' => $this->bucket($request->user()->servicePurchases(), 'past')->count(),
                // The one figure the student has to act on, so the overview can
                // separate "still to pay for" from "already usable".
                'pending' => $request->user()->servicePurchases()->holdOpen()->count(),
            ],
        ]);
    }

    /**
     * The statuses a tab lists. A hold that ran out belongs with the finished
     * purchases rather than with the ones the student can still act on, because
     * paying for it is already impossible — the scheduler expires it minutes
     * later, and this page must not offer that dead end in the meantime.
     */
    private function bucket(HasMany $purchases, string $tab): Builder
    {
        $statuses = $tab === 'past'
            ? [
                PurchaseStatus::Completed->value,
                PurchaseStatus::Cancelled->value,
                PurchaseStatus::Expired->value,
                PurchaseStatus::Resolved->value,
            ]
            : [
                PurchaseStatus::Active->value,
                PurchaseStatus::Disputed->value,
            ];

        return $purchases->getQuery()->where(fn (Builder $bucket) => $bucket
            ->whereIn('status', $statuses)
            ->orWhere(fn (Builder $hold) => $tab === 'past' ? $hold->holdLapsed() : $hold->holdOpen()));
    }

    public function show(Request $request, ServicePurchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        $purchase->load([
            'offering',
            'teacherProfile.user',
            'subject',
            'lesson',
            'markingOrder',
            'sessions.subject',
            'sessions.lesson',
            'payments',
        ]);

        return view('student.purchases.show', [
            'purchase' => $purchase,
            'capturedPayment' => $this->purchases->capturedFor($purchase),
            'manualPayment' => $this->payments->latestManualFor($purchase),
        ]);
    }

    /**
     * Buy an offering: create the pending purchase and head to checkout.
     */
    public function store(Request $request, ServiceOffering $offering): RedirectResponse
    {
        Gate::authorize('buy', $offering);

        $purchase = $this->purchases->create($offering, $request->user());

        return redirect()
            ->route('student.purchases.show', $purchase)
            ->with('status', 'purchase-created');
    }

    public function cancel(Request $request, ServicePurchase $purchase): RedirectResponse
    {
        Gate::authorize('cancel', $purchase);

        $this->purchases->cancel(
            $purchase,
            ServicePurchase::CANCELLED_BY_STUDENT,
            (string) $request->input('reason', ''),
        );

        return redirect()
            ->route('student.purchases.show', $purchase)
            ->with('status', 'purchase-cancelled');
    }
}
