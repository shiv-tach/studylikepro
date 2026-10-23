<?php

use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use App\Models\User;

beforeEach(function () {
    $this->teacher = User::factory()->teacher()->onboarded()->create();
    $this->profile = $this->teacher->teacherProfile;
});

it('shows the availability manager to the teacher', function () {
    TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $this->profile->id]);

    $response = $this->actingAs($this->teacher)
        ->get(route('teacher.availability.index'))
        ->assertOk()
        ->assertSee('id="teacher-availability-app"', false)
        ->assertSee('id="teacher-availability-config"', false)
        ->assertSee('resources/js/teacher-availability/main.js', false);

    $config = teacherAvailabilityConfig($response);

    expect(collect($config['week'])->firstWhere('value', 1)['ranges'])
        ->toBe([['start' => '18:00', 'end' => '21:00']])
        ->and(collect($config['week'])->firstWhere('value', 2)['enabled'])->toBeFalse()
        ->and($config['week'])->toHaveCount(7)
        ->and($config['presets'])->toHaveCount(3)
        ->and($config['pricing']['lessonDuration'])->toBe(60)
        ->and($config['pricing']['rate'])->toBe(500)
        ->and($config['pricing']['currency']['symbol'])->toBe(config('studylikepro.currency_symbol'))
        ->and($config['limits']['maxRangesPerDay'])->toBe(4)
        ->and($config['timezone'])->toBe('Asia/Colombo');
});

it('sends the next bookable slots with the manager', function () {
    foreach (array_keys(config('studylikepro.weekdays')) as $day) {
        TeacherAvailabilitySlot::factory()->on($day, '18:00', '21:00')->create(['teacher_profile_id' => $this->profile->id]);
    }

    $config = teacherAvailabilityConfig(
        $this->actingAs($this->teacher)->get(route('teacher.availability.index'))
    );

    expect($config['preview'])->not->toBeEmpty()
        ->and($config['preview'][0]['slots'][0]['time'])->toMatch('/\d{1,2}:\d{2} (AM|PM)/');
});

it('replaces the whole week from the manager', function () {
    TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $this->profile->id]);

    $this->actingAs($this->teacher)
        ->put(route('teacher.availability.week.update'), weekPayload([
            [
                'value' => 2,
                'enabled' => '1',
                'ranges' => [
                    ['start_time' => '15:00', 'end_time' => '18:00'],
                    ['start_time' => '19:00', 'end_time' => '21:00'],
                ],
            ],
            [
                'value' => 3,
                'enabled' => '1',
                'ranges' => [['start_time' => '09:00', 'end_time' => '12:00']],
            ],
            // A switched-off day keeps its value but contributes no range.
            ['value' => 4, 'ranges' => [['start_time' => '09:00', 'end_time' => '10:00']]],
        ]))
        ->assertRedirect(route('teacher.availability.index'))
        ->assertSessionHas('status', 'availability-week-saved')
        ->assertSessionHasNoErrors();

    // Monday's old range is gone: the week is replaced, not appended to.
    expect($this->profile->availabilitySlots()->count())->toBe(3)
        ->and($this->profile->availabilitySlots()->where('day_of_week', 1)->exists())->toBeFalse()
        ->and($this->profile->availabilitySlots()->where('day_of_week', 2)->count())->toBe(2);
});

it('replays a rejected week into the editor', function () {
    $this->actingAs($this->teacher)
        ->from(route('teacher.availability.index'))
        ->put(route('teacher.availability.week.update'), weekPayload([
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '21:00', 'end_time' => '18:00']]],
        ]))
        ->assertSessionHasErrors('days.0.ranges.0.end_time');

    $config = teacherAvailabilityConfig(
        $this->get(route('teacher.availability.index'))
    );

    expect($config['week'][0]['value'])->toBe(1)
        ->and($config['week'][0]['enabled'])->toBeTrue()
        ->and($config['week'][0]['ranges'])->toBe([['start' => '21:00', 'end' => '18:00']])
        ->and($config['errors']['days.0.ranges.0.end_time'])->not->toBeEmpty();
});

it('updates the default price', function () {
    $this->actingAs($this->teacher)
        ->put(route('teacher.availability.pricing.update'), ['hourly_rate' => 2750])
        ->assertRedirect(route('teacher.availability.index'))
        ->assertSessionHas('status', 'availability-pricing-updated')
        ->assertSessionHasNoErrors();

    expect($this->profile->fresh()->hourly_rate_minor)->toBe(275000);
});

it('validates the default price', function (mixed $rate) {
    $this->actingAs($this->teacher)
        ->put(route('teacher.availability.pricing.update'), ['hourly_rate' => $rate])
        ->assertSessionHasErrors('hourly_rate');

    expect($this->profile->fresh()->hourly_rate_minor)->toBe(50000);
})->with([
    'below the floor' => [50],
    'not a number' => ['free'],
]);

it('lists time off in the island with its own delete url', function () {
    $timeOff = TeacherTimeOff::factory()->create([
        'teacher_profile_id' => $this->profile->id,
        'starts_on' => now()->addDays(5)->toDateString(),
        'ends_on' => now()->addDays(6)->toDateString(),
        'reason' => 'Family function',
    ]);

    $config = teacherAvailabilityConfig(
        $this->actingAs($this->teacher)->get(route('teacher.availability.index'))
    );

    expect($config['timeOff'])->toHaveCount(1)
        ->and($config['timeOff'][0]['id'])->toBe($timeOff->id)
        ->and($config['timeOff'][0]['reason'])->toBe('Family function')
        ->and($config['timeOff'][0]['dayCountLabel'])->toBe('2 days')
        ->and($config['timeOff'][0]['deleteUrl'])->toBe(route('teacher.availability.time-off.destroy', $timeOff));
});

it('lets a teacher add a weekly range', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.availability.slots.store'), [
            'day_of_week' => 1,
            'start_time' => '18:00',
            'end_time' => '21:00',
        ])
        ->assertRedirect(route('teacher.availability.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('teacher_availability_slots', [
        'teacher_profile_id' => $this->profile->id,
        'day_of_week' => 1,
        'start_minute' => 1080,
        'end_minute' => 1260,
    ]);
});

it('rejects a range that overlaps an existing one', function () {
    TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $this->profile->id]);

    $this->actingAs($this->teacher)
        ->post(route('teacher.availability.slots.store'), [
            'day_of_week' => 1,
            'start_time' => '20:00',
            'end_time' => '22:00',
        ])
        ->assertSessionHasErrors('start_time');

    expect($this->profile->availabilitySlots()->count())->toBe(1);
});

it('rejects invalid ranges', function (array $payload, string $field) {
    $this->actingAs($this->teacher)
        ->post(route('teacher.availability.slots.store'), $payload)
        ->assertSessionHasErrors($field);

    expect($this->profile->availabilitySlots()->count())->toBe(0);
})->with([
    'end before start' => [['day_of_week' => 1, 'start_time' => '21:00', 'end_time' => '18:00'], 'end_time'],
    'shorter than the lesson' => [['day_of_week' => 1, 'start_time' => '18:00', 'end_time' => '18:30'], 'end_time'],
    'unknown weekday' => [['day_of_week' => 9, 'start_time' => '18:00', 'end_time' => '21:00'], 'day_of_week'],
    'bad time format' => [['day_of_week' => 1, 'start_time' => '6pm', 'end_time' => '21:00'], 'start_time'],
]);

it('lets a teacher remove their own range', function () {
    $slot = TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $this->profile->id]);

    $this->actingAs($this->teacher)
        ->delete(route('teacher.availability.slots.destroy', $slot))
        ->assertRedirect(route('teacher.availability.index'));

    $this->assertDatabaseMissing('teacher_availability_slots', ['id' => $slot->id]);
});

it('forbids removing another teacher range', function () {
    $other = TeacherProfile::factory()->approved()->create();
    $slot = TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $other->id]);

    $this->actingAs($this->teacher)
        ->delete(route('teacher.availability.slots.destroy', $slot))
        ->assertForbidden();

    $this->assertDatabaseHas('teacher_availability_slots', ['id' => $slot->id]);
});

it('lets a teacher add and remove time off', function () {
    $startsOn = now()->addDays(3)->toDateString();

    $this->actingAs($this->teacher)
        ->post(route('teacher.availability.time-off.store'), [
            'starts_on' => $startsOn,
            'ends_on' => now()->addDays(4)->toDateString(),
            'reason' => 'Family function',
        ])
        ->assertRedirect(route('teacher.availability.index'))
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('teacher_time_off', [
        'teacher_profile_id' => $this->profile->id,
        'starts_on' => $startsOn.' 00:00:00',
        'reason' => 'Family function',
    ]);

    $timeOff = $this->profile->timeOff()->first();

    $this->actingAs($this->teacher)
        ->delete(route('teacher.availability.time-off.destroy', $timeOff))
        ->assertRedirect(route('teacher.availability.index'));

    $this->assertDatabaseMissing('teacher_time_off', ['id' => $timeOff->id]);
});

it('validates time off dates', function () {
    $this->actingAs($this->teacher)
        ->post(route('teacher.availability.time-off.store'), [
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
        ])
        ->assertSessionHasErrors('starts_on');

    $this->actingAs($this->teacher)
        ->post(route('teacher.availability.time-off.store'), [
            'starts_on' => now()->addDays(5)->toDateString(),
            'ends_on' => now()->addDays(2)->toDateString(),
        ])
        ->assertSessionHasErrors('ends_on');
});

it('forbids removing another teacher time off', function () {
    $timeOff = TeacherTimeOff::factory()->create();

    $this->actingAs($this->teacher)
        ->delete(route('teacher.availability.time-off.destroy', $timeOff))
        ->assertForbidden();

    $this->assertDatabaseHas('teacher_time_off', ['id' => $timeOff->id]);
});

it('updates the lesson length', function () {
    $this->actingAs($this->teacher)
        ->put(route('teacher.availability.settings.update'), ['lesson_duration_minutes' => 45])
        ->assertRedirect(route('teacher.availability.index'))
        ->assertSessionHasNoErrors();

    expect($this->profile->fresh()->lessonDuration())->toBe(45);

    $this->actingAs($this->teacher)
        ->put(route('teacher.availability.settings.update'), ['lesson_duration_minutes' => 50])
        ->assertSessionHasErrors('lesson_duration_minutes');
});

it('blocks students from the availability manager', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get(route('teacher.availability.index'))
        ->assertForbidden();
});
