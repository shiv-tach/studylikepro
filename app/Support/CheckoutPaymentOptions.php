<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\ManualPaymentService;

/**
 * Everything the two checkout screens need to draw the payment method picker:
 * which methods the admin offers, the bank accounts a student can pay into, the
 * uploaded slip's state and the hold deadline, all pre-formatted for Blade.
 */
final class CheckoutPaymentOptions
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Payment $payment, ?string $timezone = null): array
    {
        $service = app(ManualPaymentService::class);
        $timezone = $timezone ?: config('studylikepro.default_display_timezone');

        $payable = $payment->payable();
        $selected = $payment->payment_method;

        $methods = [];

        foreach (PaymentMethod::cases() as $method) {
            $enabled = $service->isEnabled($method);
            $requiresSlip = $method->requiresSlip();

            $methods[] = [
                'value' => $method->value,
                'label' => $method->label(),
                'description' => $method->description(),
                'enabled' => $enabled,
                'selected' => $selected === $method,
                'requiresSlip' => $requiresSlip,
                'instructions' => $requiresSlip ? $service->instructionsFor($method) : null,
                'holdLabel' => $requiresSlip ? self::holdLabel($method, $service->holdMinutesFor($method)) : null,
            ];
        }

        $accounts = $service->bankAccounts()
            ->map(fn ($account) => [
                'id' => $account->id,
                'bankName' => $account->bank_name,
                'accountName' => $account->account_name,
                'accountNumber' => $account->account_number,
                'branch' => $account->branch,
                'note' => $account->note,
                'isDefault' => $account->is_default,
            ])
            ->values()
            ->all();

        return [
            'methods' => $methods,
            'accounts' => $accounts,
            'selected' => $selected?->value,
            'selectedLabel' => $selected?->label(),
            'selectedRequiresSlip' => (bool) $selected?->requiresSlip(),
            'isSubmitted' => $payment->status === PaymentStatus::Submitted,
            'isRejected' => $payment->status === PaymentStatus::Rejected,
            'failureReason' => $payment->failure_reason,
            'slipUrl' => filled($payment->slip_path) ? route('student.payments.slip.show', $payment) : null,
            'slipName' => $payment->slip_original_name,
            'bankReference' => $payment->bank_reference,
            'suggestedReference' => 'SLP-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT),
            'submittedAtLabel' => $payment->submitted_at?->copy()->setTimezone($timezone)->format(TimeFormat::DATE_TIME),
            'holdUntilLabel' => $payable?->expires_at?->copy()->setTimezone($timezone)->format(TimeFormat::DATE_TIME),
            'slipMaxKb' => max(1, (int) config('studylikepro.payments.slip_max_kb')),
        ];
    }

    private static function holdLabel(PaymentMethod $method, int $minutes): string
    {
        if ($method === PaymentMethod::BankDeposit) {
            $hours = max(1, (int) round($minutes / 60));

            return trans_choice(
                'Your slot stays reserved for :count hour while you visit the bank.|Your slot stays reserved for :count hours while you visit the bank.',
                $hours,
            );
        }

        return __('The slot stays yours for at least :minutes minutes after you pick this method.', [
            'minutes' => max(15, $minutes),
        ]);
    }
}
