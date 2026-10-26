<?php

namespace App\Http\Controllers\Student;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitPaymentSlipRequest;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\ManualPaymentService;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manual settlement actions on the checkout page: choosing how to pay, storing
 * the slip and streaming it back to the payer.
 */
class ManualPaymentController extends Controller
{
    public function __construct(
        private readonly ManualPaymentService $manual,
        private readonly PaymentService $payments,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * Pick (or switch) the payment method. Manual methods extend the hold.
     */
    public function selectMethod(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('selectMethod', $payment);

        $validated = $request->validate([
            'method' => [
                'required',
                'string',
                Rule::in(array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::cases())),
            ],
        ]);

        $method = PaymentMethod::from($validated['method']);

        if ($method === PaymentMethod::Gateway) {
            // The card checkout is a "coming soon" card unless the admin turned
            // it on, or the offline gateway is running locally/tests.
            $available = $this->manual->isEnabled(PaymentMethod::Gateway)
                || $this->gateway instanceof FakePaymentGateway;

            if (! $available) {
                throw ValidationException::withMessages([
                    'method' => __('Card payments are coming soon — pick another method for now.'),
                ]);
            }

            $this->payments->resetToGateway($payment);
        } else {
            $this->manual->selectMethod($payment, $method);
        }

        return back()->with('status', 'payment-method-selected');
    }

    /**
     * Store the uploaded proof and hand the payment to the admin queue.
     */
    public function storeSlip(SubmitPaymentSlipRequest $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('uploadSlip', $payment);

        $this->manual->submitSlip(
            $payment,
            $request->file('slip'),
            $request->integer('bank_account_id') ?: null,
            $request->validated('reference'),
        );

        return back()->with('status', 'payment-slip-submitted');
    }

    /**
     * Stream the uploaded slip to its owner.
     */
    public function showSlip(Payment $payment): StreamedResponse
    {
        Gate::authorize('viewSlip', $payment);

        abort_unless(
            filled($payment->slip_path)
                && Storage::disk(ManualPaymentService::SLIP_DISK)->exists($payment->slip_path),
            404,
        );

        return Storage::disk(ManualPaymentService::SLIP_DISK)->response(
            $payment->slip_path,
            $payment->slip_original_name ?: 'payment-slip',
        );
    }
}
