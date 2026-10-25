<?php

namespace App\Services;

use App\Enums\BookingFeeType;
use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime-tunable marketplace settings (commission, booking fee, hold TTL,
 * refund policy) stored in `platform_settings`, falling back to the values in
 * config when a row has not been seeded yet.
 */
class PlatformSettings
{
    public const CACHE_KEY = 'studylikepro:platform-settings';

    /**
     * Settings the admin console can edit, with their defaults.
     *
     * `options` belongs to `select` settings, while `min`/`max`/`step` bound the
     * numeric ones (the settings controller and the console both read them).
     * `secret` settings (API keys) are stored encrypted and never sent back to
     * the browser in full.
     *
     * @return array<string, array{default: mixed, type: string, group: string, label: string, description: string, options?: array<string, string>, min?: int|float, max?: int|float, step?: string}>
     */
    public static function definitions(): array
    {
        return [
            'commission_percent' => [
                'default' => (int) config('studylikepro.commission_percent'),
                'type' => 'int',
                'group' => 'money',
                'label' => 'Platform commission (%)',
                'description' => 'Withheld from every lesson payment before the teacher payout.',
            ],
            'booking_fee_type' => [
                'default' => (string) config('studylikepro.booking_fee_type'),
                'type' => 'select',
                'group' => 'money',
                'label' => 'Student booking fee type',
                'description' => 'Charge a fixed amount for every booking, or a percentage of the lesson or service price.',
                'options' => collect(BookingFeeType::cases())
                    ->mapWithKeys(fn (BookingFeeType $type) => [$type->value => $type->label()])
                    ->all(),
            ],
            'booking_fee_minor' => [
                'default' => (int) config('studylikepro.booking_fee_minor'),
                'type' => 'money',
                'group' => 'money',
                'label' => 'Student booking fee (fixed amount)',
                'description' => 'Charged when the fee type is a fixed amount. Running special offers can discount or waive it — set 0 to drop it entirely.',
            ],
            'booking_fee_percent' => [
                'default' => (float) config('studylikepro.booking_fee_percent'),
                'type' => 'float',
                'group' => 'money',
                'label' => 'Student booking fee (%)',
                'description' => 'Used when the fee type is percentage — 10 charges 10% of the lesson or service price on top.',
                'min' => 0,
                'max' => 100,
            ],
            'currency' => [
                'default' => (string) config('studylikepro.currency'),
                'type' => 'string',
                'group' => 'money',
                'label' => 'Currency code',
                'description' => 'ISO currency code used for payments and payouts (LKR for the Sri Lankan market).',
            ],
            'hold_ttl_minutes' => [
                'default' => (int) config('studylikepro.booking.hold_ttl_minutes'),
                'type' => 'int',
                'group' => 'bookings',
                'label' => 'Payment hold (minutes)',
                'description' => 'How long a reserved slot is held while the student pays.',
            ],
            'student_cancel_window_hours' => [
                'default' => (int) config('studylikepro.booking.student_cancel_window_hours'),
                'type' => 'int',
                'group' => 'refunds',
                'label' => 'Free cancellation window (hours)',
                'description' => 'A student cancelling earlier than this window is refunded in full.',
            ],
            'refund_student_percent' => [
                'default' => 100,
                'type' => 'int',
                'group' => 'refunds',
                'label' => 'Student cancellation refund (%)',
                'description' => 'Refund given when a student cancels inside the free window.',
            ],
            'refund_teacher_percent' => [
                'default' => 100,
                'type' => 'int',
                'group' => 'refunds',
                'label' => 'Teacher cancellation refund (%)',
                'description' => 'Refund given when a teacher cancels a paid lesson.',
            ],
            'cancellation_policy_text' => [
                'default' => 'Free cancellation up to 24 hours before the lesson: you get a full refund. Inside 24 hours, contact support — if the teacher cancels you are always refunded in full.',
                'type' => 'text',
                'group' => 'refunds',
                'label' => 'Cancellation policy text',
                'description' => 'Shown on booking pages and receipts.',
            ],
            'plan_expiry_days' => [
                'default' => (int) config('studylikepro.services.plan_expiry_days'),
                'type' => 'int',
                'group' => 'services',
                'label' => 'Lesson plan validity (days)',
                'description' => 'How long a purchased lesson allocation stays usable. Unused time is refunded when it expires.',
            ],
            'max_sessions_per_plan' => [
                'default' => (int) config('studylikepro.services.max_sessions_per_plan'),
                'type' => 'int',
                'group' => 'services',
                'label' => 'Maximum sessions per plan',
                'description' => 'Hard cap on how many sessions a single lesson plan can be split into.',
            ],
            'sms_provider' => [
                'default' => (string) config('studylikepro.sms.provider'),
                'type' => 'select',
                'group' => 'smslenz',
                'label' => 'SMS provider',
                'description' => 'Who delivers text messages. “Demo” writes them to the log instead of sending; SMSLenz needs the credentials below.',
                'options' => [
                    'fake' => 'Demo (log only, nothing is sent)',
                    'smslenz' => 'SMSLenz (live SMS)',
                ],
            ],
            'smslenz_user_id' => [
                'default' => null,
                'type' => 'string',
                'group' => 'smslenz',
                'label' => 'SMSLenz user ID',
                'description' => 'From the SMSLenz dashboard (API settings). Leave blank to use the SMSLENZ_USER_ID value from .env.',
            ],
            'smslenz_api_key' => [
                'default' => null,
                'type' => 'secret',
                'group' => 'smslenz',
                'label' => 'SMSLenz API key',
                'description' => 'Stored encrypted. Leave blank to keep the saved key or the SMSLENZ_API_KEY value from .env.',
            ],
            'smslenz_sender_id' => [
                'default' => null,
                'type' => 'string',
                'group' => 'smslenz',
                'label' => 'SMSLenz sender ID',
                'description' => 'Your approved sender ID, case sensitive. Use SMSlenzDEMO until your own is approved.',
            ],
            'sms_otp_enabled' => [
                'default' => '1',
                'type' => 'select',
                'group' => 'sms',
                'label' => 'Mobile verification (SMS passcode)',
                'description' => 'Require a texted passcode before a new account is created. Turn off to let new accounts register in one step.',
                'options' => [
                    '1' => 'Required',
                    '0' => 'Skip verification',
                ],
            ],
            'sms_otp_length' => [
                'default' => (int) config('studylikepro.sms.otp_length'),
                'type' => 'int',
                'group' => 'sms',
                'label' => 'Passcode length (digits)',
                'description' => 'How many digits the texted code contains. 6 is the usual length.',
                'min' => 4,
                'max' => 8,
            ],
            'sms_otp_ttl_minutes' => [
                'default' => (int) config('studylikepro.sms.otp_ttl_minutes'),
                'type' => 'int',
                'group' => 'sms',
                'label' => 'Passcode lifetime (minutes)',
                'description' => 'A code stops working this long after it was sent.',
                'min' => 1,
                'max' => 60,
            ],
            'sms_otp_resend_seconds' => [
                'default' => (int) config('studylikepro.sms.otp_resend_seconds'),
                'type' => 'int',
                'group' => 'sms',
                'label' => 'Resend cooldown (seconds)',
                'description' => 'How long the visitor must wait before asking for another code.',
                'min' => 0,
                'max' => 600,
            ],
            'sms_otp_max_attempts' => [
                'default' => (int) config('studylikepro.sms.otp_max_attempts'),
                'type' => 'int',
                'group' => 'sms',
                'label' => 'Wrong-code attempts allowed',
                'description' => 'After this many incorrect codes the pending registration is discarded.',
                'min' => 1,
                'max' => 20,
            ],
            'sms_otp_max_resends' => [
                'default' => (int) config('studylikepro.sms.otp_max_resends'),
                'type' => 'int',
                'group' => 'sms',
                'label' => 'Resends per registration',
                'description' => 'How many replacement codes a visitor may request before starting over.',
                'min' => 0,
                'max' => 10,
            ],
        ];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        if (array_key_exists($key, $settings)) {
            return $settings[$key];
        }

        $fallback = $default ?? self::definitions()[$key]['default'] ?? null;

        return $fallback;
    }

    public function int(string $key, ?int $default = null): int
    {
        return (int) $this->get($key, $default);
    }

    public function float(string $key, ?float $default = null): float
    {
        return (float) $this->get($key, $default);
    }

    public function string(string $key, ?string $default = null): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * Render an amount held in minor units for display, e.g. "RS: 1,250.00".
     * The platform symbol is used while the currency setting matches the one the
     * marketplace trades in; anything else prints the ISO code instead.
     */
    public function formatMinor(int $minor): string
    {
        return $this->currencySymbol().' '.number_format($minor / 100, 2);
    }

    public function currencySymbol(): string
    {
        $currency = strtoupper($this->string('currency'));
        $platform = strtoupper((string) config('studylikepro.currency'));

        return $currency === $platform
            ? (string) config('studylikepro.currency_symbol')
            : $currency;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        if (! $this->tableExists()) {
            return $this->defaults();
        }

        // Only the stored rows are cached; the defaults are read from the live
        // config on every call, so changing .env is never frozen into a
        // forever cache.
        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            return PlatformSetting::query()
                ->get()
                ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => $setting->typedValue()])
                ->all();
        });

        return array_merge($this->defaults(), $stored);
    }

    public function set(string $key, mixed $value): void
    {
        $definition = self::definitions()[$key] ?? null;

        $stored = is_bool($value) ? ($value ? '1' : '0') : (string) $value;

        // API keys are encrypted at rest; the model decrypts them on read.
        if (($definition['type'] ?? null) === 'secret' && $stored !== '') {
            $stored = Crypt::encryptString($stored);
        }

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $stored,
                'type' => $definition['type'] ?? 'string',
                'group' => $definition['group'] ?? 'general',
                'label' => $definition['label'] ?? $key,
                'description' => $definition['description'] ?? null,
            ],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return collect(self::definitions())
            ->map(fn (array $definition) => $definition['default'])
            ->all();
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('platform_settings');
    }
}
