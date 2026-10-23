<?php

use App\Enums\BookingStatus;
use App\Support\BookingDisplay;

test('every booking status carries its own semantic colour', function () {
    $tones = collect(BookingStatus::cases())
        ->mapWithKeys(fn (BookingStatus $status) => [$status->value => BookingDisplay::tone($status)]);

    expect($tones->all())->toBe([
        'pending_payment' => 'amber',
        'confirmed' => 'sky',
        'in_progress' => 'primary',
        'completed' => 'emerald',
        'cancelled' => 'slate',
        'expired' => 'slate',
        'no_show' => 'rose',
        'disputed' => 'rose',
        'resolved' => 'slate',
    ]);

    // A confirmed lesson that has not happened yet and a completed one sit side
    // by side in a session list, so the two must never share a colour.
    expect($tones['confirmed'])->not->toBe($tones['completed']);
});

test('every booking status has a glyph for its chip', function () {
    $icons = collect(BookingStatus::cases())->map(fn (BookingStatus $status) => BookingDisplay::icon($status));

    expect($icons->every(fn (string $icon) => $icon !== ''))->toBeTrue()
        ->and(BookingDisplay::icon(BookingStatus::Confirmed))->toBe('calendar')
        ->and(BookingDisplay::icon(BookingStatus::Completed))->toBe('check-circle');
});
