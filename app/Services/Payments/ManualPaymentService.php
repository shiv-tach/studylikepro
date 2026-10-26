<?php

namespace App\Services\Payments;

use App\Contracts\Payable;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Events\ManualPaymentRejected;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\ServicePurchase;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manual settlement for checkouts paid by bank transfer or branch deposit:
 * picking the method (and keeping the slot alive long enough), storing the
 * uploaded slip, and letting an admin verify or reject the proof.
 *
 * Verification hands over to PaymentService::capture(), so a confirmed manual
 * payment behaves exactly like a gateway capture (booking confirmed, purchase
 * activated, earning recorded, notifications fired).
 */
class ManualPaymentService
{
    public const SLIP_DISK = 'local';

    public const SLIP_FOLDER = 'payment-slips';

    public function __construct(
        private readonly PaymentService $payments,
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * Whether an admin switch offers this method at checkout.
     */
    public function isEnabled(PaymentMethod $method): bool
    {
        return $this->settings->string($method->settingKey()) === '1';
    }

    /**
     * @return list<PaymentMethod>
     */
    public function enabledMethods(): array
    {
        return array_values(array_filter(
            PaymentMethod::cases(),
            fn (PaymentMethod $method) => $this->isEnabled($method),
        ));
    }

    /**
     * How long the payable stays held after this method was picked.
     *
     * Online transfers are instant, so the floor is the 15-minute promise made
     * to the student. Bank deposits need a branch visit, so they are measured
     * in hours instead of minutes.
     */
    public function holdMinutesFor(?PaymentMethod $method): int
    {
        if ($method === PaymentMethod::BankDeposit) {
            return max(1, $this->settings->int('bank_deposit_hold_hours')) * 60;
        }

        return max(15, $this->settings->int('online_transfer_hold_minutes'));
    }

    /**
     * Instructions shown above the bank details and the slip form.
     */
    public function instructionsFor(PaymentMethod $method): string
    {
        return $this->settings->string($method->instructionsKey());
    }

    /**
     * Active accounts a student can pay into, default first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, BankAccount>
     */
    public function bankAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return BankAccount::query()->active()->ordered()->get();
    }

    /**
     * Pick (or switch) the manual method and extend the hold far enough for the
     * student to complete the transfer and upload the proof.
     */
    public function selectMethod(Payment $payment, PaymentMethod $method): Payment
    {
        if ($method === PaymentMethod::Gateway) {
            throw ValidationException::withMessages([
                'method' => __('Card payments are not available yet.'),
            ]);
        }

        if (! $this->isEnabled($method)) {
            throw ValidationException::withMessages([
                'method' => __('That payment method is not available right now.'),
            ]);
        }

        if ($payment->status->isSettled()) {
            throw ValidationException::withMessages([
                'method' => __('This payment is already settled.'),
            ]);
        }

        if ($payment->status === PaymentStatus::Submitted) {
            throw ValidationException::withMessages([
                'method' => __('Your slip is already with our team — you can replace it below.'),
            ]);
        }

        $payable = $payment->payable();

        $this->assertPayableIsOpen($payable);

        if ($this->bankAccounts()->isEmpty()) {
            throw ValidationException::withMessages([
                'method' => __('Bank details have not been set up yet — please contact support.'),
            ]);
        }

        DB::transaction(function () use ($payment, $method, $payable): void {
            $payment->forceFill([
                'payment_method' => $method,
                'gateway' => 'manual',
                // A manual payment never had a provider order.
                'gateway_order_id' => null,
                'gateway_payment_id' => null,
                'gateway_payload' => null,
                'status' => PaymentStatus::Created,
                'failed_at' => null,
                'failure_reason' => null,
            ])->save();

            $this->extendHold($payable, $this->holdMinutesFor($method));
        });

        return $payment->refresh();
    }

    /**
     * Store the uploaded slip and switch the payment to "awaiting verification".
     * Replacing a slip while pending (or after a rejection) is allowed.
     */
    public function submitSlip(
        Payment $payment,
        UploadedFile $slip,
        ?int $bankAccountId = null,
        ?string $reference = null,
    ): Payment {
        if ($payment->status->isSettled()) {
            throw ValidationException::withMessages([
                'slip' => __('This payment is already settled.'),
            ]);
        }

        $method = $payment->payment_method;

        if (! $method instanceof PaymentMethod || ! $method->requiresSlip()) {
            throw ValidationException::withMessages([
                'slip' => __('Pick a payment method before uploading the slip.'),
            ]);
        }

        if (! $this->isEnabled($method)) {
            throw ValidationException::withMessages([
                'slip' => __('That payment method is not available right now.'),
            ]);
        }

        $payable = $payment->payable();

        $this->assertPayableIsOpen($payable);

        $account = $this->resolveBankAccount($bankAccountId, $payment->bank_account_id);

        $previous = $payment->slip_path;

        $path = Storage::disk(self::SLIP_DISK)->putFileAs(
            self::SLIP_FOLDER.'/'.$payment->id,
            $slip,
            Str::uuid()->toString().'.'.strtolower($slip->getClientOriginalExtension() ?: 'bin'),
        );

        if ($path === false || $path === null) {
            throw ValidationException::withMessages([
                'slip' => __('We could not store that file — please try again.'),
            ]);
        }

        DB::transaction(function () use ($payment, $path, $slip, $account, $reference, $payable, $method): void {
            $payment->forceFill([
                'bank_account_id' => $account->id,
                'slip_path' => $path,
                'slip_original_name' => $slip->getClientOriginalName(),
                'bank_reference' => filled($reference) ? trim((string) $reference) : null,
                'submitted_at' => now(),
                'status' => PaymentStatus::Submitted,
                'failed_at' => null,
                'failure_reason' => null,
            ])->save();

            // The proof is with us now: keep the slot until an admin decides.
            $this->extendHold($payable, $this->holdMinutesFor($method));
        });

        if ($previous && $previous !== $path) {
            Storage::disk(self::SLIP_DISK)->delete($previous);
        }

        return $payment->refresh();
    }

    /**
     * The money arrived: capture the payment through the regular path so the
     * booking/purchase, the earning ledger and the notifications all line up.
     */
    public function verify(Payment $payment, User $admin): Payment
    {
        if ($payment->status === PaymentStatus::Captured) {
            return $payment;
        }

        if ($payment->status !== PaymentStatus::Submitted) {
            throw ValidationException::withMessages([
                'payment' => __('Only a submitted slip can be verified.'),
            ]);
        }

        $method = $payment->payment_method;

        $captured = DB::transaction(function () use ($payment, $admin, $method) {
            $payable = $payment->payable();

            // The admin has confirmed the money, so the hold deadline no longer
            // applies: clear it before capture() re-checks a lapsed hold.
            if ($payable instanceof Booking && $payable->status === BookingStatus::PendingPayment) {
                $payable->forceFill(['expires_at' => null])->save();
            }

            $captured = $this->payments->capture(
                $payment,
                'manual-'.$payment->id,
                $method instanceof PaymentMethod ? $method->value : null,
                null,
                $payment->gateway_payload ?? [],
            );

            $captured->forceFill(['verified_by' => $admin->id])->save();

            return $captured;
        });

        return $captured->refresh();
    }

    /**
     * The slip could not be matched to a deposit. The student may resubmit
     * while the (renewed) hold lasts.
     */
    public function reject(Payment $payment, User $admin, string $reason): Payment
    {
        if ($payment->status !== PaymentStatus::Submitted) {
            throw ValidationException::withMessages([
                'payment' => __('Only a submitted slip can be rejected.'),
            ]);
        }

        $payable = $payment->payable();

        DB::transaction(function () use ($payment, $admin, $reason, $payable): void {
            $payment->forceFill([
                'status' => PaymentStatus::Rejected,
                'failed_at' => now(),
                'failure_reason' => $reason,
                'verified_by' => $admin->id,
            ])->save();

            // Give the student a fresh window to upload a better slip.
            $this->extendHold($payable, $this->holdMinutesFor($payment->payment_method));
        });

        ManualPaymentRejected::dispatch($payment->refresh(), $reason);

        return $payment->refresh();
    }

    private function resolveBankAccount(?int $requestedId, ?int $currentId): BankAccount
    {
        $id = $requestedId ?? $currentId;

        if ($id !== null) {
            $account = BankAccount::query()->active()->find($id);

            if ($account !== null) {
                return $account;
            }
        }

        $fallback = BankAccount::query()->active()->ordered()->first();

        if ($fallback === null) {
            throw ValidationException::withMessages([
                'bank_account_id' => __('Bank details have not been set up yet — please contact support.'),
            ]);
        }

        return $fallback;
    }

    private function assertPayableIsOpen(?Payable $payable): void
    {
        if ($payable === null) {
            throw ValidationException::withMessages([
                'payment' => __('This payment is no longer attached to an order.'),
            ]);
        }

        $expired = match (true) {
            $payable instanceof Booking => $payable->status === BookingStatus::Expired,
            $payable instanceof ServicePurchase => $payable->status === PurchaseStatus::Expired,
            default => false,
        };

        if ($expired) {
            throw ValidationException::withMessages([
                'payment' => __('This hold has expired — start again.'),
            ]);
        }
    }

    /**
     * Keep the slot alive until at least now + the method's window. Never
     * shortens an existing hold.
     */
    private function extendHold(?Payable $payable, int $minutes): void
    {
        if (! $payable instanceof Booking && ! $payable instanceof ServicePurchase) {
            return;
        }

        $until = now()->addMinutes($minutes);

        if ($payable->expires_at === null || $payable->expires_at->lessThan($until)) {
            $payable->forceFill(['expires_at' => $until])->save();
        }
    }
}
