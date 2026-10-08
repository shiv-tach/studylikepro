<?php

namespace App\Http\Controllers\Student;

use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * My Purchases — the student's service catalog wallet: lesson plans with
 * remaining balances, summaries, paper marking orders, discussions and Q&A.
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'string', 'in:active,past'],
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(ServiceType::cases(), 'value'))],
        ]);

        $tab = ($filters['tab'] ?? null) === 'past' ? 'past' : 'active';

        $purchases = $request->user()->servicePurchases()
            ->with(['offering', 'teacherProfile.user', 'subject', 'lesson', 'summary', 'markingOrder'])
            ->when(
                $tab === 'past',
                fn ($query) => $query->whereIn('status', [
                    PurchaseStatus::Completed->value,
                    PurchaseStatus::Cancelled->value,
                    PurchaseStatus::Expired->value,
                    PurchaseStatus::Resolved->value,
                ]),
                fn ($query) => $query->whereIn('status', [
                    PurchaseStatus::PendingPayment->value,
                    PurchaseStatus::Active->value,
                    PurchaseStatus::Disputed->value,
                ]),
            )
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
                'active' => $request->user()->servicePurchases()->whereIn('status', [
                    PurchaseStatus::PendingPayment->value,
                    PurchaseStatus::Active->value,
                    PurchaseStatus::Disputed->value,
                ])->count(),
                'past' => $request->user()->servicePurchases()->whereIn('status', [
                    PurchaseStatus::Completed->value,
                    PurchaseStatus::Cancelled->value,
                    PurchaseStatus::Expired->value,
                    PurchaseStatus::Resolved->value,
                ])->count(),
            ],
        ]);
    }

    public function show(Request $request, ServicePurchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        $purchase->load([
            'offering',
            'teacherProfile.user',
            'subject',
            'lesson',
            'summary',
            'markingOrder',
            'sessions.subject',
            'sessions.lesson',
            'payments',
        ]);

        return view('student.purchases.show', [
            'purchase' => $purchase,
            'capturedPayment' => $this->purchases->capturedFor($purchase),
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
