<?php

use App\Contracts\SmsGateway;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\PlatformSettings;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsLenzGateway;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| SMSLenz setup console (/admin/settings)
|--------------------------------------------------------------------------
|
| Credentials are saved in platform_settings (the API key encrypted) so an
| operator can swap accounts without a deploy. The connection test sends a real
| message through the saved account and flashes the provider's answer back to
| the console.
|
*/

function adminSmsLenzUser(): User
{
    return User::factory()->admin()->create();
}

function seedSmsLenzAccount(string $apiKey = 'super-secret-key'): PlatformSettings
{
    $settings = app(PlatformSettings::class);
    $settings->set('sms_provider', 'smslenz');
    $settings->set('smslenz_user_id', '12');
    $settings->set('smslenz_api_key', $apiKey);
    $settings->set('smslenz_sender_id', 'SMSlenzDEMO');

    return $settings;
}

it('shows the SMSLenz group on the settings console without the saved API key', function () {
    seedSmsLenzAccount();

    $response = $this->actingAs(adminSmsLenzUser())->get(route('admin.settings.edit'))->assertOk();

    $config = adminSettingsConfig($response);
    $groups = collect($config['groups'])->keyBy('key');

    expect($groups->has('smslenz'))->toBeTrue()
        ->and($groups['smslenz']['label'])->toBe('SMSLenz API')
        ->and(collect($groups['smslenz']['items'])->pluck('key')->all())
        ->toBe(['sms_provider', 'smslenz_user_id', 'smslenz_api_key', 'smslenz_sender_id'])
        ->and($config['sms']['provider'])->toBe('smslenz')
        ->and($config['sms']['providerLabel'])->toBe('SMSLenz')
        ->and($config['sms']['senderId'])->toBe('SMSlenzDEMO')
        ->and($config['sms']['configured'])->toBeTrue()
        ->and($config['urls']['smsTest'])->toBe(route('admin.settings.sms-test'));

    // The key never travels back to the browser: the field is empty and only
    // says that a value is stored.
    $apiKey = collect($groups['smslenz']['items'])->firstWhere('key', 'smslenz_api_key');

    expect($apiKey['type'])->toBe('secret')
        ->and($apiKey['inputType'])->toBe('password')
        ->and($apiKey['value'])->toBe('')
        ->and($apiKey['hasValue'])->toBeTrue();

    $response->assertDontSee('super-secret-key');

    // The noscript twin still offers the test form.
    $response->assertSee('Send test SMS');
});

it('stores the API key encrypted and reads it back', function () {
    $settings = seedSmsLenzAccount();

    $stored = PlatformSetting::query()->where('key', 'smslenz_api_key')->value('value');

    expect($stored)->not->toBe('super-secret-key')
        ->and($stored)->not->toBeNull()
        ->and($settings->get('smslenz_api_key'))->toBe('super-secret-key')
        ->and($settings->string('smslenz_api_key'))->toBe('super-secret-key');
});

it('keeps the saved API key when the settings form submits it blank', function () {
    $settings = seedSmsLenzAccount();

    $this->actingAs(adminSmsLenzUser())
        ->put(route('admin.settings.update'), [
            'sms_provider' => 'smslenz',
            'smslenz_user_id' => '12',
            'smslenz_api_key' => '',
            'smslenz_sender_id' => 'SMSlenzDEMO',
        ])
        ->assertRedirect(route('admin.settings.edit'));

    expect($settings->get('smslenz_api_key'))->toBe('super-secret-key');
});

it('sends a sample SMS and reports the provider answer', function () {
    seedSmsLenzAccount();

    Http::fake([
        'smslenz.lk/api/send-sms' => Http::response([
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => [
                'campaign_id' => 42,
                'pages' => 1,
                'sms_credit_balance' => '2,513.95',
                'charged_from' => 'main',
                'recipient_number' => '94769828874',
            ],
        ]),
        'smslenz.lk/api/account-status' => Http::response([
            'success' => true,
            'message' => 'Account status retrieved successfully',
            'data' => [
                'status' => 'active',
                'sms_credit_balance' => '2,513.95',
                'active_plan' => 'Pay As You Go',
                'sender_id_balances' => [
                    ['sender_id' => 'SMSlenzDEMO', 'allocated_balance' => '1,500.00', 'active' => true],
                ],
            ],
        ]),
    ]);

    $this->actingAs(adminSmsLenzUser())
        ->from(route('admin.settings.edit'))
        ->post(route('admin.settings.sms-test'), [
            'mobile' => '076 982 88 74',
            'message' => 'Hello from the admin console',
        ])
        ->assertRedirect(route('admin.settings.edit').'#smslenz')
        ->assertSessionHas('sms_test', fn (array $result) => $result['ok'] === true
            && $result['campaign_id'] === 42
            && $result['credits'] === '2,513.95'
            && $result['plan'] === 'Pay As You Go'
            && $result['recipient'] === '076 982 88 74'
            && $result['warnings'] === []);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->url() === 'https://smslenz.lk/api/send-sms'
        && $request['user_id'] === '12'
        && $request['api_key'] === 'super-secret-key'
        && $request['sender_id'] === 'SMSlenzDEMO'
        // SMSLenz wants +9476XXXXXXX, not the local digits typed in the console.
        && $request['contact'] === '+94769828874'
        && $request['message'] === 'Hello from the admin console');

    Http::assertSent(fn ($request) => $request->url() === 'https://smslenz.lk/api/account-status');
});

it('surfaces the provider error instead of failing the request', function () {
    seedSmsLenzAccount();

    Http::fake([
        'smslenz.lk/api/send-sms' => Http::response([
            'success' => false,
            'message' => 'Invalid API key or user ID.',
        ], 401),
    ]);

    $this->actingAs(adminSmsLenzUser())
        ->post(route('admin.settings.sms-test'), [
            'mobile' => '0769828874',
            'message' => 'Connection check',
        ])
        ->assertRedirect(route('admin.settings.edit').'#smslenz')
        ->assertSessionHas('sms_test', fn (array $result) => $result['ok'] === false
            && $result['message'] === 'Invalid API key or user ID.');
});

it('will not call the provider before the credentials are saved', function () {
    Http::fake();

    $this->actingAs(adminSmsLenzUser())
        ->post(route('admin.settings.sms-test'), [
            'mobile' => '0769828874',
            'message' => 'Connection check',
        ])
        ->assertRedirect(route('admin.settings.edit').'#smslenz')
        ->assertSessionHas('sms_test', fn (array $result) => $result['ok'] === false
            && str_contains($result['message'], 'SMSLenz user ID'));

    Http::assertNothingSent();
});

it('rejects an invalid mobile number before sending', function () {
    seedSmsLenzAccount();
    Http::fake();

    $this->actingAs(adminSmsLenzUser())
        ->from(route('admin.settings.edit'))
        ->post(route('admin.settings.sms-test'), [
            'mobile' => '12345',
            'message' => 'Connection check',
        ])
        ->assertSessionHasErrors('mobile');

    Http::assertNothingSent();
});

it('binds the live gateway from the saved provider setting', function () {
    seedSmsLenzAccount();

    expect(app(SmsGateway::class))->toBeInstanceOf(SmsLenzGateway::class);

    app(PlatformSettings::class)->set('sms_provider', 'fake');

    expect(app(SmsGateway::class))->toBeInstanceOf(FakeSmsGateway::class);
});

it('keeps the test console admin only', function () {
    seedSmsLenzAccount();

    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->post(route('admin.settings.sms-test'), [
            'mobile' => '0769828874',
            'message' => 'Nope',
        ])
        ->assertForbidden();
});
