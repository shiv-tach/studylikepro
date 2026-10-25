<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Exceptions\SmsDeliveryException;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Offline provider used for local development and tests. Every message is kept
 * in memory (tests assert the issued code) and mirrored to the log, so a
 * developer running the app locally can finish registration without a SMSLenz
 * account.
 *
 * Tests can inspect `messages()`, read `lastCode()` and force failures with
 * `fail()`. State is reset before every test in tests/TestCase.php.
 */
class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string, sent_at: string}> */
    private static array $messages = [];

    private static bool $failing = false;

    public static function fail(bool $failing = true): void
    {
        self::$failing = $failing;
    }

    /**
     * @return list<array{to: string, message: string, sent_at: string}>
     */
    public static function messages(): array
    {
        return self::$messages;
    }

    /**
     * @return array{to: string, message: string, sent_at: string}|null
     */
    public static function last(): ?array
    {
        return self::$messages === [] ? null : self::$messages[array_key_last(self::$messages)];
    }

    /**
     * The numeric code inside the last message, for tests and local debugging.
     */
    public static function lastCode(): ?string
    {
        $message = self::last()['message'] ?? null;

        return $message !== null && preg_match('/\b(\d{4,8})\b/', $message, $matches) === 1
            ? $matches[1]
            : null;
    }

    public static function reset(): void
    {
        self::$messages = [];
        self::$failing = false;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function send(string $mobile, string $message): void
    {
        if (self::$failing) {
            throw new SmsDeliveryException('The demo SMS provider is unavailable.');
        }

        self::$messages[] = [
            'to' => $mobile,
            'message' => $message,
            'sent_at' => now()->toIso8601String(),
        ];

        // Local development reads the code here; a production install never
        // binds this gateway.
        Log::info('Fake SMS sent', [
            'to' => PhoneNumber::format($mobile),
            'message' => $message,
        ]);
    }
}
