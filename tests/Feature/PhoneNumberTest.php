<?php

use App\Models\User;
use App\Support\PhoneNumber;

test('mobile numbers normalise to ten local digits', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('0769828874');
})->with([
    'digits' => '0769828874',
    'spaced local' => '076 982 88 74',
    'dashed' => '076-982-8874',
    'international plus' => '+94 76 982 8874',
    'international plus tight' => '+94769828874',
    'international prefix' => '0094 76 982 8874',
    'international without plus' => '94 76 982 8874',
    'body only' => '76 982 8874',
    'padded' => '  076 982 88 74  ',
]);

test('invalid mobile numbers do not normalise', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull()
        ->and(PhoneNumber::isValid($input))->toBeFalse();
})->with([
    'empty' => '',
    'spaces' => '   ',
    'too short' => '076 982 887',
    'too long' => '076 982 88 741',
    'landline' => '011 234 5678',
    'operator prefix 9' => '079 982 8874',
    'letters' => 'not-a-number',
]);

test('stored numbers are formatted for people', function () {
    expect(PhoneNumber::format('0769828874'))->toBe('076 982 88 74')
        ->and(PhoneNumber::format('+94 76 982 8874'))->toBe('076 982 88 74')
        ->and(PhoneNumber::format(null))->toBeNull()
        ->and(PhoneNumber::format('0123'))->toBe('0123');
});

test('the user model stores mobile numbers in canonical form', function () {
    $user = User::factory()->create(['mobile' => '+94 76 982 88 74']);

    expect($user->fresh()->mobile)->toBe('0769828874')
        ->and($user->mobileFormatted())->toBe('076 982 88 74');
});

test('a blank mobile number stays null', function () {
    $user = User::factory()->create(['mobile' => '']);

    expect($user->fresh()->mobile)->toBeNull()
        ->and($user->mobileFormatted())->toBeNull();
});
