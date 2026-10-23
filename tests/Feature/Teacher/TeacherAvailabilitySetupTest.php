<?php

use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use App\Models\User;

/**
 * A teacher who saved the profile (step 1) but has not set a week (step 2) yet.
 */
function teacherWithoutWeek(): User
{
    $user = User::factory()->teacher()->create();
    TeacherProfile::factory()->for($user)->create();

    return $user;
}

/**
 * The day rows the Vue editor and the no-JS form both submit.
 *
 * @param  array<int, array{value: int, enabled: string, ranges: array<int, array{start_time: string, end_time: string}>}>  $days
 * @return array{days: array<int, mixed>}
 */
function weekPayload(array $days): array
{
    return ['days' => $days];
}

it('shows onboarding step 2 with the recommended week pre-loaded', function () {
    $teacher = teacherWithoutWeek();

    $response = $this->actingAs($teacher)
        ->get(route('teacher.availability.setup'))
        ->assertOk()
        ->assertSee('id="teacher-availability-setup-app"', false)
        ->assertSee('Set your weekly availability')
        ->assertSee('Suggested weeks')
        ->assertSee('School-friendly');

    $config = availabilitySetupConfig($response);

    expect($config['currentStep'])->toBe(2)
        ->and(collect($config['steps'])->pluck('label')->all())
        ->toBe(['Teaching profile', 'Weekly availability', 'Verification'])
        ->and($config['profile']['isComplete'])->toBeTrue()
        ->and($config['profile']['hasAvailability'])->toBeFalse()
        ->and($config['profile']['lessonDuration'])->toBe(60)
        ->and($config['profile']['timezone'])->toBe('Asia/Colombo')
        ->and($config['presets'])->toHaveCount(3)
        ->and($config['presets'][0]['key'])->toBe('school_friendly')
        // Weekdays 3-9 PM, weekends 8 AM-8 PM, still unsaved.
        ->and(collect($config['week'])->firstWhere('value', 1)['ranges'])
        ->toBe([['start' => '15:00', 'end' => '21:00']])
        ->and(collect($config['week'])->firstWhere('value', 0)['ranges'])
        ->toBe([['start' => '08:00', 'end' => '20:00']])
        ->and($teacher->teacherProfile->availabilitySlots()->count())->toBe(0);
});

it('sends a teacher with an incomplete profile back to step 1', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.availability.setup'))
        ->assertRedirect(route('teacher.profile'))
        ->assertSessionHas('status', 'complete-your-profile');
});

it('saves several ranges per day and ignores days that are switched off', function () {
    $teacher = teacherWithoutWeek();
    $profile = $teacher->teacherProfile;

    $this->actingAs($teacher)
        ->put(route('teacher.availability.setup.update'), weekPayload([
            [
                'value' => 1,
                'enabled' => '1',
                'ranges' => [
                    ['start_time' => '15:00', 'end_time' => '18:00'],
                    ['start_time' => '19:00', 'end_time' => '21:00'],
                ],
            ],
            [
                'value' => 2,
                'enabled' => '1',
                'ranges' => [['start_time' => '08:00', 'end_time' => '12:00']],
            ],
            [
                'value' => 6,
                'enabled' => '0',
                'ranges' => [['start_time' => '08:00', 'end_time' => '20:00']],
            ],
        ]))
        ->assertRedirect(route('teacher.verification'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'availability-saved');

    $this->assertDatabaseHas('teacher_availability_slots', [
        'teacher_profile_id' => $profile->id,
        'day_of_week' => 1,
        'start_minute' => 900,
        'end_minute' => 1080,
    ]);
    $this->assertDatabaseHas('teacher_availability_slots', [
        'teacher_profile_id' => $profile->id,
        'day_of_week' => 1,
        'start_minute' => 1140,
        'end_minute' => 1260,
    ]);
    $this->assertDatabaseHas('teacher_availability_slots', [
        'teacher_profile_id' => $profile->id,
        'day_of_week' => 2,
        'start_minute' => 480,
        'end_minute' => 720,
    ]);
    $this->assertDatabaseMissing('teacher_availability_slots', [
        'teacher_profile_id' => $profile->id,
        'day_of_week' => 6,
    ]);

    expect($profile->availabilitySlots()->count())->toBe(3);
});

it('replaces the previous week instead of appending to it', function () {
    $teacher = teacherWithoutWeek();
    $profile = $teacher->teacherProfile;

    $this->actingAs($teacher)->put(route('teacher.availability.setup.update'), weekPayload([
        ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
        ['value' => 2, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($teacher)->put(route('teacher.availability.setup.update'), weekPayload([
        ['value' => 6, 'enabled' => '1', 'ranges' => [['start_time' => '08:00', 'end_time' => '20:00']]],
    ]))->assertSessionHasNoErrors();

    expect($profile->availabilitySlots()->count())->toBe(1)
        ->and($profile->availabilitySlots()->first()->day_of_week)->toBe(6);
});

it('keeps the teacher time off when the week is replaced', function () {
    $teacher = teacherWithoutWeek();
    $profile = $teacher->teacherProfile;

    $timeOff = TeacherTimeOff::create([
        'teacher_profile_id' => $profile->id,
        'starts_on' => now()->addWeek()->toDateString(),
        'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
        'reason' => 'Family function',
    ]);

    $this->actingAs($teacher)->put(route('teacher.availability.setup.update'), weekPayload([
        ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('teacher_time_off', ['id' => $timeOff->id]);
});

it('rejects an invalid week', function (array $days, string $field) {
    $teacher = teacherWithoutWeek();

    $this->actingAs($teacher)
        ->put(route('teacher.availability.setup.update'), ['days' => $days])
        ->assertSessionHasErrors($field);

    expect($teacher->teacherProfile->availabilitySlots()->count())->toBe(0);
})->with([
    'no ranges at all' => [
        [
            ['value' => 1, 'enabled' => '0'],
            ['value' => 2, 'enabled' => '0'],
        ],
        'days',
    ],
    'end before start' => [
        [
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '21:00', 'end_time' => '18:00']]],
        ],
        'days.0.ranges.0.end_time',
    ],
    'shorter than the lesson length' => [
        [
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '15:30']]],
        ],
        'days.0.ranges.0.end_time',
    ],
    'overlapping ranges on one day' => [
        [
            [
                'value' => 1,
                'enabled' => '1',
                'ranges' => [
                    ['start_time' => '15:00', 'end_time' => '18:00'],
                    ['start_time' => '17:00', 'end_time' => '20:00'],
                ],
            ],
        ],
        'days.0.ranges.1.start_time',
    ],
    'missing one end of a range' => [
        [
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00']]],
        ],
        'days.0.ranges.0.start_time',
    ],
    'unknown weekday' => [
        [
            ['value' => 9, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
        ],
        'days.0.value',
    ],
    'the same weekday twice' => [
        [
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '08:00', 'end_time' => '12:00']]],
        ],
        'days.1.value',
    ],
    'a time that is not H:i' => [
        [
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '6pm', 'end_time' => '21:00']]],
        ],
        'days.0.ranges.0.start_time',
    ],
]);

it('replays a rejected save with its values and messages', function () {
    $teacher = teacherWithoutWeek();

    $this->actingAs($teacher)
        ->from(route('teacher.availability.setup'))
        ->put(route('teacher.availability.setup.update'), weekPayload([
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '15:30']]],
        ]))
        ->assertSessionHasErrors('days.0.ranges.0.end_time');

    $config = availabilitySetupConfig(
        $this->actingAs($teacher)->get(route('teacher.availability.setup'))->assertOk()
    );

    expect(collect($config['week'])->firstWhere('value', 1)['ranges'])
        ->toBe([['start' => '15:00', 'end' => '15:30']])
        ->and(collect($config['week'])->firstWhere('value', 2)['enabled'])->toBeFalse()
        ->and($config['errors']['days.0.ranges.0.end_time'])->not->toBeEmpty();
});

it('blocks verification and the dashboard until a weekly range exists', function () {
    $teacher = teacherWithoutWeek();

    $this->actingAs($teacher)
        ->get(route('teacher.verification'))
        ->assertRedirect(route('teacher.availability.setup'))
        ->assertSessionHas('status', 'complete-your-availability');

    $this->actingAs($teacher)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertRedirect(route('teacher.availability.setup'));

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.availability.setup'))
        ->assertSessionHas('status', 'complete-your-availability');
});

it('opens verification again once a range is saved', function () {
    $teacher = teacherWithoutWeek();

    $this->actingAs($teacher)->put(route('teacher.availability.setup.update'), weekPayload([
        ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($teacher)->get(route('teacher.verification'))->assertOk();
});

it('sends a submitted teacher to the full availability manager', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.availability.setup'))
        ->assertRedirect(route('teacher.availability.index'));
});

it('blocks the setup save when the profile is incomplete', function () {
    $teacher = User::factory()->teacher()->create();

    $this->actingAs($teacher)
        ->put(route('teacher.availability.setup.update'), weekPayload([
            ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '21:00']]],
        ]))
        ->assertRedirect(route('teacher.profile'))
        ->assertSessionHas('status', 'complete-your-profile');
});
