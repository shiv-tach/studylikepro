<?php

use App\Models\Booking;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * An approved O/L teacher with Grade 6 (cheap) and Grade 10 (dear) rates on
 * one subject, a cheaper subject override, and an even cheaper base rate.
 *
 * @return array{teacher: TeacherProfile, subject: Subject, grade6: Grade, grade10: Grade}
 */
function gradeRateScenario(): array
{
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $ol->id)->where('number', 6)->firstOrFail();
    $grade10 = Grade::query()->where('education_level_id', $ol->id)->where('number', 10)->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Grade Priced Mathematics',
        'slug' => 'grade-priced-mathematics',
        'education_level_id' => $ol->id,
    ]);

    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 100000,
    ]);

    $teacher->subjects()->attach($subject->id, [
        'grade_levels' => [(string) $grade6->id, (string) $grade10->id],
        'rate_per_hour_minor' => 120000,
        'grade_rates' => [$grade6->id => 150000, $grade10->id => 250000],
    ]);

    return ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6, 'grade10' => $grade10];
}

test('teachers can save a rate for every grade they support', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $ol->id)->where('number', 6)->firstOrFail();
    $grade7 = Grade::query()->where('education_level_id', $ol->id)->where('number', 7)->firstOrFail();

    $math = Subject::factory()->create(['name' => 'Mathematics', 'education_level_id' => $ol->id]);
    $user = User::factory()->teacher()->onboarded()->create();

    $user->teacherProfile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'sync_grades' => '1',
            'grades' => [$grade6->id, $grade7->id],
            'grade_rates' => [
                $grade6->id => 1500,
                $grade7->id => 1750,
            ],
            'rate_per_hour' => 1200,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('teacher.subjects.index'));

    $pivot = $user->teacherProfile->fresh()->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_rates)->toEqual([$grade6->id => 150000, $grade7->id => 175000])
        ->and($pivot->rate_per_hour_minor)->toBe(120000);
});

test('a grade the teacher stops supporting loses its rate', function () {
    ['teacher' => $teacher, 'subject' => $subject] = gradeRateScenario();

    $grades = $teacher->fresh()->subjects()->where('subjects.id', $subject->id)->first()->pivot->grade_rates;
    $keptGrade = (int) array_key_first($grades);
    $droppedGrade = (int) array_key_last($grades);

    $this->actingAs($teacher->user)
        ->post(route('teacher.subjects.update', $subject), [
            'sync_grades' => '1',
            'grades' => [$keptGrade],
            'grade_rates' => [$keptGrade => 1500, $droppedGrade => 2500],
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $pivot = $teacher->fresh()->subjects()->where('subjects.id', $subject->id)->first()->pivot;

    expect($pivot->grade_rates)->toEqual([$keptGrade => 150000]);
});

test('clearing a grade rate falls back to the default rate', function () {
    ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6] = gradeRateScenario();

    $this->actingAs($teacher->user)
        ->post(route('teacher.subjects.update', $subject), [
            'sync_grades' => '1',
            'grades' => [$grade6->id],
            'grade_rates' => [$grade6->id => ''],
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $pivot = $teacher->fresh()->subjects()->where('subjects.id', $subject->id)->first()->pivot;

    expect($pivot->grade_rates)->toBeNull();
});

test('a grade rate for another level is rejected', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $alGrade = Grade::query()->where('number', 12)->firstOrFail();

    $math = Subject::factory()->create(['name' => 'Mathematics', 'education_level_id' => $ol->id]);
    $user = User::factory()->teacher()->onboarded()->create();

    $user->teacherProfile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'sync_grades' => '1',
            'grades' => [],
            'grade_rates' => [$alGrade->id => 1500],
        ])
        ->assertSessionHasErrors('grade_rates');

    expect($user->teacherProfile->fresh()->subjects()->where('subjects.id', $math->id)->first()->pivot->grade_rates)->toBeNull();
});

test('the subjects page pre-fills the saved grade rates', function () {
    ['teacher' => $teacher, 'grade6' => $grade6, 'grade10' => $grade10] = gradeRateScenario();

    $this->actingAs($teacher->user)
        ->get(route('teacher.subjects.index'))
        ->assertOk()
        ->assertSee('Grades you support & rate')
        ->assertSee('name="grade_rates['.$grade6->id.']"', false)
        ->assertSee('name="grade_rates['.$grade10->id.']"', false)
        ->assertSee('value="1500"', false)
        ->assertSee('value="2500"', false)
        ->assertSee('Default rate (optional)')
        ->assertSee('value="1200"', false);
});

test('the effective rate is the grade rate, then the subject override, then the base rate', function () {
    ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6, 'grade10' => $grade10] = gradeRateScenario();

    $teacher = $teacher->fresh()->load('subjects');
    $unratedGrade = gradeId(7);

    expect($teacher->effectiveRateFor($subject, $grade6->id))->toBe(150000)
        ->and($teacher->effectiveRateFor($subject, $grade10->id))->toBe(250000)
        ->and($teacher->effectiveRateFor($subject, $unratedGrade))->toBe(120000)
        ->and($teacher->effectiveRateFor($subject))->toBe(120000)
        ->and($teacher->startingRateMinor())->toBe(150000)
        ->and($teacher->startingRateMinor($grade6->id))->toBe(150000)
        ->and($teacher->startingRateMinor($grade10->id))->toBe(250000);

    $teacher->subjects()->updateExistingPivot($subject->id, ['rate_per_hour_minor' => null, 'grade_rates' => null]);

    $teacher = $teacher->fresh()->load('subjects');

    expect($teacher->effectiveRateFor($subject, $grade6->id))->toBe(100000)
        ->and($teacher->effectiveRateFor($subject))->toBe(100000)
        ->and($teacher->startingRateMinor())->toBe(100000);
});

test('the public teacher profile shows a rate for every grade', function () {
    ['teacher' => $teacher, 'grade6' => $grade6, 'grade10' => $grade10] = gradeRateScenario();

    $this->get(route('teachers.show', $teacher))
        ->assertOk()
        ->assertSee('Grade 6 · '.platform_settings()->formatMinor(150000).'/hr')
        ->assertSee('Grade 10 · '.platform_settings()->formatMinor(250000).'/hr');

    expect($grade6->label)->toBe('Grade 6')
        ->and($grade10->label)->toBe('Grade 10');
});

test('the public subject page prices teachers for the selected grade', function () {
    ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6, 'grade10' => $grade10] = gradeRateScenario();

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade6->id]))
        ->assertOk()
        ->assertSee($teacher->user->name)
        ->assertSee(platform_settings()->formatMinor(150000))
        ->assertDontSee(platform_settings()->formatMinor(250000));

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade10->id]))
        ->assertOk()
        ->assertSee(platform_settings()->formatMinor(250000))
        ->assertDontSee(platform_settings()->formatMinor(150000));

    // Without a grade the card advertises the cheapest grade rate.
    $this->get(route('catalog.subjects.show', $subject))
        ->assertOk()
        ->assertSee(platform_settings()->formatMinor(150000))
        ->assertDontSee(platform_settings()->formatMinor(250000));
});

test('the teacher directory prices and filters on the selected grade rate', function () {
    ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6] = gradeRateScenario();

    // The underlying expression is also exercised by the price filters: the
    // base rate (1,000) is inside 1,400 but the Grade 6 rate (1,500) is not.
    $this->get(route('teachers.index', ['subject' => $subject->slug, 'grade' => $grade6->id, 'max_rate' => 1400]))
        ->assertOk()
        ->assertDontSee($teacher->user->name);

    $this->get(route('teachers.index', ['subject' => $subject->slug, 'grade' => $grade6->id, 'max_rate' => 1600]))
        ->assertOk()
        ->assertSee($teacher->user->name)
        ->assertSee(platform_settings()->formatMinor(150000));

    // Without a grade the card prices the subject's cheapest grade rate.
    $this->get(route('teachers.index', ['subject' => $subject->slug]))
        ->assertOk()
        ->assertSee(platform_settings()->formatMinor(150000))
        ->assertDontSee(platform_settings()->formatMinor(250000));
});

test('price sorting follows the selected grade rate', function () {
    ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6] = gradeRateScenario();

    // The scenario teacher is cheaper on the base rate but dearer in Grade 6.
    $teacher->subjects()->updateExistingPivot($subject->id, ['grade_rates' => [$grade6->id => 300000]]);

    $cheapForGrade = TeacherProfile::factory()->approved()->create([
        'hourly_rate_minor' => 200000,
    ]);
    $cheapForGrade->subjects()->attach($subject->id, [
        'grade_levels' => [(string) $grade6->id],
        'grade_rates' => [$grade6->id => 150000],
    ]);

    $this->get(route('teachers.index', [
        'subject' => $subject->slug,
        'grade' => $grade6->id,
        'sort' => 'price_low',
    ]))
        ->assertOk()
        ->assertSeeInOrder([$cheapForGrade->user->name, $teacher->user->name]);
});

test('the booking page and hold use the learner grade rate', function () {
    ['teacher' => $teacher, 'subject' => $subject, 'grade6' => $grade6] = gradeRateScenario();

    // A 30-minute default slot: the page shows the 1,500/hr Grade 6 rate and
    // half of it as the lesson price, not the 1,200 subject override.
    $teacher->update(['lesson_duration_minutes' => 30]);

    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    TeacherAvailabilitySlot::factory()
        ->on($day->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();
    $student->studentProfile->update(['grade_id' => $grade6->id]);

    $this->actingAs($student)
        ->get(route('student.bookings.create', $teacher))
        ->assertOk()
        ->assertSee(platform_settings()->formatMinor(150000))
        ->assertSee(platform_settings()->formatMinor(75000));

    $this->actingAs($student)
        ->post(route('student.bookings.store', $teacher), [
            'subject_id' => $subject->id,
            'duration' => 60,
            'starts_at' => $day->setTime(18, 0)->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect((int) $booking->price_minor)->toBe(150000)
        ->and((int) $booking->learner_grade_id)->toBe((int) $grade6->id);
});
