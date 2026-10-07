<?php

use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use App\Models\Topic;
use App\Models\User;

function approvedTeacher(string $name, array $profileAttributes = []): TeacherProfile
{
    $user = User::factory()->teacher()->create(['name' => $name]);

    return TeacherProfile::factory()->approved()->create([
        'user_id' => $user->id,
        ...$profileAttributes,
    ]);
}

it('lists only approved teachers', function () {
    approvedTeacher('Priya Approved');
    approvedTeacher('Draft Teacher', ['verification_status' => 'draft']);
    approvedTeacher('Pending Teacher', ['verification_status' => 'pending']);

    $this->get(route('teachers.index'))
        ->assertOk()
        ->assertSee('Priya Approved')
        ->assertDontSee('Draft Teacher')
        ->assertDontSee('Pending Teacher');
});

it('filters teachers by subject and topic', function () {
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $algebra = Topic::factory()->create(['subject_id' => $maths->id, 'name' => 'Algebra', 'slug' => 'algebra']);

    $mathsTeacher = approvedTeacher('Maths Teacher');
    $mathsTeacher->subjects()->attach($maths->id, ['grade_levels' => ['high_school']]);
    $mathsTeacher->topics()->attach($algebra->id);

    $other = approvedTeacher('English Teacher');
    $english = Subject::factory()->create(['name' => 'English', 'slug' => 'english']);
    $other->subjects()->attach($english->id, ['grade_levels' => []]);

    $this->get(route('teachers.index', ['subject' => 'mathematics']))
        ->assertOk()
        ->assertSee('Maths Teacher')
        ->assertDontSee('English Teacher');

    $this->get(route('teachers.index', ['topic' => 'algebra']))
        ->assertOk()
        ->assertSee('Maths Teacher')
        ->assertDontSee('English Teacher');
});

it('filters teachers by grade level and language', function () {
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    $highSchool = approvedTeacher('High School Tutor', ['languages' => ['English']]);
    $highSchool->subjects()->attach($maths->id, ['grade_levels' => ['high_school']]);

    $college = User::factory()->teacher()->create(['name' => 'College Tutor']);
    $collegeProfile = TeacherProfile::factory()->approved()->create([
        'user_id' => $college->id,
        'languages' => ['Tamil'],
    ]);
    $collegeProfile->subjects()->attach($maths->id, ['grade_levels' => ['college']]);

    $this->get(route('teachers.index', ['grade_level' => 'high_school']))
        ->assertOk()
        ->assertSee('High School Tutor')
        ->assertDontSee('College Tutor');

    $this->get(route('teachers.index', ['language' => 'Tamil']))
        ->assertOk()
        ->assertSee('College Tutor')
        ->assertDontSee('High School Tutor');
});

it('filters teachers by price range using the subject override when a subject is chosen', function () {
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    $cheap = approvedTeacher('Cheap Override Teacher', ['hourly_rate_minor' => 80000]);
    $cheap->subjects()->attach($maths->id, ['grade_levels' => [], 'rate_per_hour_minor' => 40000]);

    $pricey = approvedTeacher('Pricey Teacher', ['hourly_rate_minor' => 90000]);
    $pricey->subjects()->attach($maths->id, ['grade_levels' => []]);

    $this->get(route('teachers.index', ['subject' => 'mathematics', 'max_rate' => 500]))
        ->assertOk()
        ->assertSee('Cheap Override Teacher')
        ->assertDontSee('Pricey Teacher');

    $this->get(route('teachers.index', ['min_rate' => 850]))
        ->assertOk()
        ->assertSee('Pricey Teacher')
        ->assertDontSee('Cheap Override Teacher');
});

it('filters teachers by weekday and time window', function () {
    $evening = approvedTeacher('Evening Teacher');
    TeacherAvailabilitySlot::factory()->on(1, '18:00', '21:00')->create(['teacher_profile_id' => $evening->id]);

    $morning = approvedTeacher('Morning Teacher');
    TeacherAvailabilitySlot::factory()->on(3, '07:00', '09:00')->create(['teacher_profile_id' => $morning->id]);

    $this->get(route('teachers.index', ['weekday' => 1, 'time_from' => '19:00', 'time_to' => '20:00']))
        ->assertOk()
        ->assertSee('Evening Teacher')
        ->assertDontSee('Morning Teacher');
});

it('sorts teachers by rating, price, and newest', function () {
    approvedTeacher('Cheap Low Rating', ['hourly_rate_minor' => 30000, 'rating_avg' => 4.1, 'rating_count' => 5]);
    approvedTeacher('Expensive High Rating', ['hourly_rate_minor' => 90000, 'rating_avg' => 4.9, 'rating_count' => 9]);
    approvedTeacher('Newest Teacher', ['hourly_rate_minor' => 60000]);

    $this->get(route('teachers.index', ['sort' => 'price_low']))
        ->assertOk()
        ->assertSeeInOrder(['Cheap Low Rating', 'Newest Teacher', 'Expensive High Rating']);

    $this->get(route('teachers.index', ['sort' => 'price_high']))
        ->assertOk()
        ->assertSeeInOrder(['Expensive High Rating', 'Newest Teacher', 'Cheap Low Rating']);

    $this->get(route('teachers.index', ['sort' => 'rating']))
        ->assertOk()
        ->assertSeeInOrder(['Expensive High Rating', 'Cheap Low Rating']);

    $this->get(route('teachers.index', ['sort' => 'newest']))
        ->assertOk()
        ->assertSeeInOrder(['Newest Teacher', 'Expensive High Rating', 'Cheap Low Rating']);
});

it('paginates results and keeps the filters in the links', function () {
    foreach (range(1, 10) as $index) {
        approvedTeacher('Teacher '.$index, ['hourly_rate_minor' => 40000 + $index]);
    }

    $this->get(route('teachers.index', ['sort' => 'price_low']))
        ->assertOk()
        ->assertSee('?sort=price_low&amp;page=2', false);

    $this->get(route('teachers.index', ['sort' => 'price_low', 'page' => 2]))
        ->assertOk()
        ->assertSee('Teacher 10');
});

it('rejects unknown filter values', function () {
    $this->get(route('teachers.index', ['subject' => 'not-a-subject']))
        ->assertSessionHasErrors('subject');
});

it('shows an approved teacher profile with rates and availability', function () {
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $profile = approvedTeacher('Priya Verma', [
        'headline' => 'CBSE Mathematics specialist',
        'hourly_rate_minor' => 70000,
        'languages' => ['English', 'Sinhala'],
        'timezone' => 'Asia/Colombo',
    ]);
    $profile->subjects()->attach($maths->id, ['grade_levels' => ['high_school'], 'rate_per_hour_minor' => 80000]);
    TeacherAvailabilitySlot::factory()->on(1, '18:00', '20:00')->create(['teacher_profile_id' => $profile->id]);
    TeacherTimeOff::factory()->create(['teacher_profile_id' => $profile->id]);

    $this->get(route('teachers.show', $profile))
        ->assertOk()
        ->assertSee('Priya Verma')
        ->assertSee('CBSE Mathematics specialist')
        ->assertSee(platform_settings()->formatMinor(80000))
        ->assertSee('High school')
        ->assertSee('Your time (Asia/Colombo)')
        ->assertSee('18:00');
});

it('hides teacher profiles that are not approved', function () {
    $draft = TeacherProfile::factory()->create();
    $pending = TeacherProfile::factory()->pending()->create();

    $this->get(route('teachers.show', $draft))->assertNotFound();
    $this->get(route('teachers.show', $pending))->assertNotFound();
});

it('links students to the teacher directory from their dashboard navigation', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertSee('Find a teacher');
});
