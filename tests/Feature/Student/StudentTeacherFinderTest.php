<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Review;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\TeacherStatsService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // A Monday morning: "tomorrow" is a stable Tuesday and every slot below
    // stays in the future.
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00', 'UTC'));
});

/**
 * A student with a completed profile at the given grade.
 */
function finderStudent(int $gradeNumber): User
{
    $user = User::factory()->student()->onboarded()->create();
    $user->studentProfile->update(['grade_id' => gradeId($gradeNumber)]);

    return $user->fresh();
}

/**
 * An approved teacher who takes the given grade on a subject.
 */
function finderTeacher(string $name, int $gradeNumber, array $profileAttributes = [], ?Subject $subject = null): TeacherProfile
{
    $user = User::factory()->teacher()->create(['name' => $name]);
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $user->id,
        ...$profileAttributes,
    ]);

    $teacher->subjects()->attach(($subject ?? Subject::factory()->create())->id, [
        'grade_levels' => [(string) gradeId($gradeNumber)],
    ]);

    return $teacher;
}

test('the finder is only for signed-in onboarded students', function () {
    $this->get(route('student.teachers.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.teachers.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.teachers.index'))
        ->assertRedirect(route('student.onboarding.show'));
});

test('only approved teachers who take the student grade are listed', function () {
    $student = finderStudent(11);
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    finderTeacher('Eleven Maths Teacher', 11, subject: $maths);
    finderTeacher('Thirteen Maths Teacher', 13, subject: $maths);

    $pending = User::factory()->teacher()->create(['name' => 'Pending Eleven Teacher']);
    $pendingProfile = TeacherProfile::factory()->pending()->create(['user_id' => $pending->id]);
    $pendingProfile->subjects()->attach($maths->id, ['grade_levels' => [(string) gradeId(11)]]);

    $this->actingAs($student)
        ->get(route('student.teachers.index'))
        ->assertOk()
        ->assertSee('Eleven Maths Teacher')
        ->assertDontSee('Thirteen Maths Teacher')
        ->assertDontSee('Pending Eleven Teacher')
        ->assertSee('Grade 11');
});

test('the grade cannot be overridden from the query string', function () {
    $student = finderStudent(11);
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    finderTeacher('Thirteen Maths Teacher', 13, subject: $maths);

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['grade' => gradeId(13)]))
        ->assertOk()
        ->assertDontSee('Thirteen Maths Teacher');
});

test('the subject picker offers the subjects the student grade runs', function () {
    $student = finderStudent(11);
    $grade = Grade::query()->whereKey(gradeId(11))->firstOrFail();

    $offered = Subject::factory()->create([
        'name' => 'Grade Eleven Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($offered)->create(['grade_id' => $grade->id, 'name' => 'Algebra']);

    $otherLevel = EducationLevel::factory()->create();
    Subject::factory()->create(['name' => 'Grade Thirteen Physics', 'education_level_id' => $otherLevel->id]);

    $this->actingAs($student)
        ->get(route('student.teachers.index'))
        ->assertOk()
        ->assertSee('Grade Eleven Mathematics')
        ->assertDontSee('Grade Thirteen Physics');
});

test('the simple filters narrow the list by subject and language', function () {
    $student = finderStudent(11);
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $science = Subject::factory()->create(['name' => 'Science', 'slug' => 'science']);

    finderTeacher('Sinhala Maths Teacher', 11, ['languages' => ['Sinhala']], $maths);
    finderTeacher('Tamil Science Teacher', 11, ['languages' => ['Tamil']], $science);

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['subject' => 'mathematics']))
        ->assertOk()
        ->assertSee('Sinhala Maths Teacher')
        ->assertDontSee('Tamil Science Teacher');

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['language' => 'Tamil']))
        ->assertOk()
        ->assertSee('Tamil Science Teacher')
        ->assertDontSee('Sinhala Maths Teacher');
});

test('the date and time filters only keep teachers who are genuinely free then', function () {
    $student = finderStudent(11);
    $tuesday = CarbonImmutable::parse('2026-06-02 00:00', 'UTC');

    $mondayOnly = finderTeacher('Monday Only Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(1, '18:00', '20:00')->create(['teacher_profile_id' => $mondayOnly->id]);

    $tuesdayEvening = finderTeacher('Tuesday Evening Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(2, '18:00', '20:00')->create(['teacher_profile_id' => $tuesdayEvening->id]);

    $tuesdayMorning = finderTeacher('Tuesday Morning Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(2, '07:00', '09:00')->create(['teacher_profile_id' => $tuesdayMorning->id]);

    $this->actingAs($student)
        ->get(route('student.teachers.index', [
            'date' => $tuesday->toDateString(),
            'time_from' => '18:30',
            'time_to' => '19:30',
        ]))
        ->assertOk()
        ->assertSee('Tuesday Evening Teacher')
        ->assertDontSee('Monday Only Teacher')
        ->assertDontSee('Tuesday Morning Teacher');

    // A lesson already booked takes the teacher out of the same window.
    $booked = finderTeacher('Tuesday Booked Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(2, '18:00', '20:00')->create(['teacher_profile_id' => $booked->id]);

    Booking::factory()->create([
        'teacher_profile_id' => $booked->id,
        'starts_at' => CarbonImmutable::parse('2026-06-02 18:00', 'Asia/Colombo')->utc(),
        'ends_at' => CarbonImmutable::parse('2026-06-02 20:00', 'Asia/Colombo')->utc(),
    ]);

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['date' => $tuesday->toDateString()]))
        ->assertOk()
        ->assertSee('Tuesday Evening Teacher')
        ->assertDontSee('Tuesday Booked Teacher');
});

test('a time window needs a date and a past date is rejected', function () {
    $student = finderStudent(11);

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['time_from' => '18:00']))
        ->assertSessionHasErrors('date');

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['date' => '2026-05-31']))
        ->assertSessionHasErrors('date');
});

test('the default order puts the teachers with the earliest open slot first', function () {
    $student = finderStudent(11);

    $thursday = finderTeacher('Thursday Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(4, '10:00', '11:00')->create(['teacher_profile_id' => $thursday->id]);

    $noSlots = finderTeacher('No Slots Teacher', 11);

    $tuesday = finderTeacher('Tuesday Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(2, '10:00', '11:00')->create(['teacher_profile_id' => $tuesday->id]);

    $this->actingAs($student)
        ->get(route('student.teachers.index'))
        ->assertOk()
        ->assertSeeInOrder(['Tuesday Teacher', 'Thursday Teacher', 'No Slots Teacher']);

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['sort' => 'available_soon']))
        ->assertOk()
        ->assertSeeInOrder(['Tuesday Teacher', 'Thursday Teacher', 'No Slots Teacher']);
});

test('sorting by rating still works', function () {
    $student = finderStudent(11);

    finderTeacher('Low Rated Teacher', 11, ['rating_avg' => 4.1, 'rating_count' => 5]);
    finderTeacher('High Rated Teacher', 11, ['rating_avg' => 4.9, 'rating_count' => 9]);

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['sort' => 'rating']))
        ->assertOk()
        ->assertSeeInOrder(['High Rated Teacher', 'Low Rated Teacher']);
});

test('the availability ordering keeps the filters in its pagination links', function () {
    $student = finderStudent(11);
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    foreach (range(1, 10) as $index) {
        $teacher = finderTeacher('Slot Teacher '.$index, 11, subject: $maths);
        TeacherAvailabilitySlot::factory()->on(2, '10:00', '11:00')->create(['teacher_profile_id' => $teacher->id]);
    }

    $this->actingAs($student)
        ->get(route('student.teachers.index', ['subject' => 'mathematics']))
        ->assertOk()
        ->assertSee('page=2', false)
        ->assertSee('subject=mathematics', false);
});

test('cards price the lesson with the student grade rate', function () {
    $student = finderStudent(11);
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    $teacher = finderTeacher('Grade Rate Teacher', 11, ['hourly_rate_minor' => 90000], $maths);
    $teacher->subjects()->updateExistingPivot($maths->id, [
        'grade_rates' => [gradeId(11) => 45000],
    ]);

    $this->actingAs($student)
        ->get(route('student.teachers.index'))
        ->assertOk()
        ->assertSee(platform_settings()->formatMinor(45000));
});

/**
 * A maths teacher covering Grades 8 and 9 with one lesson per grade, and a
 * Grade 8-only rate. Grade 8 is the O/L grade a scoped student sees.
 */
function gradeSplitTeacher(): TeacherProfile
{
    $grade8 = Grade::query()->whereKey(gradeId(8))->firstOrFail();

    $maths = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'education_level_id' => $grade8->education_level_id,
    ]);

    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create(['name' => 'Split Grades Teacher'])->id,
        'hourly_rate_minor' => 90000,
    ]);

    $teacher->subjects()->attach($maths->id, [
        'grade_levels' => [(string) gradeId(8), (string) gradeId(9)],
        'grade_rates' => [gradeId(8) => 40000],
    ]);

    $teacher->lessons()->attach([
        Lesson::factory()->for($maths)->create(['grade_id' => gradeId(8), 'name' => 'Grade Eight Numbers'])->id,
        Lesson::factory()->for($maths)->create(['grade_id' => gradeId(9), 'name' => 'Grade Nine Numbers'])->id,
    ]);

    return $teacher;
}

test('a student only sees their own grade on the dedicated teacher page', function () {
    $student = finderStudent(8);
    $teacher = gradeSplitTeacher();

    $this->actingAs($student)
        ->get(route('student.teachers.show', $teacher))
        ->assertOk()
        ->assertSee('Grade 8 lessons')
        ->assertSee('Grade Eight Numbers')
        ->assertDontSee('Grade Nine Numbers')
        ->assertSee(platform_settings()->formatMinor(40000))
        ->assertDontSee(platform_settings()->formatMinor(90000))
        ->assertSee(route('student.bookings.create', $teacher), false);
});

test('a guest still sees every grade on the public teacher page', function () {
    $teacher = gradeSplitTeacher();

    $this->get(route('teachers.show', $teacher))
        ->assertOk()
        ->assertSee('Grade Eight Numbers')
        ->assertSee('Grade Nine Numbers')
        ->assertSee('Log in to request a lesson');
});

test('students are redirected from the public teacher pages to their own', function () {
    $student = finderStudent(8);
    $teacher = gradeSplitTeacher();

    $this->actingAs($student)
        ->get(route('teachers.show', $teacher))
        ->assertRedirect(route('student.teachers.show', $teacher));

    $this->actingAs($student)
        ->get(route('teachers.index'))
        ->assertRedirect(route('student.teachers.index'));
});

test('a teacher who does not take the student grade shows a notice instead of lessons', function () {
    $student = finderStudent(13);
    $teacher = gradeSplitTeacher();

    $this->actingAs($student)
        ->get(route('student.teachers.show', $teacher))
        ->assertOk()
        ->assertSee('does not teach Grade 13 lessons yet')
        ->assertDontSee('Grade Eight Numbers')
        ->assertSee(route('student.teachers.index'), false);
});

test('the finder links to the dedicated student teacher page', function () {
    $student = finderStudent(11);
    $teacher = finderTeacher('Linked Teacher', 11);

    $this->actingAs($student)
        ->get(route('student.teachers.index'))
        ->assertOk()
        ->assertSee(route('student.teachers.show', $teacher), false);
});

test('only students can open the workspace teacher page', function () {
    $teacher = gradeSplitTeacher();

    $this->get(route('student.teachers.show', $teacher))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.teachers.show', $teacher))
        ->assertForbidden();
});

test('the dedicated teacher page lists reviews and the rating', function () {
    $student = finderStudent(8);
    $teacher = gradeSplitTeacher();

    $booking = Booking::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'status' => BookingStatus::Completed,
    ]);

    Review::factory()->create([
        'booking_id' => $booking->id,
        'student_id' => $booking->student_id,
        'teacher_profile_id' => $teacher->id,
        'rating' => 5,
        'comment' => 'Great grade 8 maths lesson.',
    ]);

    app(TeacherStatsService::class)->refresh($teacher);

    $this->actingAs($student)
        ->get(route('student.teachers.show', $teacher))
        ->assertOk()
        ->assertSee('Great grade 8 maths lesson.')
        ->assertSee('5 ★');
});
