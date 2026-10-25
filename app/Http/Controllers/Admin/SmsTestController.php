<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\SmsDeliveryException;
use App\Http\Controllers\Controller;
use App\Rules\SriLankanMobile;
use App\Services\ActivityLogger;
use App\Services\Sms\SmsLenzGateway;
use App\Support\PhoneNumber;
use App\Support\SmsLenzConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Sends a sample SMS through the saved SMSLenz credentials so an operator can
 * confirm the account works before mobile verification depends on it. The
 * outcome is flashed back to /admin/settings, never returned as JSON.
 */
class SmsTestController extends Controller
{
    public function store(Request $request, SmsLenzConfig $config, ActivityLogger $activity): RedirectResponse
    {
        if (! $config->isConfigured()) {
            return $this->back([
                'ok' => false,
                'message' => __('Save the :fields before testing the connection.', [
                    'fields' => Arr::join($config->missing(), ', ', ' '.__('and').' '),
                ]),
            ]);
        }

        $validated = $request->validate([
            'mobile' => ['required', new SriLankanMobile],
            // The provider caps a message at 1500 chars; the console keeps the
            // sample short so it stays inside one SMS page.
            'message' => ['required', 'string', 'max:300'],
        ]);

        $mobile = (string) PhoneNumber::normalize($validated['mobile']);
        $credentials = $config->credentials();

        $gateway = new SmsLenzGateway(
            $credentials['user_id'],
            $credentials['api_key'],
            $credentials['sender_id'],
            $credentials['base_url'],
            $credentials['timeout'],
        );

        try {
            $reply = $gateway->sendAndReport($mobile, $validated['message']);
        } catch (SmsDeliveryException $exception) {
            $activity->describe('SMSLenz test message to '.$this->mask($mobile).' failed: '.$exception->getMessage());

            return $this->back([
                'ok' => false,
                'recipient' => PhoneNumber::format($mobile),
                'message' => $exception->getMessage(),
            ]);
        }

        // The plan and balance are a second call. The message was already
        // accepted, so a failed status read must not turn the test red.
        try {
            $account = $gateway->accountStatus();
        } catch (SmsDeliveryException) {
            $account = [];
        }

        $activity->describe('Sent a SMSLenz test message to '.$this->mask($mobile));

        $senderIds = collect($account['sender_id_balances'] ?? [])
            ->pluck('sender_id')
            ->filter()
            ->values()
            ->all();

        $warnings = [];

        if ($senderIds !== [] && ! in_array($credentials['sender_id'], $senderIds, true)) {
            $warnings[] = __('Sender ID :sender is not listed on this SMSLenz account, so messages may be rejected.', [
                'sender' => $credentials['sender_id'],
            ]);
        }

        return $this->back([
            'ok' => true,
            'recipient' => PhoneNumber::format($mobile),
            'provider_message' => (string) ($reply['message'] ?? ''),
            'campaign_id' => data_get($reply, 'data.campaign_id'),
            'pages' => data_get($reply, 'data.pages'),
            'credits' => data_get($reply, 'data.sms_credit_balance') ?? ($account['sms_credit_balance'] ?? null),
            'charged_from' => data_get($reply, 'data.charged_from'),
            'plan' => $account['active_plan'] ?? null,
            'account_status' => $account['status'] ?? null,
            'tested_at' => display_datetime(now()),
            'warnings' => $warnings,
        ]);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function back(array $result): RedirectResponse
    {
        return redirect()
            ->route('admin.settings.edit')
            ->withFragment('smslenz')
            ->with('sms_test', $result);
    }

    /**
     * The audit trail keeps the recipient identifiable but not complete.
     */
    private function mask(string $mobile): string
    {
        return substr($mobile, 0, 3).' *** **'.substr($mobile, -2);
    }
}
