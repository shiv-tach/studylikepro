<?php

use App\Enums\ServiceType;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\ServiceOffering;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

test('the public catalog starts with the four education levels', function () {
    $this->seed(CatalogSeeder::class);

    // Subjects stay hidden until a level and a grade are chosen.
    $this->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertSee('Choose your level')
        ->assertSee('Primary')
        ->assertSee('O/L')
        ->assertSee('A/L')
        ->assertSee('Other')
        ->assertDontSee('Environmental Studies')
        ->assertDontSee('Commerce');
});

test('choosing a level reveals its grades', function () {
    $this->seed(CatalogSeeder::class);

    $this->get(route('catalog.subjects.index', ['level' => 'ol']))
        ->assertOk()
        ->assertSee('Choose your grade')
        ->assertSee('Grade 6')
        ->assertSee('Grade 11')
        ->assertDontSee('Grade 12');

    // An unknown level falls back to the level list.
    $this->get(route('catalog.subjects.index', ['level' => 'not-a-level']))
        ->assertOk()
        ->assertDontSee('Choose your grade');
});

test('choosing a level and grade reveals the subjects that run in that grade', function () {
    $this->seed(CatalogSeeder::class);

    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $ol->id)->where('number', 6)->firstOrFail();
    $grade10 = Grade::query()->where('education_level_id', $ol->id)->where('number', 10)->firstOrFail();

    // Grade 10: Commerce runs here - lesson counts are scoped to the grade.
    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade10->id]))
        ->assertOk()
        ->assertSee('Commerce')
        ->assertSee('8 lessons')
        ->assertDontSee('Environmental Studies');

    // Grade 6: Commerce does not run yet.
    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade6->id]))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('12 lessons')
        ->assertDontSee('Commerce');
});

test('subjects without active lessons in the chosen grade stay hidden', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade = Grade::query()->where('education_level_id', $level->id)->where('number', 10)->firstOrFail();

    $visible = Subject::factory()->create(['name' => 'Visible Subject', 'education_level_id' => $level->id]);
    Lesson::factory()->for($visible)->create(['grade_id' => $grade->id]);

    $quiet = Subject::factory()->create(['name' => 'Quiet Subject', 'education_level_id' => $level->id]);
    Lesson::factory()->for($quiet)->inactive()->create(['grade_id' => $grade->id]);

    Subject::factory()->inactive()->create(['name' => 'Hidden Subject', 'education_level_id' => $level->id]);

    $otherGrade = Grade::query()->where('education_level_id', $level->id)->where('number', 9)->firstOrFail();
    $elsewhere = Subject::factory()->create(['name' => 'Elsewhere Subject', 'education_level_id' => $level->id]);
    Lesson::factory()->for($elsewhere)->create(['grade_id' => $otherGrade->id]);

    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade->id]))
        ->assertOk()
        ->assertSee('Visible Subject')
        ->assertSee('1 lesson')
        ->assertDontSee('Quiet Subject')
        ->assertDontSee('Hidden Subject')
        ->assertDontSee('Elsewhere Subject');
});

test('a subject page lists its active lessons', function () {
    $subject = Subject::factory()->create(['name' => 'Physics']);
    Lesson::factory()->for($subject)->create(['name' => 'Mechanics']);
    Lesson::factory()->for($subject)->inactive()->create(['name' => 'Secret Lesson']);

    $this->get(route('catalog.subjects.show', $subject))
        ->assertOk()
        ->assertSee('Mechanics')
        ->assertDontSee('Secret Lesson');
});

test('opening a subject from a grade drill-down lists only that grade', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $level->id)->where('number', 6)->firstOrFail();
    $grade7 = Grade::query()->where('education_level_id', $level->id)->where('number', 7)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade6->id, 'name' => 'Fractions']);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade7->id, 'name' => 'Algebra']);

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade6->id]))
        ->assertOk()
        ->assertSee('Fractions')
        ->assertDontSee('Algebra')
        ->assertSee('View all grades');

    // A grade that does not belong to the subject's level is ignored.
    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => gradeId(12)]))
        ->assertOk()
        ->assertSee('Fractions')
        ->assertSee('Algebra');
});

test('the subject page lists its verified teachers with the subject rate', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $level->id)->where('number', 6)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'English', 'slug' => 'english', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade6->id]);

    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create(['name' => 'Priya Perera'])->id,
        'hourly_rate_minor' => 90000,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $grade6->id]]);

    TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create(['name' => 'Unrelated Tutor'])->id,
    ]);

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade6->id]))
        ->assertOk()
        ->assertSee('Teachers who teach English in Grade 6')
        ->assertSee('Priya Perera')
        ->assertSee('RS: 900.00')
        ->assertSee('No slots in the next 7 days')
        ->assertDontSee('Unrelated Tutor');
});

test('the teacher list on a subject page follows the selected grade', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $level->id)->where('number', 6)->firstOrFail();
    $grade11 = Grade::query()->where('education_level_id', $level->id)->where('number', 11)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Science', 'slug' => 'science', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade11->id]);

    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create(['name' => 'Senior Science Teacher'])->id,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $grade11->id]]);

    // No grade chosen: the teacher is offered for the whole subject.
    $this->get(route('catalog.subjects.show', $subject))
        ->assertOk()
        ->assertSee('Senior Science Teacher');

    // Grade 6 is not covered by this teacher.
    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade6->id]))
        ->assertOk()
        ->assertSee('No verified teachers')
        ->assertDontSee('Senior Science Teacher');
});

test('the subject page links to the directory filtered by subject and grade', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('education_level_id', $level->id)->where('number', 6)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'English', 'slug' => 'english', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade6->id]);

    foreach (range(1, 7) as $index) {
        $teacher = TeacherProfile::factory()->approved()->create([
            'user_id' => User::factory()->teacher()->create(['name' => "English Tutor {$index}"])->id,
        ]);
        $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $grade6->id]]);
    }

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade6->id]))
        ->assertOk()
        ->assertSee('See all 7 teachers')
        ->assertSee(route('teachers.index', ['subject' => 'english', 'grade' => $grade6->id]));
});

test('teacher cards on a subject page offer booking for the shown grade', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade7 = Grade::query()->where('education_level_id', $level->id)->where('number', 7)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'English', 'slug' => 'english', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade7->id]);

    $teacher = TeacherProfile::factory()->approved()->create();
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $grade7->id]]);

    $url = route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade7->id]);

    // Guests are sent to the create-account action instead of the profile.
    $this->get($url)
        ->assertOk()
        ->assertSee('Book now')
        ->assertDontSee('View profile')
        ->assertSee(route('register', ['role' => 'student']));

    // Signed-in students are kept off the public page: their workspace page
    // offers booking with the subject preselected.
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get($url)
        ->assertRedirect(route('student.subjects.show', ['subject' => $subject->slug, 'grade' => $grade7->id]));

    $this->actingAs($student)
        ->get(route('student.subjects.show', ['subject' => $subject, 'grade' => $grade7->id]))
        ->assertOk()
        ->assertSee('Book now')
        ->assertSee(route('student.bookings.create', [
            'teacherProfile' => $teacher,
            'subject_id' => $subject->id,
        ]));

    // Teachers cannot book lessons, so they keep the profile CTA.
    $this->actingAs(User::factory()->teacher()->create())
        ->get($url)
        ->assertOk()
        ->assertDontSee('Book now')
        ->assertSee('View profile');
});

test('inactive subjects are not publicly accessible', function () {
    $subject = Subject::factory()->inactive()->create();

    $this->get(route('catalog.subjects.show', $subject))->assertNotFound();
});

test('the catalog seeder is idempotent', function () {
    $this->seed(CatalogSeeder::class);

    $subjects = Subject::query()->count();
    $lessons = Lesson::query()->count();
    $baskets = SubjectBasket::query()->count();

    $this->seed(CatalogSeeder::class);

    expect(Subject::query()->count())->toBe($subjects)
        ->and(Lesson::query()->count())->toBe($lessons)
        ->and(SubjectBasket::query()->count())->toBe($baskets)
        ->and($subjects)->toBeGreaterThan(0)
        ->and($lessons)->toBeGreaterThan(0);
});

test('the catalog seeder builds the o/l basket subjects', function () {
    $this->seed(CatalogSeeder::class);

    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade6 = Grade::query()->where('number', 6)->firstOrFail();
    $grade10 = Grade::query()->where('number', 10)->firstOrFail();

    $baskets = SubjectBasket::query()
        ->where('education_level_id', $ol->id)
        ->ordered()
        ->pluck('id', 'key');

    expect($baskets->keys()->all())->toBe(['category_1', 'category_2', 'category_3']);

    // Category I runs from Grade 6 (the junior grades pick from that basket
    // alone) and Categories II and III from Grade 10.
    $ranges = SubjectBasket::query()
        ->where('education_level_id', $ol->id)
        ->get()
        ->mapWithKeys(fn (SubjectBasket $basket) => [$basket->key => $basket->gradeNumbers()]);

    expect($ranges['category_1'])->toBe([6, 7, 8, 9, 10, 11])
        ->and($ranges['category_2'])->toBe([10, 11])
        ->and($ranges['category_3'])->toBe([10, 11]);

    // An aesthetic Category I subject runs in both ends of the level, while a
    // literature subject stays in the O/L Grades 10-11.
    $art = Subject::query()->where('slug', 'art')->first();
    $literature = Subject::query()->where('slug', 'english-literature')->first();

    expect($art)->not->toBeNull()
        ->and($art->basket_id)->toBe($baskets['category_1'])
        ->and($art->lessons()->where('grade_id', $grade6->id)->count())->toBeGreaterThan(0)
        ->and($art->lessons()->where('grade_id', $grade10->id)->count())->toBeGreaterThan(0)
        ->and($literature)->not->toBeNull()
        ->and($literature->basket_id)->toBe($baskets['category_1'])
        ->and($literature->lessons()->where('grade_id', $grade6->id)->count())->toBe(0);

    // Existing O/L subjects map onto their basket, and mandatory subjects stay
    // outside every basket.
    expect(Subject::query()->where('slug', 'geography')->firstOrFail()->basket_id)->toBe($baskets['category_3'])
        ->and(Subject::query()->where('slug', 'ict')->firstOrFail()->basket_id)->toBe($baskets['category_2'])
        ->and(Subject::query()->where('slug', 'health-physical-education')->firstOrFail()->basket_id)->toBe($baskets['category_2'])
        ->and(Subject::query()->where('slug', 'commerce')->firstOrFail()->basket_id)->toBe($baskets['category_3'])
        ->and(Subject::query()->where('slug', 'ol-mathematics')->firstOrFail()->basket_id)->toBeNull();
});

test('the catalog seeder builds the grade by grade curriculum', function () {
    $this->seed(CatalogSeeder::class);

    $mathematics = Subject::query()
        ->where('slug', 'ol-mathematics')
        ->first();

    $grade6 = Grade::query()->where('number', 6)->first();
    $grade7 = Grade::query()->where('number', 7)->first();

    expect($mathematics)->not->toBeNull()
        ->and($mathematics->educationLevel->key)->toBe('ol')
        ->and($mathematics->lessons()->where('grade_id', $grade6->id)->count())->toBe(12)
        ->and($mathematics->lessons()->where('grade_id', $grade7->id)->count())->toBe(10);

    // Slugs are the {subject} route key, so the seeder must never create two
    // subjects with the same one — same-named subjects get a level prefix.
    $duplicateSlugs = Subject::query()
        ->select('slug')
        ->groupBy('slug')
        ->havingRaw('count(*) > 1')
        ->count();

    expect($duplicateSlugs)->toBe(0);
});

test('the catalog step three groups a basket grade by basket', function () {
    $this->seed(CatalogSeeder::class);

    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade10 = Grade::query()->where('education_level_id', $ol->id)->where('number', 10)->firstOrFail();
    $grade7 = Grade::query()->where('education_level_id', $ol->id)->where('number', 7)->firstOrFail();

    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade10->id]))
        ->assertOk()
        ->assertSee('Compulsory subjects')
        ->assertSee('Category I')
        ->assertSee('Category II')
        ->assertSee('Category III')
        ->assertSee('pick one')
        ->assertSee('Aesthetic, music, dancing, literature and drama subjects')
        ->assertSee('Art')
        ->assertSee('Mathematics');

    // The junior grades pick their Category I subject alone: the other two
    // baskets start in Grade 10, so their subjects stay compulsory here.
    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade7->id]))
        ->assertOk()
        ->assertSee('Compulsory subjects')
        ->assertSee('Category I')
        ->assertSee('Art')
        ->assertSee('ICT')
        ->assertDontSee('Category II')
        ->assertDontSee('Category III');
});

test('a basket grade with no basket subjects stays flat and names the basket grades', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade7 = Grade::query()->where('education_level_id', $ol->id)->where('number', 7)->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Grade Seven Science',
        'education_level_id' => $ol->id,
    ]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade7->id]);

    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade7->id]))
        ->assertOk()
        ->assertSee('Every subject below is compulsory in Grade 7')
        ->assertSee('begin in Grades 6-11')
        ->assertSee('Grade Seven Science')
        ->assertDontSee('Compulsory subjects')
        ->assertDontSee('pick one');
});

test('a basket subject page shows its category', function () {
    $this->seed(CatalogSeeder::class);

    $art = Subject::query()->where('slug', 'art')->firstOrFail();

    $this->get(route('catalog.subjects.show', $art))
        ->assertOk()
        ->assertSee('Category I')
        ->assertSee('pick one');
});

test('the interests page shows compulsory subjects and only the picked basket subjects', function () {
    $this->seed(CatalogSeeder::class);

    $grade10 = Grade::query()->where('number', 10)->firstOrFail();
    $art = Subject::query()->where('slug', 'art')->firstOrFail();
    $ict = Subject::query()->where('slug', 'ict')->firstOrFail();

    $student = User::factory()->student()->onboarded()->create();
    $student->studentProfile->update(['grade_id' => $grade10->id]);
    $student->interestedSubjects()->sync([$art->id]);

    $this->actingAs($student)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee('Compulsory subjects')
        ->assertSee('Category I')
        ->assertSee('View lessons and teachers')
        // Compulsory subjects and the picked basket subject open the student's
        // dedicated subject page, scoped to the student's own grade.
        ->assertSee(route('student.subjects.show', ['subject' => 'ol-mathematics', 'grade' => $grade10->id]))
        ->assertSee(route('student.subjects.show', ['subject' => 'art', 'grade' => $grade10->id]))
        // Only the picked basket subjects appear — the unpicked options stay
        // off this page.
        ->assertDontSee('Category II')
        ->assertDontSee($ict->name)
        // The page is no longer a picker: no checkboxes or lesson list.
        ->assertDontSee('name="subjects"', false)
        ->assertDontSee('name="lessons"', false);
});

test('a subject page sells the services teachers offer for it', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade9 = Grade::query()->where('education_level_id', $level->id)->where('number', 9)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'ol-mathematics', 'education_level_id' => $level->id]);
    $lesson = Lesson::factory()->for($subject)->create(['grade_id' => $grade9->id, 'name' => 'Algebra']);

    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create(['name' => 'Nimal Silva'])->id,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $grade9->id]]);

    ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::Lesson)->create([
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'grade_id' => $grade9->id,
        'title' => 'Algebra booster plan',
        'price_minor' => 125000,
    ]);

    ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::PaperMarking)->create([
        'subject_id' => $subject->id,
        'title' => 'Mark my past paper',
        'price_minor' => 30000,
    ]);

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade9->id]))
        ->assertOk()
        ->assertSee('Services for Mathematics in Grade 9')
        ->assertSee('Lesson plans')
        ->assertSee('Algebra booster plan')
        ->assertSee('Mark my past paper')
        ->assertSee(platform_settings()->formatMinor(125000))
        ->assertSee(platform_settings()->formatMinor(30000))
        ->assertSee('Nimal Silva');
});

test('subject services hide other grades, inactive offers and unapproved teachers', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade9 = Grade::query()->where('education_level_id', $level->id)->where('number', 9)->firstOrFail();
    $grade10 = Grade::query()->where('education_level_id', $level->id)->where('number', 10)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'ol-mathematics', 'education_level_id' => $level->id]);
    $grade9Lesson = Lesson::factory()->for($subject)->create(['grade_id' => $grade9->id]);
    $grade10Lesson = Lesson::factory()->for($subject)->create(['grade_id' => $grade10->id]);

    $teacher = TeacherProfile::factory()->approved()->create();
    $pending = TeacherProfile::factory()->pending()->create();

    ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::Lesson)->create([
        'subject_id' => $subject->id, 'lesson_id' => $grade9Lesson->id, 'grade_id' => $grade9->id, 'title' => 'Grade Nine Pack',
    ]);
    ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::Lesson)->create([
        'subject_id' => $subject->id, 'lesson_id' => $grade10Lesson->id, 'grade_id' => $grade10->id, 'title' => 'Grade Ten Pack',
    ]);
    ServiceOffering::factory()->for($pending, 'teacherProfile')->forType(ServiceType::Qna)->create([
        'subject_id' => $subject->id, 'title' => 'Unapproved Q&A',
    ]);
    ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::Qna)->inactive()->create([
        'subject_id' => $subject->id, 'title' => 'Retired Q&A',
    ]);

    // A grade pins the catalog to that grade's services.
    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade9->id]))
        ->assertOk()
        ->assertSee('Grade Nine Pack')
        ->assertDontSee('Grade Ten Pack')
        ->assertDontSee('Unapproved Q&A')
        ->assertDontSee('Retired Q&A');

    // Without a grade the whole subject catalog is shown.
    $this->get(route('catalog.subjects.show', $subject))
        ->assertOk()
        ->assertSee('Grade Nine Pack')
        ->assertSee('Grade Ten Pack')
        ->assertDontSee('Unapproved Q&A')
        ->assertDontSee('Retired Q&A');
});

test('subject services offer buying to students and sign-up to guests', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade9 = Grade::query()->where('education_level_id', $level->id)->where('number', 9)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'ol-mathematics', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade9->id]);

    $teacher = TeacherProfile::factory()->approved()->create();
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $grade9->id]]);

    $discussion = ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::Qna)->create([
        'subject_id' => $subject->id,
        'title' => 'Mock paper discussion',
    ]);
    $marking = ServiceOffering::factory()->for($teacher, 'teacherProfile')->forType(ServiceType::PaperMarking)->create([
        'subject_id' => $subject->id,
        'title' => 'Past paper marking',
    ]);

    $url = route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade9->id]);

    // Guests only get the create-account action.
    $this->get($url)
        ->assertOk()
        ->assertSee('Sign up to buy')
        ->assertDontSee('Buy now')
        ->assertDontSee('Submit a paper');

    // Students are redirected to their workspace page, where they buy the
    // offering or start a marking order for a paper service.
    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get($url)
        ->assertRedirect(route('student.subjects.show', ['subject' => $subject->slug, 'grade' => $grade9->id]));

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.subjects.show', ['subject' => $subject, 'grade' => $grade9->id]))
        ->assertOk()
        ->assertSee('Buy now')
        ->assertSee(route('student.purchases.store', $discussion))
        ->assertSee('Submit a paper')
        ->assertSee(route('student.marking.create', $marking));
});

test('the student subject page is scoped to the profile grade', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade9 = Grade::query()->where('education_level_id', $level->id)->where('number', 9)->firstOrFail();
    $grade10 = Grade::query()->where('education_level_id', $level->id)->where('number', 10)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'ol-mathematics', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade9->id, 'name' => 'Grade Nine Algebra']);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade10->id, 'name' => 'Grade Ten Geometry']);

    $student = User::factory()->student()->onboarded()->create();
    $student->studentProfile->update(['grade_id' => $grade9->id]);

    // A student opening the public URL is sent to their own page.
    $this->actingAs($student)
        ->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade10->id]))
        ->assertRedirect(route('student.subjects.show', ['subject' => $subject->slug, 'grade' => $grade10->id]));

    // The profile grade wins: even a grade 10 query cannot widen the page.
    $this->actingAs($student)
        ->get(route('student.subjects.show', ['subject' => $subject, 'grade' => $grade10->id]))
        ->assertOk()
        ->assertSee('Grade Nine Algebra')
        ->assertDontSee('Grade Ten Geometry');
});

test('the student subject page requires an onboarded student', function () {
    $subject = Subject::factory()->create();

    $this->get(route('student.subjects.show', $subject))
        ->assertRedirect(route('login'));

    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.subjects.show', $subject))
        ->assertRedirect(route('student.onboarding.show'));

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.subjects.show', $subject))
        ->assertForbidden();
});

test('a subject with no services points at the teacher directory', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade9 = Grade::query()->where('education_level_id', $level->id)->where('number', 9)->firstOrFail();

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'ol-mathematics', 'education_level_id' => $level->id]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade9->id]);

    $this->get(route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade9->id]))
        ->assertOk()
        ->assertSee('Services for Mathematics in Grade 9')
        ->assertSee('No ready-made services for this subject yet')
        ->assertSee(route('teachers.index', ['subject' => 'ol-mathematics', 'grade' => $grade9->id]));
});
