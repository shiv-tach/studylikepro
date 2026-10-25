<?php

use App\Models\User;
use App\Notifications\LessonCompleted;

/*
|--------------------------------------------------------------------------
| The Settings section is Vue
|--------------------------------------------------------------------------
|
| Every settings screen keeps Blade as the source of truth: it renders a JSON
| island with the cards/pickers/fields, their current values, Laravel's messages
| and the route map, and the Vue bundle only draws it. These tests pin that
| contract so a renamed key or a dropped route is caught here rather than in the
| browser.
|
*/

it('builds the settings home cards from the island', function () {
    $student = User::factory()->student()->onboarded()->create();

    $response = $this->actingAs($student)->get(route('settings.index'))->assertOk();

    $config = settingsConfig($response);

    expect(collect($config['cards'])->pluck('key')->all())
        ->toBe(['theme', 'profile', 'notifications', 'security', 'privacy', 'billing'])
        ->and($config['notifications']['emailEnabled'])->toBeTrue()
        ->and($config['urls']['theme'])->toBe(route('settings.theme'))
        ->and($config['urls']['profile'])->toBe(route('profile.edit'))
        ->and($config['urls']['notificationsUpdate'])->toBe(route('settings.notifications.update'));

    // The toggle's label travels in the island, so the page is still readable
    // (and assertable) without running JavaScript.
    $response->assertSee('Save notification preferences');
});

it('reflects the email preference and the unread badge on the settings home', function () {
    $student = User::factory()->student()->onboarded()->create([
        'notification_preferences' => ['email' => false],
    ]);

    $student->notify(new LessonCompleted(paidBookingScenario()['booking']));

    $config = settingsConfig($this->actingAs($student)->get(route('settings.index'))->assertOk());

    expect($config['notifications']['emailEnabled'])->toBeFalse()
        ->and($config['notifications']['unreadCount'])->toBe(1);
});

it('builds the theme page from its island and keeps the saved choice', function () {
    $user = User::factory()->create();
    $user->update(['theme' => [
        'preset' => 'ocean',
        'accent' => 'cyan',
        'mode' => 'dark',
        'sidebarStyle' => 'floating',
    ]]);

    $config = settingsThemeConfig($this->actingAs($user)->get(route('settings.theme'))->assertOk());

    expect($config['selected'])->toBe([
        'preset' => 'ocean',
        'accent' => 'cyan',
        'mode' => 'dark',
        'sidebarStyle' => 'floating',
    ])
        ->and(collect($config['presets'])->pluck('id')->all())
        ->toBe(['classic', 'forest', 'midnight', 'sunset', 'glass', 'ocean'])
        ->and(collect($config['accents'])->pluck('id')->all())
        ->toBe(['indigo', 'violet', 'emerald', 'rose', 'amber', 'blue', 'cyan'])
        ->and($config['urls']['update'])->toBe(route('settings.theme.update'));
});

it('reopens the theme picker on the values a rejected save carried', function () {
    $user = User::factory()->create();

    // The stored theme is the default classic/indigo/light/flat. One invalid
    // value rejects the whole save, so every field has to come back from old().
    $this->actingAs($user)
        ->from(route('settings.theme'))
        ->patch(route('settings.theme.update'), [
            'theme_preset' => 'forest',
            'theme_accent' => 'chartreuse',
            'theme_mode' => 'dark',
            'theme_sidebarStyle' => 'floating',
        ])
        ->assertSessionHasErrors('theme_accent')
        ->assertRedirect(route('settings.theme'));

    $config = settingsThemeConfig($this->actingAs($user)->get(route('settings.theme'))->assertOk());

    expect($config['selected']['preset'])->toBe('forest')
        ->and($config['selected']['accent'])->toBe('chartreuse')
        ->and($config['selected']['mode'])->toBe('dark')
        ->and($config['selected']['sidebarStyle'])->toBe('floating');
});

it('builds the admin platform settings console from its island', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.settings.edit'))->assertOk();

    $config = adminSettingsConfig($response);
    $keys = collect($config['groups'])->flatMap(fn ($group) => collect($group['items'])->pluck('key'));

    expect($keys)->toContain(
        'commission_percent',
        'currency',
        'hold_ttl_minutes',
        'student_cancel_window_hours',
        'cancellation_policy_text',
        'booking_fee_type',
        'booking_fee_percent',
        'sms_provider',
        'smslenz_user_id',
        'smslenz_api_key',
        'smslenz_sender_id',
        'sms_otp_enabled',
        'sms_otp_length',
        'sms_otp_ttl_minutes',
        'sms_otp_resend_seconds',
        'sms_otp_max_attempts',
        'sms_otp_max_resends',
    )
        ->and($config['groups'][0]['label'])->toBe('Money')
        ->and(collect($config['groups'])->pluck('label'))->toContain('SMS & OTP')
        ->and(collect($config['groups'])->pluck('label'))->toContain('SMSLenz API')
        ->and($config['urls']['update'])->toBe(route('admin.settings.update'))
        ->and($config['urls']['smsTest'])->toBe(route('admin.settings.sms-test'))
        ->and($config['meta']['currencySymbol'])->not->toBe('');

    // The money fields are edited in whole currency units, not minor units.
    $items = collect($config['groups'])->flatMap(fn ($group) => $group['items']);

    $bookingFee = $items->firstWhere('key', 'booking_fee_minor');

    expect($bookingFee['type'])->toBe('money')
        ->and($bookingFee['inputType'])->toBe('number')
        ->and($bookingFee['step'])->toBe('0.01');

    // The fee type is a select and the percentage carries its 0-100 bounds.
    $feeType = $items->firstWhere('key', 'booking_fee_type');
    $feePercent = $items->firstWhere('key', 'booking_fee_percent');

    expect($feeType['type'])->toBe('select')
        ->and($feeType['value'])->toBe('fixed')
        ->and($feeType['options'])->toBe([
            ['value' => 'fixed', 'label' => 'Fixed amount'],
            ['value' => 'percent', 'label' => 'Percentage of the price'],
        ])
        ->and($feePercent['type'])->toBe('float')
        ->and($feePercent['min'])->toBe(0)
        ->and($feePercent['max'])->toBe(100);

    // The noscript twin ships real inputs named after the settings, including
    // a select for the fee type.
    $response->assertSee('name="booking_fee_type"', false)
        ->assertSee('name="booking_fee_percent"', false)
        ->assertSee('<select id="setting-booking_fee_type"', false)
        ->assertSee('Percentage of the price');
});

it('builds the profile page from its island', function () {
    $user = User::factory()->create();

    $config = profileConfig($this->actingAs($user)->get('/profile')->assertOk());

    expect($config['values']['firstName'])->toBe($user->first_name)
        ->and($config['values']['lastName'])->toBe($user->last_name)
        ->and($config['values']['email'])->toBe($user->email)
        ->and($config['user']['name'])->toBe($user->displayName())
        ->and($config['urls']['update'])->toBe(route('profile.update'))
        ->and($config['urls']['password'])->toBe(route('password.update'))
        ->and($config['urls']['destroy'])->toBe(route('profile.destroy'))
        ->and($config['deleteDialogOpen'])->toBeFalse();
});

it('reopens the delete dialog when the confirmation password is wrong', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from('/profile')
        ->delete('/profile', ['password' => 'not-the-password'])
        ->assertRedirect('/profile');

    $config = profileConfig($this->actingAs($user)->get('/profile')->assertOk());

    expect($config['deleteDialogOpen'])->toBeTrue()
        ->and($config['deleteErrors'])->not->toBeEmpty();
});

it('builds the notification centre from its island', function () {
    $scenario = paidBookingScenario();
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $response = $this->actingAs($scenario['student'])->get(route('notifications.index'))->assertOk();

    $config = notificationsConfig($response);
    $row = $config['notifications'][0];

    expect($config['unreadCount'])->toBe(1)
        ->and($row['title'])->toBe('Lesson completed')
        ->and($row['unread'])->toBeTrue()
        ->and($row['time'])->not->toBe('')
        ->and($row['openUrl'])->toBe(route('notifications.open', $row['id']));

    // The read-state copy and the "mark all as read" action stay server-rendered.
    $response->assertSee('Mark all as read');
});
