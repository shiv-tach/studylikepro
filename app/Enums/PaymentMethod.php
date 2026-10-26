<?php

namespace App\Enums;

/**
 * How the student settled (or is settling) a checkout: a card through the
 * payment gateway, or a manual bank transfer/deposit proven by a slip.
 *
 * The gateway stays behind a "coming soon" switch, so the two manual methods
 * are the live checkout options for now.
 */
enum PaymentMethod: string
{
    case Gateway = 'gateway';
    case OnlineTransfer = 'online_transfer';
    case BankDeposit = 'bank_deposit';

    public function label(): string
    {
        return match ($this) {
            self::Gateway => 'Card payment',
            self::OnlineTransfer => 'Online transfer',
            self::BankDeposit => 'Bank deposit',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Gateway => 'Pay securely with a debit or credit card.',
            self::OnlineTransfer => 'Transfer the exact amount from your bank app, then upload the transfer slip.',
            self::BankDeposit => 'Deposit the exact amount at a bank branch, then upload the deposit slip.',
        };
    }

    /**
     * Manual methods are settled by an admin checking the uploaded proof.
     */
    public function requiresSlip(): bool
    {
        return $this !== self::Gateway;
    }

    /**
     * The platform_settings switch that makes this method available at checkout.
     */
    public function settingKey(): string
    {
        return match ($this) {
            self::Gateway => 'card_payment_enabled',
            self::OnlineTransfer => 'online_transfer_enabled',
            self::BankDeposit => 'bank_deposit_enabled',
        };
    }

    /**
     * The platform_settings key holding the instructions shown with the bank details.
     */
    public function instructionsKey(): string
    {
        return match ($this) {
            self::Gateway => 'card_instructions',
            self::OnlineTransfer => 'online_transfer_instructions',
            self::BankDeposit => 'bank_deposit_instructions',
        };
    }

    /**
     * Badge tone for the method chip (literal Tailwind classes live in the views).
     */
    public function tone(): string
    {
        return match ($this) {
            self::Gateway => 'primary',
            self::OnlineTransfer => 'sky',
            self::BankDeposit => 'emerald',
        };
    }

    /**
     * @return list<self>
     */
    public static function manual(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $method) => $method->requiresSlip(),
        ));
    }
}
