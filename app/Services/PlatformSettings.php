<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
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
     * @return array<string, array{default: mixed, type: string, group: string, label: string, description: string}>
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
            'booking_fee_minor' => [
                'default' => (int) config('studylikepro.booking_fee_minor'),
                'type' => 'money',
                'group' => 'money',
                'label' => 'Student booking fee',
                'description' => 'Charged to the student on top of the lesson price at checkout. Running special offers can discount or waive it — set 0 to drop it entirely.',
            ],
            'currency' => [
                'default' => (string) config('studylikepro.currency'),
                'type' => 'string',
                'group' => 'money',
                'label' => 'Currency code',
                'description' => 'ISO currency code used for payments and payouts (LKR for the Sri Lankan market).',
            ],
            'hold_ttl_minutes' => [
                'default' => (int) config('studylikepro.requests.hold_ttl_minutes'),
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
            'ai_min_confidence' => [
                'default' => (float) config('studylikepro.ai.min_confidence'),
                'type' => 'float',
                'group' => 'ai',
                'label' => 'AI confidence threshold',
                'description' => 'Below this score the student confirms the subject/topic themselves (0.30–0.95).',
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

        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = PlatformSetting::query()
                ->get()
                ->mapWithKeys(fn (PlatformSetting $setting) => [$setting->key => $setting->typedValue()])
                ->all();

            return array_merge($this->defaults(), $stored);
        });
    }

    public function set(string $key, mixed $value): void
    {
        $definition = self::definitions()[$key] ?? null;

        PlatformSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
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
