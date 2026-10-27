<?php

use App\Enums\GradeBand;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Models\User;
use Database\Seeders\CatalogSeeder;

/**
 * A student who has not finished onboarding, plus one subject - with a lesson
 * in Grade 10 - for each of the given O/L basket keys.
 *
 * @param  list<string>  $basketKeys
 * @return array{0: User, 1: Grade, 2: array<string, Subject>}
 */
function onboardingBasketSubjects(array $basketKeys = ['category_1', 'category_2', 'category_3']): array
{
    $user = User::factory()->student()->create();
    $grade = Grade::query()->whereKey(gradeId(10))->firstOrFail();

    $baskets = SubjectBasket::query()
        ->where('education_level_id', $grade->education_level_id)
        ->get()
        ->keyBy('key');

    $subjects = [];

    foreach ($basketKeys as $index => $key) {
        $subject = Subject::factory()->create([
            'name' => 'Basket Subject '.($index + 1),
            'education_level_id' => $grade->education_level_id,
            'basket_id' => $baskets[$key]->id,
        ]);

        Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

        $subjects[$key] = $subject;
    }

    return [$user, $grade, $subjects];
}

/**
 * An O/L subject of one basket that runs in the given grade, so it shows up in
 * that grade's onboarding step.
 *
 * @return array{0: Subject, 1: Grade}
 */
function basketSubjectInGrade(int $gradeNumber, string $basketKey = 'category_1', GradeBand $band = GradeBand::All): array
{
    $grade = Grade::query()->whereKey(gradeId($gradeNumber))->firstOrFail();

    $basket = SubjectBasket::query()
        ->where('education_level_id', $grade->education_level_id)
        ->where('key', $basketKey)
        ->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Basket '.$basketKey.' in Grade '.$gradeNumber,
        'education_level_id' => $grade->education_level_id,
        'basket_id' => $basket->id,
        'grade_band' => $band,
    ]);

    Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

    return [$subject, $grade];
}

function olLevelId(): int
{
    return (int) EducationLevel::query()->where('key', 'ol')->value('id');
}

function alLevelId(): int
{
    return (int) EducationLevel::query()->where('key', 'al')->value('id');
}

test('a new student is sent to the onboarding wizard', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.dashboard'))
        ->assertRedirect(route('student.onboarding.show'));

    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.profile'))
        ->assertRedirect(route('student.onboarding.show'));
});

test('the wizard offers the levels, the grades and the basket subjects', function () {
    [$user, $grade, $subjects] = onboardingBasketSubjects();

    $this->actingAs($user)
        ->get(route('student.onboarding.show'))
        ->assertOk()
        ->assertSee('Which level are you studying?')
        ->assertSee('O/L (Ordinary Level)')
        ->assertSee('Which grade are you in?')
        ->assertSee($grade->label)
        ->assertSee('Category I')
        ->assertSee('Category III')
        ->assertSee('one subject from each of the three O/L baskets')
        ->assertSee($subjects['category_1']->name)
        ->assertSee($subjects['category_3']->name)
        ->assertSee('Which language do you learn in?')
        ->assertSee('Sinhala')
        ->assertSee('English');
});

test('a basket with nothing to choose from is left out of the wizard', function () {
    [$user, , $subjects] = onboardingBasketSubjects(['category_1']);

    $this->actingAs($user)
        ->get(route('student.onboarding.show'))
        ->assertOk()
        ->assertSee($subjects['category_1']->name)
        ->assertDontSee('Category III');
});

test('the seeded catalog offers a grade 6 to 9 student the Category II step', function () {
    $this->seed(CatalogSeeder::class);

    $grade7 = Grade::query()->whereKey(gradeId(7))->firstOrFail();
    $art = Subject::query()->where('slug', 'art')->firstOrFail();
    $ict = Subject::query()->where('slug', 'ict')->firstOrFail();
    $user = User::factory()->student()->create();

    $this->actingAs($user)
        ->get(route('student.onboarding.show'))
        ->assertOk()
        ->assertSee('Category II')
        ->assertSee($art->name)
        ->assertSee('Grades 6 to 9 pick one subject from their Category II basket');

    // Category III opens in Grade 10 only, so an ICT pick has no basket to sit
    // in for this grade.
    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade7->id,
        'learning_language' => 'Sinhala',
        'basket_subjects' => ['category_3' => $ict->id],
    ])->assertSessionHasErrors('basket_subjects');

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade7->id,
        'learning_language' => 'Sinhala',
        'basket_subjects' => ['category_2' => $art->id],
    ])->assertRedirect(route('student.dashboard'));

    expect($user->fresh()->interestedSubjects()->pluck('subjects.slug')->all())->toBe(['art']);
});

test('finishing the wizard saves the grade, the language and the basket subjects', function () {
    [$user, $grade, $subjects] = onboardingBasketSubjects();

    $response = $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade->id,
        'learning_language' => 'Sinhala',
        'basket_subjects' => [
            'category_1' => $subjects['category_1']->id,
            'category_2' => $subjects['category_2']->id,
            'category_3' => $subjects['category_3']->id,
        ],
    ]);

    $response->assertRedirect(route('student.dashboard'))
        ->assertSessionHas('status', 'profile-completed');

    $profile = $user->fresh()->studentProfile;

    expect($profile)->not->toBeNull()
        ->and($profile->completed_at)->not->toBeNull()
        ->and($profile->grade_id)->toBe($grade->id)
        ->and($profile->learning_language)->toBe('Sinhala')
        ->and($profile->timezone)->toBe('Asia/Colombo');

    expect($user->fresh()->interestedSubjects()->pluck('subjects.id')->sort()->values()->all())
        ->toBe(collect($subjects)->pluck('id')->sort()->values()->all());

    // The workspace opens once the wizard is done.
    $this->actingAs($user->fresh())->get(route('student.dashboard'))->assertOk();
});

test('the grade has to belong to the chosen level', function () {
    $user = User::factory()->student()->create();
    $al = EducationLevel::query()->where('key', 'al')->firstOrFail();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => $al->id,
        'grade_id' => gradeId(10),
        'learning_language' => 'English',
    ])->assertSessionHasErrors('grade_id');

    expect($user->fresh()->studentProfile)->toBeNull();
});

test('a grade with baskets needs one subject from every basket', function () {
    [$user, $grade, $subjects] = onboardingBasketSubjects();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade->id,
        'learning_language' => 'English',
        'basket_subjects' => ['category_1' => $subjects['category_1']->id],
    ])->assertSessionHasErrors('basket_subjects');

    expect($user->fresh()->studentProfile)->toBeNull();
});

test('a subject has to come from the basket it is submitted under', function () {
    [$user, $grade, $subjects] = onboardingBasketSubjects();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade->id,
        'learning_language' => 'English',
        'basket_subjects' => [
            'category_1' => $subjects['category_2']->id,
            'category_2' => $subjects['category_2']->id,
            'category_3' => $subjects['category_3']->id,
        ],
    ])->assertSessionHasErrors('basket_subjects');
});

test('a grade 6 to 9 student picks their Category II subject and nothing else', function () {
    $user = User::factory()->student()->create();
    [$art, $grade] = basketSubjectInGrade(7, 'category_2');
    [$ict] = basketSubjectInGrade(7, 'category_3');
    $this->actingAs($user)
        ->get(route('student.onboarding.show'))
        ->assertOk()
        ->assertSee('Category II')
        ->assertSee($art->name)
        ->assertSee('Grades 6 to 9 pick one subject from their Category II basket')
        ->assertDontSee('Category III')
        ->assertDontSee($ict->name);

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade->id,
        'learning_language' => 'Sinhala',
        'basket_subjects' => ['category_2' => $art->id],
    ])->assertRedirect(route('student.dashboard'));

    expect($user->fresh()->interestedSubjects()->pluck('subjects.id')->all())->toBe([$art->id]);
});

test('a grade 6 to 9 student has to pick a Category II subject', function () {
    $user = User::factory()->student()->create();
    [, $grade] = basketSubjectInGrade(8, 'category_2');

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade->id,
        'learning_language' => 'Sinhala',
    ])->assertSessionHasErrors('basket_subjects');

    expect($user->fresh()->studentProfile)->toBeNull();
});

test('a subject banded to the senior grades is only offered in grades 10 and 11', function () {
    [$senior] = basketSubjectInGrade(10, 'category_1', GradeBand::Senior);
    Lesson::factory()->for($senior)->create(['grade_id' => gradeId(7)]);

    $student = User::factory()->student()->create();
    $payload = [
        'level_id' => olLevelId(),
        'learning_language' => 'English',
        'basket_subjects' => ['category_1' => $senior->id],
    ];

    // The subject runs in Grade 7 as well, but its band keeps it out.
    $this->actingAs($student)
        ->post(route('student.onboarding.store'), $payload + ['grade_id' => gradeId(7)])
        ->assertSessionHasErrors('basket_subjects');

    $this->actingAs($student)
        ->post(route('student.onboarding.store'), $payload + ['grade_id' => gradeId(10)])
        ->assertRedirect(route('student.dashboard'));

    expect($student->fresh()->interestedSubjects()->pluck('subjects.id')->all())->toBe([$senior->id]);
});

test('a subject banded to the junior grades is not offered in grade 10', function () {
    [$junior] = basketSubjectInGrade(7, 'category_2', GradeBand::Junior);
    Lesson::factory()->for($junior)->create(['grade_id' => gradeId(10)]);

    $student = User::factory()->student()->create();
    $payload = [
        'level_id' => olLevelId(),
        'learning_language' => 'English',
        'basket_subjects' => ['category_2' => $junior->id],
    ];

    $this->actingAs($student)
        ->post(route('student.onboarding.store'), $payload + ['grade_id' => gradeId(10)])
        ->assertSessionHasErrors('basket_subjects');

    $this->actingAs($student)
        ->post(route('student.onboarding.store'), $payload + ['grade_id' => gradeId(7)])
        ->assertRedirect(route('student.dashboard'));

    expect($student->fresh()->interestedSubjects()->pluck('subjects.id')->all())->toBe([$junior->id]);
});

test('a grade without baskets finishes without sending any', function () {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => alLevelId(),
        'grade_id' => gradeId(13),
        'learning_language' => 'English',
    ])->assertRedirect(route('student.dashboard'));

    expect($user->fresh()->studentProfile->grade_id)->toBe(gradeId(13));
});

test('basket subjects are refused for a grade that does not choose them', function () {
    [$subject] = basketSubjectInGrade(10);

    $user = User::factory()->student()->create();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => alLevelId(),
        'grade_id' => gradeId(13),
        'learning_language' => 'English',
        'basket_subjects' => ['category_1' => $subject->id],
    ])->assertSessionHasErrors('basket_subjects');

    expect($user->fresh()->studentProfile)->toBeNull();
});

test('the learning language has to be one the platform teaches in', function () {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => gradeId(9),
        'learning_language' => 'Tamil',
    ])->assertSessionHasErrors('learning_language');
});

test('a failed submit keeps the answers and returns to the wizard', function () {
    [$user, $grade, $subjects] = onboardingBasketSubjects();

    $this->actingAs($user)->post(route('student.onboarding.store'), [
        'level_id' => olLevelId(),
        'grade_id' => $grade->id,
        'learning_language' => 'English',
        'basket_subjects' => ['category_1' => $subjects['category_1']->id],
    ])->assertSessionHasErrors('basket_subjects');

    $this->actingAs($user)
        ->get(route('student.onboarding.show'))
        ->assertOk()
        ->assertSee('Please check your answers')
        ->assertSee('Choose one subject from Category II')
        ->assertSee($subjects['category_1']->name);
});

test('an onboarded student skips the wizard', function () {
    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.onboarding.show'))
        ->assertRedirect(route('student.dashboard'));

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->post(route('student.onboarding.store'), [
            'level_id' => olLevelId(),
            'grade_id' => gradeId(11),
            'learning_language' => 'English',
        ])
        ->assertRedirect(route('student.dashboard'));
});

test('only students may open the onboarding wizard', function () {
    $this->get(route('student.onboarding.show'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.onboarding.show'))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('student.onboarding.show'))
        ->assertForbidden();
});
