<?php

namespace App\Http\Controllers\Student;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;
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

        $payment->load(['booking.teacherProfile.user', 'booking.subject', 'booking.topic', 'booking.bookingFeePromotion']);

        return view('student.checkout.show', [
            'payment' => $payment,
            'booking' => $payment->booking,
            'checkout' => $this->gateway->checkoutPayload($payment),
            'localCapture' => $this->localPayload($payment, 'capture'),
            'localFailure' => $this->localPayload($payment, 'failure'),
            'isLocalGateway' => $this->gateway instanceof FakePaymentGateway,
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
