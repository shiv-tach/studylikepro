<?php

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Review;
use App\Models\ServiceOffering;
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

/**
 * The dedicated teacher page is rendered by resources/js/student-teacher-profile,
 * so the Blade view only has to hand the Vue app a correct config island (see
 * `teacherProfileConfig()` in tests/Pest.php). These tests pin that contract
 * down: the grade scope, the subjects and rates, the services, the reviews and
 * the booking action.
 */
test('a student only sees their own grade on the dedicated teacher page', function () {
    $student = finderStudent(8);
    $teacher = gradeSplitTeacher();

    $response = $this->actingAs($student)
        ->get(route('student.teachers.show', $teacher))
        ->assertOk()
        // The Blade heading names the grade the page is scoped to.
        ->assertSee('Grade 8 lessons');

    $config = teacherProfileConfig($response);

    expect($config['grade'])->toBe(['label' => 'Grade 8']);
    expect($config['gradeScoped'])->toBeTrue();
    expect($config['subjects'])->toHaveCount(1);
    expect($config['subjects'][0]['gradeLabel'])->toBe('Grade 8');
    expect($config['subjects'][0]['lessons'])->toBe(['Grade Eight Numbers']);
    expect($config['subjects'][0]['rate'])->toBe(platform_settings()->formatMinor(40000));
    expect($config['booking']['rate'])->toBe(platform_settings()->formatMinor(40000));
    expect($config['booking']['bookUrl'])->toBe(route('student.bookings.create', $teacher));

    // Nothing priced for another grade leaks into the payload.
    expect(json_encode($config))->not->toContain(platform_settings()->formatMinor(90000));
});

test('a guest still sees every grade on the public teacher page', function () {
    $teacher = gradeSplitTeacher();

    // The public page lists the grades the teacher takes, each with its rate,
    // rather than the per-grade lesson lists the student workspace shows.
    $this->get(route('teachers.show', $teacher))
        ->assertOk()
        ->assertSee('Grade 8 · '.platform_settings()->formatMinor(40000).'/hr')
        ->assertSee('Grade 9 · '.platform_settings()->formatMinor(90000).'/hr')
        ->assertSee('Log in to book');
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

    $config = teacherProfileConfig(
        $this->actingAs($student)->get(route('student.teachers.show', $teacher))->assertOk()
    );

    expect($config['grade'])->toBe(['label' => 'Grade 13']);
    expect($config['gradeScoped'])->toBeFalse();
    expect($config['subjects'])->toBe([]);
    expect($config['copy']['gradeMismatch'])->toBe('Split Grades Teacher does not teach Grade 13 lessons yet.');
    expect($config['copy']['findGradeTeachers'])->toBe('Find Grade 13 teachers');
    expect($config['urls']['index'])->toBe(route('student.teachers.index'));

    // None of the lessons the teacher does run for other grades leak in.
    expect(json_encode($config))->not->toContain('Grade Eight Numbers');
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

    $config = teacherProfileConfig(
        $this->actingAs($student)->get(route('student.teachers.show', $teacher))->assertOk()
    );

    expect($config['reviews']['count'])->toBe(1);
    expect($config['reviews']['rating'])->toBe('5.0');
    expect($config['reviews']['summaryLabel'])->toBe('1 review');
    expect($config['reviews']['breakdown'][0])->toEqual(['label' => '5 ★', 'count' => 1, 'percent' => 100]);
    expect($config['reviews']['items'][0]['comment'])->toBe('Great grade 8 maths lesson.');
    expect($config['reviews']['items'][0]['rating'])->toBe(5);
    expect($config['reviews']['items'][0]['stars']['filled'])->toBe('★★★★★');
    expect($config['reviews']['items'][0]['stars']['empty'])->toBe('');
    expect($config['teacher']['rating'])->toBe('5.0');
});

test('the dedicated teacher page lists the teacher services grouped by type', function () {
    $student = finderStudent(8);
    $teacher = gradeSplitTeacher();

    $lesson = $teacher->lessons()->where('grade_id', gradeId(8))->firstOrFail();

    $lessonOffering = ServiceOffering::factory()
        ->for($teacher, 'teacherProfile')
        ->forType(ServiceType::Lesson)
        ->create([
            'subject_id' => $lesson->subject_id,
            'lesson_id' => $lesson->id,
            'grade_id' => $lesson->grade_id,
            'price_minor' => 120000,
        ]);

    $summaryOffering = ServiceOffering::factory()
        ->for($teacher, 'teacherProfile')
        ->forType(ServiceType::Summary)
        ->create([
            'subject_id' => $lesson->subject_id,
            'lesson_id' => $lesson->id,
            'grade_id' => $lesson->grade_id,
            'price_minor' => 35000,
        ]);

    $config = teacherProfileConfig(
        $this->actingAs($student)->get(route('student.teachers.show', $teacher))->assertOk()
    );

    expect($config['copy']['servicesTitle'])->toBe('Services & pricing');

    $groups = collect($config['serviceGroups'])->keyBy('key');
    $lessonGroup = $groups[ServiceType::Lesson->value];
    $summaryGroup = $groups[ServiceType::Summary->value];

    expect($lessonGroup['icon'].' '.$lessonGroup['label'])
        ->toBe(ServiceType::Lesson->icon().' '.ServiceType::Lesson->label());
    expect($summaryGroup['icon'].' '.$summaryGroup['label'])
        ->toBe(ServiceType::Summary->icon().' '.ServiceType::Summary->label());

    expect($lessonGroup['items'][0]['title'])->toBe('Grade Eight Numbers');
    expect($lessonGroup['items'][0]['price'])->toBe(platform_settings()->formatMinor(120000));
    expect($lessonGroup['items'][0]['action'])->toBe([
        'kind' => 'form',
        'label' => 'Buy',
        'url' => route('student.purchases.store', $lessonOffering),
    ]);

    expect($summaryGroup['items'][0]['price'])->toBe(platform_settings()->formatMinor(35000));
    expect($summaryGroup['items'][0]['action']['url'])->toBe(route('student.purchases.store', $summaryOffering));
});

test('the dedicated teacher page hides services for other grades', function () {
    $student = finderStudent(8);
    $teacher = gradeSplitTeacher();

    $grade9 = $teacher->lessons()->where('grade_id', gradeId(9))->firstOrFail();

    // Grade 9 offerings must not leak onto a grade 8 student's page.
    ServiceOffering::factory()
        ->for($teacher, 'teacherProfile')
        ->forType(ServiceType::Lesson)
        ->create([
            'subject_id' => $grade9->subject_id,
            'lesson_id' => $grade9->id,
            'grade_id' => $grade9->grade_id,
            'price_minor' => 777700,
        ]);

    ServiceOffering::factory()
        ->for($teacher, 'teacherProfile')
        ->forType(ServiceType::Summary)
        ->create([
            'subject_id' => $grade9->subject_id,
            'lesson_id' => $grade9->id,
            'grade_id' => $grade9->grade_id,
            'price_minor' => 666600,
        ]);

    // A grade-less subject-wide service shows when the teacher covers the
    // student's grade on that subject...
    ServiceOffering::factory()
        ->for($teacher, 'teacherProfile')
        ->forType(ServiceType::PaperMarking)
        ->create([
            'subject_id' => $grade9->subject_id,
            'lesson_id' => null,
            'grade_id' => null,
            'price_minor' => 555500,
        ]);

    // ...and stays hidden when the teacher only takes that subject for other grades.
    $science = Subject::factory()->create(['name' => 'Science', 'slug' => 'science']);
    $teacher->subjects()->attach($science->id, ['grade_levels' => [(string) gradeId(9)]]);

    ServiceOffering::factory()
        ->for($teacher, 'teacherProfile')
        ->forType(ServiceType::PaperMarking)
        ->create([
            'subject_id' => $science->id,
            'lesson_id' => null,
            'grade_id' => null,
            'price_minor' => 444400,
        ]);

    $config = teacherProfileConfig(
        $this->actingAs($student)->get(route('student.teachers.show', $teacher))->assertOk()
    );

    // Only the grade-less paper service the teacher covers grade 8 on survives;
    // the grade 9 rows and the other subject never reach the payload.
    $prices = collect($config['serviceGroups'])
        ->flatMap(fn (array $group) => collect($group['items'])->pluck('price'))
        ->all();

    expect($prices)->toBe([platform_settings()->formatMinor(555500)]);
});

/**
 * The finder list is rendered by resources/js/student-teacher-finder, so the
 * Blade view only has to hand the Vue app a correct config island (see
 * `teacherFinderConfig()` in tests/Pest.php). These tests pin that contract
 * down: the grade scope, the filter options, the cards and the route map.
 */
test('the finder island carries the grade scope, the filters and the route map', function () {
    $student = finderStudent(11);
    $grade = Grade::query()->whereKey(gradeId(11))->firstOrFail();

    $offered = Subject::factory()->create([
        'name' => 'Grade Eleven Mathematics',
        'slug' => 'grade-eleven-mathematics',
        'icon' => '🧮',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($offered)->create(['grade_id' => $grade->id, 'name' => 'Algebra']);

    $config = teacherFinderConfig(
        $this->actingAs($student)->get(route('student.teachers.index'))->assertOk()
    );

    // The profile grade is the one filter the student cannot change, so the
    // island names it and never repeats it as a query string field.
    expect($config['grade'])->toBe(['label' => 'Grade 11']);
    expect($config['theme']['preset'])->toBe('classic');
    expect($config['filters']['sort'])->toBe('available_soon');
    expect($config['subjectOptions'])->toContain(['value' => 'grade-eleven-mathematics', 'label' => '🧮 Grade Eleven Mathematics']);
    expect($config['languageOptions'])->toBe(['Sinhala', 'English', 'Tamil']);
    expect(collect($config['sortOptions'])->pluck('value')->all())->toBe(['available_soon', 'rating', 'price_low', 'price_high', 'newest']);
    expect($config['hasActiveFilters'])->toBeFalse();
    expect($config['hasDateFilter'])->toBeFalse();
    expect($config['errorMessage'])->toBeNull();
    expect($config['teachers'])->toBe([]);
    expect($config['totalLabel'])->toBe('0 teachers found');
    expect($config['urls'])->toBe([
        'index' => route('student.teachers.index'),
        'reset' => route('student.teachers.index'),
        'profile' => route('student.profile'),
    ]);
});

test('a finder card carries the grade rate, the rating and the next open slot', function () {
    $student = finderStudent(11);
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics', 'icon' => '📐']);

    $teacher = finderTeacher('Rated Teacher', 11, [
        'hourly_rate_minor' => 90000,
        'rating_avg' => 4.5,
        'rating_count' => 3,
    ], $maths);

    // The student's own grade rate wins over the teacher's headline rate.
    $teacher->subjects()->updateExistingPivot($maths->id, [
        'grade_rates' => [gradeId(11) => 45000],
    ]);

    $teacher->lessons()->attach(
        Lesson::factory()->for($maths)->create(['grade_id' => gradeId(11), 'name' => 'Algebra'])->id
    );

    TeacherAvailabilitySlot::factory()->on(2, '10:00', '11:00')->create(['teacher_profile_id' => $teacher->id]);

    $config = teacherFinderConfig(
        $this->actingAs($student)->get(route('student.teachers.index', ['subject' => 'mathematics']))->assertOk()
    );

    $card = collect($config['teachers'])->firstWhere('id', $teacher->id);

    expect($card['url'])->toBe(route('student.teachers.show', $teacher));
    expect($card['name'])->toBe('Rated Teacher');
    expect($card['initials'])->toBe('Ra');
    expect($card['rating'])->toBe('4.5');
    expect($card['ratingCount'])->toBe(3);
    expect($card['rate'])->toBe(platform_settings()->formatMinor(45000));
    expect($card['subjects'])->toHaveCount(1);
    expect($card['subjects'][0])->toContain('Mathematics');
    expect($card['extraSubjects'])->toBe(0);
    expect($card['metaLabel'])->toContain('Grade 11')->toContain('1 lesson');
    expect($card['availability']['label'])->toMatch('/^Next: \w{3} \d{2} \w{3} · \d{2}:\d{2}$/');
});

test('a finder card without a rating or an open slot says so', function () {
    $student = finderStudent(11);
    finderTeacher('Brand New Teacher', 11);

    $config = teacherFinderConfig(
        $this->actingAs($student)->get(route('student.teachers.index'))->assertOk()
    );

    $card = $config['teachers'][0];

    // No rating yet means the "new teacher" chip, and no weekly slot means the
    // card prints the no-slots sentence instead of a blank line.
    expect($card['rating'])->toBeNull();
    expect($card['ratingCount'])->toBe(0);
    expect($card['subjects'])->toHaveCount(1);
    expect($card['availability'])->toBeNull();
    expect($card['metaLabel'])->toContain('Grade 11');
    expect($card['metaLabel'])->not->toContain('lesson');
});

test('a picked day turns the card slot into "available" and counts as a filter', function () {
    $student = finderStudent(11);
    $tuesday = CarbonImmutable::parse('2026-06-02 00:00', 'UTC');

    $teacher = finderTeacher('Tuesday Evening Teacher', 11);
    TeacherAvailabilitySlot::factory()->on(2, '18:00', '20:00')->create(['teacher_profile_id' => $teacher->id]);

    $config = teacherFinderConfig(
        $this->actingAs($student)
            ->get(route('student.teachers.index', [
                'date' => $tuesday->toDateString(),
                'time_from' => '18:30',
            ]))
            ->assertOk()
    );

    expect($config['hasDateFilter'])->toBeTrue();
    expect($config['hasActiveFilters'])->toBeTrue();
    expect($config['filters']['date'])->toBe($tuesday->toDateString());
    expect($config['filters']['time_from'])->toBe('18:30');
    expect($config['teachers'][0]['availability']['label'])
        ->toMatch('/^Available \w{3} \d{2} \w{3} · \d{2}:\d{2}$/');
});

test('the finder island switches to the day-filtered empty state', function () {
    $student = finderStudent(11);
    finderTeacher('Monday Only Teacher', 11);

    $config = teacherFinderConfig(
        $this->actingAs($student)
            ->get(route('student.teachers.index', ['date' => '2026-06-02']))
            ->assertOk()
    );

    expect($config['teachers'])->toBe([]);
    expect($config['hasDateFilter'])->toBeTrue();
    expect($config['copy']['emptyWithDate'])->toContain('widen the time window');
    expect($config['copy']['emptyBody'])->toContain('clear the filters');
});

test('the student\'s own theme preset reaches the finder island', function () {
    $student = finderStudent(11);
    $student->update(['theme' => ['preset' => 'glass', 'accent' => 'indigo', 'mode' => 'light', 'sidebarStyle' => 'flat']]);

    $config = teacherFinderConfig(
        $this->actingAs($student->fresh())->get(route('student.teachers.index'))->assertOk()
    );

    expect($config['theme']['preset'])->toBe('glass');
});
