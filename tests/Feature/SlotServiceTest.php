<?php

use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use App\Services\SlotService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->service = new SlotService;
    $this->travelTo(CarbonImmutable::parse('2026-05-31 09:00', 'UTC'));
});

it('converts weekly ranges from the teacher timezone into utc slots', function () {
    $teacher = TeacherProfile::factory()->approved()->create([
        'timezone' => 'Asia/Colombo',
        'lesson_duration_minutes' => 60,
    ]);

    TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $teacher->id]);

    $slots = $this->service->openSlots(
        $teacher,
        CarbonImmutable::parse('2026-06-01 00:00', 'UTC'),
        CarbonImmutable::parse('2026-06-02 00:00', 'UTC'),
    );

    expect($slots)->toHaveCount(3)
        ->and(collect($slots)->pluck('starts_at')->map->toIso8601String()->all())->toBe([
            '2026-06-01T12:30:00+00:00',
            '2026-06-01T13:30:00+00:00',
            '2026-06-01T14:30:00+00:00',
        ])
        ->and($slots[0]['local_time'])->toBe('18:00')
        ->and($slots[0]['ends_at']->toIso8601String())->toBe('2026-06-01T13:30:00+00:00');
});

it('splits ranges using the lesson duration', function () {
    $teacher = TeacherProfile::factory()->approved()->create([
        'timezone' => 'UTC',
        'lesson_duration_minutes' => 45,
    ]);

    TeacherAvailabilitySlot::factory()->on(1, '09:00', '12:00')->create(['teacher_profile_id' => $teacher->id]);

    $slots = $this->service->openSlots(
        $teacher,
        CarbonImmutable::parse('2026-06-01 00:00', 'UTC'),
        CarbonImmutable::parse('2026-06-02 00:00', 'UTC'),
    );

    expect($slots)->toHaveCount(4)
        ->and($slots[3]['starts_at']->toIso8601String())->toBe('2026-06-01T11:15:00+00:00');
});

it('skips days covered by time off', function () {
    $teacher = TeacherProfile::factory()->approved()->create([
        'timezone' => 'UTC',
        'lesson_duration_minutes' => 60,
    ]);

    TeacherAvailabilitySlot::factory()->on(1, '09:00', '12:00')->create(['teacher_profile_id' => $teacher->id]);
    TeacherAvailabilitySlot::factory()->on(2, '09:00', '12:00')->create(['teacher_profile_id' => $teacher->id]);
    TeacherTimeOff::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-01',
    ]);

    $slots = $this->service->openSlots(
        $teacher,
        CarbonImmutable::parse('2026-06-01 00:00', 'UTC'),
        CarbonImmutable::parse('2026-06-08 12:00', 'UTC'),
    );

    $dates = collect($slots)->pluck('local_date')->unique()->values()->all();

    expect($slots)->toHaveCount(6)
        ->and($dates)->toBe(['2026-06-02', '2026-06-08']);
});

it('excludes past times and taken ranges', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-01 12:45', 'UTC'));

    $teacher = TeacherProfile::factory()->approved()->create([
        'timezone' => 'UTC',
        'lesson_duration_minutes' => 60,
    ]);

    TeacherAvailabilitySlot::factory()->on(1, '09:00', '15:00')->create(['teacher_profile_id' => $teacher->id]);

    $slots = $this->service->openSlots(
        $teacher,
        CarbonImmutable::now(),
        CarbonImmutable::parse('2026-06-02 00:00', 'UTC'),
        excluded: [[
            CarbonImmutable::parse('2026-06-01 13:00', 'UTC'),
            CarbonImmutable::parse('2026-06-01 14:00', 'UTC'),
        ]],
    );

    expect($slots)->toHaveCount(1)
        ->and($slots[0]['starts_at']->toIso8601String())->toBe('2026-06-01T14:00:00+00:00');
});

it('keeps slots unique and offset-correct across a dst change', function () {
    $this->travelTo(CarbonImmutable::parse('2026-03-06 12:00', 'UTC'));

    $teacher = TeacherProfile::factory()->approved()->create([
        'timezone' => 'America/New_York',
        'lesson_duration_minutes' => 60,
    ]);

    TeacherAvailabilitySlot::factory()->on(6, '14:00', '16:00')->create(['teacher_profile_id' => $teacher->id]);
    TeacherAvailabilitySlot::factory()->on(0, '14:00', '16:00')->create(['teacher_profile_id' => $teacher->id]);

    $slots = $this->service->openSlots(
        $teacher,
        CarbonImmutable::now(),
        CarbonImmutable::now()->addDays(4),
    );

    $byDate = collect($slots)->groupBy('local_date');

    expect($slots)->toHaveCount(4)
        ->and($byDate['2026-03-07'][0]['starts_at']->toIso8601String())->toBe('2026-03-07T19:00:00+00:00')
        ->and($byDate['2026-03-08'][0]['starts_at']->toIso8601String())->toBe('2026-03-08T18:00:00+00:00')
        ->and(collect($slots)->pluck('starts_at')->map(fn ($start) => $start->timestamp)->unique())->toHaveCount(4);
});

it('answers whether a specific instant is inside a weekly range', function () {
    $teacher = TeacherProfile::factory()->approved()->create([
        'timezone' => 'Asia/Colombo',
        'lesson_duration_minutes' => 60,
    ]);

    TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $teacher->id]);

    // 18:30 Sri Lanka time on Monday.
    expect($this->service->isAvailableAt($teacher, CarbonImmutable::parse('2026-06-01 13:00', 'UTC')))->toBeTrue()
        ->and($this->service->isAvailableAt($teacher, CarbonImmutable::parse('2026-06-01 16:00', 'UTC')))->toBeFalse()
        ->and($this->service->isAvailableAt($teacher, CarbonImmutable::parse('2026-06-02 13:00', 'UTC')))->toBeFalse();

    TeacherTimeOff::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_on' => '2026-06-01',
        'ends_on' => '2026-06-01',
    ]);

    expect($this->service->isAvailableAt($teacher->fresh(), CarbonImmutable::parse('2026-06-01 13:00', 'UTC')))->toBeFalse();
});
