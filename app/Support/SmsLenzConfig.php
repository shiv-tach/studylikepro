<?php

namespace App\Support;

use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Log;

/**
 * Where the SMSLenz account comes from.
 *
 * The admin console (/admin/settings) stores the credentials in
 * platform_settings so an operator can swap accounts without a deploy. The
 * SMSLENZ_* values in .env stay as a fallback for installations that were
 * configured before the console existed.
 */
class SmsLenzConfig
{
    /** Every value the settings console and .env may use for `sms_provider`. */
    public const PROVIDERS = ['fake', 'smslenz'];

    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * The active SMS provider: `fake` (log only) or `smslenz`.
     *
     * Anything else (a typo such as "smslentz") falls back to the demo gateway
     * — the app still works, no text is sent — but it is logged, because a
     * silent downgrade looks exactly like a message that never arrived.
     */
    public function provider(): string
    {
        $stored = $this->settings->string('sms_provider');
        $provider = strtolower(trim($stored !== '' ? $stored : (string) config('studylikepro.sms.provider', 'fake')));

        if (! in_array($provider, self::PROVIDERS, true)) {
            Log::warning('Unknown SMS provider; messages are only logged, not sent.', [
                'provider' => $provider,
                'valid' => self::PROVIDERS,
            ]);

            return 'fake';
        }

        return $provider;
    }

    /**
     * The saved account details, with the env values filling any gap.
     *
     * @return array{user_id: ?string, api_key: ?string, sender_id: ?string, base_url: string, timeout: int}
     */
    public function credentials(): array
    {
        return [
            'user_id' => $this->value('smslenz_user_id', config('services.smslenz.user_id')),
            'api_key' => $this->value('smslenz_api_key', config('services.smslenz.api_key')),
            'sender_id' => $this->value('smslenz_sender_id', config('services.smslenz.sender_id')),
            'base_url' => (string) config('services.smslenz.base_url', 'https://smslenz.lk/api'),
            'timeout' => (int) config('services.smslenz.timeout', 15),
        ];
    }

    /**
     * The credential fields still missing, in the order the console lists them.
     *
     * @return list<string>
     */
    public function missing(): array
    {
        $credentials = $this->credentials();

        return array_values(array_filter([
            blank($credentials['user_id']) ? __('SMSLenz user ID') : null,
            blank($credentials['api_key']) ? __('SMSLenz API key') : null,
            blank($credentials['sender_id']) ? __('SMSLenz sender ID') : null,
        ]));
    }

    public function isConfigured(): bool
    {
        return $this->missing() === [];
    }

    /**
     * A saved value wins over the env fallback; blank means "not set".
     */
    private function value(string $key, mixed $fallback): ?string
    {
        $stored = $this->settings->string($key);

        if ($stored !== '') {
            return $stored;
        }

        return blank($fallback) ? null : (string) $fallback;
    }
}
