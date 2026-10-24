<?php

use App\Models\User;

/**
 * The account form collects a first and a last name; `users.name` is kept as
 * the canonical full name so search, sorting, receipts and exports never need
 * to know about the split.
 */
test('a full name is split into first and last name', function () {
    $user = User::factory()->create(['name' => 'Asha Perera']);

    expect($user->first_name)->toBe('Asha')
        ->and($user->last_name)->toBe('Perera')
        ->and($user->name)->toBe('Asha Perera')
        ->and($user->displayName())->toBe('Asha');
});

test('a single word name stays in the first name', function () {
    $user = User::factory()->create(['name' => 'Madonna']);

    expect($user->first_name)->toBe('Madonna')
        ->and($user->last_name)->toBeNull()
        ->and($user->displayName())->toBe('Madonna');
});

test('saving first and last name rebuilds the full name', function () {
    $user = User::factory()->create(['name' => 'Old Name']);

    $user->update(['first_name' => 'Nimal', 'last_name' => 'Perera']);

    expect($user->fresh()->name)->toBe('Nimal Perera')
        ->and($user->fresh()->displayName())->toBe('Nimal');
});

test('assigning only a name re-splits the stored parts', function () {
    $user = User::factory()->create(['name' => 'Asha Perera']);

    $user->update(['name' => 'Nimal Silva']);

    expect($user->fresh()->first_name)->toBe('Nimal')
        ->and($user->fresh()->last_name)->toBe('Silva');
});

test('display name falls back to the full name when the first name is empty', function () {
    $user = User::factory()->create(['name' => 'Asha Perera']);

    // Simulate a row that predates the backfill.
    $user->forceFill(['first_name' => null])->saveQuietly();

    expect($user->fresh()->displayName())->toBe('Asha Perera');
});
