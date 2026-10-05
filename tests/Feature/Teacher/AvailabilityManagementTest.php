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

    $this->actingAs($this->teacher)
        ->get(route('teacher.availability.index'))
        ->assertOk()
        ->assertSee('Weekly availability')
        ->assertSee('18:00–21:00')
        ->assertSee('Base rate');
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
