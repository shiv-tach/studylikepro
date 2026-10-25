<?php

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\SmsLenzGateway;
use Illuminate\Support\Facades\Http;

function smsLenzGateway(): SmsLenzGateway
{
    return new SmsLenzGateway('12', 'test-key', 'SMSlenzDEMO', 'https://smslenz.lk/api', 15);
}

test('it posts the documented payload to the send-sms endpoint', function () {
    Http::fake([
        'smslenz.lk/*' => Http::response([
            'success' => true,
            'message' => 'SMS sent successfully',
        ], 200),
    ]);

    smsLenzGateway()->send('0769828874', 'Your code is 123456');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://smslenz.lk/api/send-sms'
            && $request->method() === 'POST'
            && $request['user_id'] === '12'
            && $request['api_key'] === 'test-key'
            && $request['sender_id'] === 'SMSlenzDEMO'
            // SMSLenz wants +9476XXXXXXX, not the stored local digits.
            && $request['contact'] === '+94769828874'
            && $request['message'] === 'Your code is 123456';
    });
});

test('a rejected send throws', function () {
    Http::fake([
        'smslenz.lk/*' => Http::response([
            'success' => false,
            'message' => 'Invalid API key or user ID.',
        ], 401),
    ]);

    expect(fn () => smsLenzGateway()->send('0769828874', 'Your code is 123456'))
        ->toThrow(SmsDeliveryException::class);
});

test('a success payload without the success flag throws', function () {
    Http::fake([
        'smslenz.lk/*' => Http::response(['message' => 'Queued'], 200),
    ]);

    expect(fn () => smsLenzGateway()->send('0769828874', 'Your code is 123456'))
        ->toThrow(SmsDeliveryException::class);
});

test('missing credentials throw without calling the provider', function () {
    Http::fake();

    $gateway = new SmsLenzGateway(null, null, null);

    expect(fn () => $gateway->send('0769828874', 'Your code is 123456'))
        ->toThrow(SmsDeliveryException::class);

    Http::assertNothingSent();
});

test('sendAndReport returns the campaign id and credit balance', function () {
    Http::fake([
        'smslenz.lk/api/send-sms' => Http::response([
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => [
                'campaign_id' => 42,
                'pages' => 1,
                'sms_credit_balance' => '2,513.95',
                'charged_from' => 'main',
            ],
        ]),
    ]);

    $reply = smsLenzGateway()->sendAndReport('0769828874', 'Test message');

    expect($reply['message'])->toBe('SMS sent successfully')
        ->and($reply['data']['campaign_id'])->toBe(42)
        ->and($reply['data']['sms_credit_balance'])->toBe('2,513.95');
});

test('account status sends the credentials and returns the account', function () {
    Http::fake([
        'smslenz.lk/api/account-status' => Http::response([
            'success' => true,
            'message' => 'Account status retrieved successfully',
            'data' => [
                'status' => 'active',
                'sms_credit_balance' => '2,515.72',
                'active_plan' => 'Pay As You Go',
                'sender_id_balances' => [
                    ['sender_id' => 'SMSlenzDEMO', 'allocated_balance' => '1,500.00', 'active' => true],
                ],
            ],
        ]),
    ]);

    $account = smsLenzGateway()->accountStatus();

    expect($account['status'])->toBe('active')
        ->and($account['active_plan'])->toBe('Pay As You Go')
        ->and($account['sender_id_balances'][0]['sender_id'])->toBe('SMSlenzDEMO');

    Http::assertSent(fn ($request) => $request->url() === 'https://smslenz.lk/api/account-status'
        && $request['user_id'] === '12'
        && $request['api_key'] === 'test-key');
});

test('a rejected account status throws with the provider message', function () {
    Http::fake([
        'smslenz.lk/api/account-status' => Http::response([
            'success' => false,
            'message' => 'Invalid API key or user ID.',
        ], 401),
    ]);

    expect(fn () => smsLenzGateway()->accountStatus())
        ->toThrow(SmsDeliveryException::class, 'Invalid API key or user ID.');
});
