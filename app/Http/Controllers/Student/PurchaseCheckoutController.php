<?php

namespace App\Http\Controllers\Student;

use App\Contracts\PaymentGateway;
use App\Exceptions\UnsupportedCurrencyException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\ServicePurchase;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\PurchaseService;
use App\Support\CheckoutPaymentOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Checkout for service purchases (lesson plans, paper marking, discussions
 * and Q&A). Mirrors the booking checkout flow.
 */
class PurchaseCheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PurchaseService $purchases,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Open the provider order for a pending purchase and go to checkout.
     */
    public function checkout(Request $request, ServicePurchase $purchase): RedirectResponse
    {
        Gate::authorize('pay', $purchase);

        $payment = $this->purchases->startCheckout($purchase);

        return redirect()->route('student.purchases.checkout.show', $payment);
    }

    /**
     * The checkout page: provider widget in production, demo panel locally.
     */
    public function show(Request $request, Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['purchase.offering', 'purchase.teacherProfile.user', 'purchase.subject', 'purchase.lesson']);

        $timezone = $request->user()->studentProfile?->timezone
            ?? config('studylikepro.default_display_timezone');

        return view('student.checkout.purchase', [
            'payment' => $payment,
            'purchase' => $payment->purchase,
            'checkout' => $this->checkoutPayload($payment),
            'localCapture' => $this->localPayload($payment, 'capture'),
            'localFailure' => $this->localPayload($payment, 'failure'),
            'isLocalGateway' => $this->gateway instanceof FakePaymentGateway,
            'manual' => CheckoutPaymentOptions::build($payment, $timezone),
        ]);
    }

    /**
     * Where the provider sends the browser back after the payment attempt.
     */
    public function return(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $payment);

        $this->payments->syncFromGateway($payment);

        $payment->refresh();

        return redirect()
            ->route('student.purchases.show', $payment->service_purchase_id)
            ->with('status', $payment->status->isSettled() ? 'purchase-paid' : 'payment-pending');
    }

    /**
     * The widget payload the gateway would hand to its checkout script.
     *
     * A gateway that cannot settle the marketplace currency (the card method
     * is "coming soon") simply has nothing to render.
     *
     * @return array<string, mixed>
     */
    private function checkoutPayload(Payment $payment): array
    {
        try {
            return $this->gateway->checkoutPayload($payment);
        } catch (UnsupportedCurrencyException) {
            return [];
        }
    }

    /**
     * @return array{payload: string, signature: string}|null
     */
    private function localPayload(Payment $payment, string $kind): ?array
    {
        if (! $this->gateway instanceof FakePaymentGateway) {
            return null;
        }

        [$body, $signature] = $kind === 'capture'
            ? $this->gateway->localCapturePayload($payment)
            : $this->gateway->localFailurePayload($payment);

        return [
            'payload' => (string) json_encode($body),
            'signature' => $signature,
        ];
    }
}
