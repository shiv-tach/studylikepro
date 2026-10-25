<?php

use App\Contracts\SmsGateway;
use App\Services\PlatformSettings;
use App\Services\Sms\FakeSmsGateway;
use App\Services\Sms\SmsLenzGateway;
use App\Support\SmsLenzConfig;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| SMS provider selection
|--------------------------------------------------------------------------
|
| `sms_provider` decides whether registration texts leave the server. A
| misspelt value used to slide into the demo gateway without a word, which
| looks exactly like a text that never arrived, so the fallback is logged.
|
*/

it('falls back to the demo gateway and warns when the provider is misspelt', function () {
    app(PlatformSettings::class)->set('sms_provider', 'smslentz');
    Log::spy();

    expect(app(SmsLenzConfig::class)->provider())->toBe('fake')
        ->and(app(SmsGateway::class))->toBeInstanceOf(FakeSmsGateway::class);

    Log::shouldHaveReceived('warning')->atLeast()->once();
});

it('reads the provider name whatever the casing and padding', function () {
    app(PlatformSettings::class)->set('sms_provider', ' SMSLenz ');
    Log::spy();

    expect(app(SmsLenzConfig::class)->provider())->toBe('smslenz')
        ->and(app(SmsGateway::class))->toBeInstanceOf(SmsLenzGateway::class);

    Log::shouldNotHaveReceived('warning');
});

it('keeps the demo gateway when verification is switched to the demo provider', function () {
    app(PlatformSettings::class)->set('sms_provider', 'fake');
    Log::spy();

    expect(app(SmsLenzConfig::class)->provider())->toBe('fake')
        ->and(app(SmsGateway::class))->toBeInstanceOf(FakeSmsGateway::class);

    Log::shouldNotHaveReceived('warning');
});
