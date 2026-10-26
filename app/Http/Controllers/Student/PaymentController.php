<?php

namespace App\Http\Controllers\Student;

use App\Contracts\PaymentGateway;
use App\Exceptions\UnsupportedCurrencyException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;
use App\Support\CheckoutPaymentOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Open the provider order for a booking hold and go to checkout.
     */
    public function checkout(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('pay', $booking);

        $payment = $this->payments->startCheckout($booking);

        return redirect()->route('student.payments.checkout', $payment);
    }

    /**
     * The checkout page: provider widget in production, demo panel locally.
     */
    public function show(Request $request, Payment $payment): View
    {
        Gate::authorize('view', $payment);

        $payment->load(['booking.teacherProfile.user', 'booking.subject', 'booking.lesson', 'booking.bookingFeePromotion']);

        $timezone = $request->user()->studentProfile?->timezone
            ?? config('studylikepro.default_display_timezone');

        return view('student.checkout.show', [
            'payment' => $payment,
            'booking' => $payment->booking,
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
            ->route('student.bookings.show', $payment->booking_id)
            ->with('status', $payment->status->isSettled() ? 'booking-confirmed' : 'payment-pending');
    }

    /**
     * The widget payload the gateway would hand to its checkout script.
     *
     * A gateway that cannot settle the marketplace currency (the card method
     * is "coming soon") simply has nothing to render — the page still shows the
     * manual methods.
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
