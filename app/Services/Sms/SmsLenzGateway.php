<?php

namespace App\Services\Sms;

use App\Contracts\SmsGateway;
use App\Exceptions\SmsDeliveryException;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * SMSLenz REST integration (https://smslenz.lk/developers/api).
 *
 * One endpoint is enough for one-time passcodes: POST /api/send-sms with the
 * account credentials, an approved sender ID, the recipient in international
 * form ("+9476XXXXXXX") and the message body. No SDK.
 *
 * `sendAndReport()` returns the decoded response (campaign id, pages, credit
 * balance) for the admin connection test, and `accountStatus()` reads the
 * account's plan and balances from /api/account-status.
 */
class SmsLenzGateway implements SmsGateway
{
    public function __construct(
        private readonly ?string $userId,
        private readonly ?string $apiKey,
        private readonly ?string $senderId,
        private readonly string $baseUrl = 'https://smslenz.lk/api',
        private readonly int $timeout = 15,
    ) {}

    public function name(): string
    {
        return 'smslenz';
    }

    public function send(string $mobile, string $message): void
    {
        $this->sendAndReport($mobile, $message);
    }

    /**
     * Send one message and return SMSLenz's full answer:
     * `{success, message, data: {campaign_id, pages, sms_credit_balance, …}}`.
     *
     * @return array<string, mixed>
     *
     * @throws SmsDeliveryException
     */
    public function sendAndReport(string $mobile, string $message): array
    {
        $contact = PhoneNumber::international($mobile);

        if ($contact === null) {
            throw new SmsDeliveryException('The mobile number is not a valid Sri Lankan number.');
        }

        $this->assertCredentials();

        return $this->request('send-sms', [
            'user_id' => $this->userId,
            'api_key' => $this->apiKey,
            'sender_id' => $this->senderId,
            'contact' => $contact,
            'message' => $message,
        ], 'SMSLenz rejected the message.');
    }

    /**
     * The account's status, plan and remaining credit.
     *
     * @return array<string, mixed>
     *
     * @throws SmsDeliveryException
     */
    public function accountStatus(): array
    {
        if (blank($this->userId) || blank($this->apiKey)) {
            throw new SmsDeliveryException('SMSLenz is not configured. Save the user ID and API key in the admin settings.');
        }

        $body = $this->request('account-status', [
            'user_id' => $this->userId,
            'api_key' => $this->apiKey,
        ], 'SMSLenz rejected the account request.');

        return (array) ($body['data'] ?? []);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SmsDeliveryException
     */
    private function request(string $endpoint, array $payload, string $fallbackMessage): array
    {
        $response = Http::timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->post(rtrim($this->baseUrl, '/').'/'.$endpoint, $payload);

        if ($response->failed() || $response->json('success') !== true) {
            $reason = (string) ($response->json('message') ?? $fallbackMessage);

            // The message body itself must never reach the log in production
            // (it can carry an OTP); the provider's answer is enough to
            // diagnose a rejected send.
            Log::warning('SMSLenz request failed', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'reason' => $reason,
            ]);

            throw new SmsDeliveryException($reason);
        }

        return (array) $response->json();
    }

    /**
     * @throws SmsDeliveryException
     */
    private function assertCredentials(): void
    {
        if (blank($this->userId) || blank($this->apiKey) || blank($this->senderId)) {
            throw new SmsDeliveryException('SMSLenz is not configured. Save the user ID, API key and sender ID in the admin settings.');
        }
    }
}
