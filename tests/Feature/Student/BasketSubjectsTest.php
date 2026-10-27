<?php

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * A Grade 10 (O/L) student plus one subject - with a lesson in the student's
 * grade - for each of the given basket keys. The O/L baskets themselves are
 * seeded by the migrations.
 *
 * @param  list<string>  $basketKeys
 * @return array{0: User, 1: Grade, 2: Collection<int, SubjectBasket>, 3: array<string, Subject>}
 */
function gradeTenStudentWithBasketSubjects(array $basketKeys = ['category_1', 'category_3']): array
{
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(10))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $baskets = SubjectBasket::query()
        ->where('education_level_id', $grade->education_level_id)
        ->ordered()
        ->get();

    $subjects = [];

    foreach ($basketKeys as $index => $key) {
        $subject = Subject::factory()->create([
            'name' => 'Basket Subject '.($index + 1),
            'education_level_id' => $grade->education_level_id,
            'basket_id' => $baskets->firstWhere('key', $key)->id,
        ]);

        Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

        $subjects[$key] = $subject;
    }

    return [$user->fresh(), $grade, $baskets, $subjects];
}

test('the interests page shows compulsory subjects and only the picked basket subject', function () {
    [$student, $grade, , $subjects] = gradeTenStudentWithBasketSubjects(['category_1']);

    $mandatory = Subject::factory()->create([
        'name' => 'Mandatory Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($mandatory)->create(['grade_id' => $grade->id]);

    $unpicked = Subject::factory()->create([
        'name' => 'Unpicked Category One',
        'education_level_id' => $grade->education_level_id,
        'basket_id' => $subjects['category_1']->basket_id,
    ]);
    Lesson::factory()->for($unpicked)->create(['grade_id' => $grade->id]);

    $student->interestedSubjects()->sync([$subjects['category_1']->id]);

    $this->actingAs($student)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee('Category I')
        ->assertSee('Compulsory subjects')
        ->assertSee($subjects['category_1']->name)
        ->assertSee($mandatory->name)
        // The basket group keeps its header and the picked subject is marked
        // chosen; the other basket options stay off the page.
        ->assertSee('chosen')
        ->assertSee('Academic, commerce and second-language subjects')
        ->assertDontSee($unpicked->name)
        // The page is a display, not a picker: no checkboxes, no lessons.
        ->assertDontSee('name="subjects"', false)
        ->assertDontSee('name="lessons"', false)
        ->assertDontSee('pick one')
        ->assertDontSee('data-basket-group', false);
});

test('the interests page stays ungrouped outside the O/L basket grades', function () {
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(13))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $subject = Subject::factory()->create([
        'name' => 'A/L Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

    $this->actingAs($user)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee('A/L Mathematics')
        ->assertDontSee('Compulsory subjects')
        ->assertDontSee('pick one');
});

test('a grade 6 to 9 student sees their picked Category II subject grouped', function () {
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(8))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $basket = SubjectBasket::query()
        ->where('education_level_id', $grade->education_level_id)
        ->where('key', 'category_2')
        ->firstOrFail();

    $art = Subject::factory()->create([
        'name' => 'Junior Art',
        'education_level_id' => $grade->education_level_id,
        'basket_id' => $basket->id,
    ]);
    Lesson::factory()->for($art)->create(['grade_id' => $grade->id]);

    $mathematics = Subject::factory()->create([
        'name' => 'Junior Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($mathematics)->create(['grade_id' => $grade->id]);

    $user->interestedSubjects()->sync([$art->id]);

    $this->actingAs($user)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee('Compulsory subjects')
        ->assertSee('Category II')
        ->assertSee('Junior Art')
        ->assertSee('Junior Mathematics');
});

test('a grade 10 student can pick one subject from each basket', function () {
    [$student, , , $subjects] = gradeTenStudentWithBasketSubjects(['category_1', 'category_3']);

    $this->actingAs($student)
        ->put(route('student.interests.update'), [
            'subjects' => [$subjects['category_1']->id, $subjects['category_3']->id],
        ])
        ->assertRedirect(route('student.interests.edit'))
        ->assertSessionHasNoErrors();

    expect($student->fresh()->interestedSubjects()->pluck('subjects.id')->sort()->values()->all())
        ->toBe(collect([$subjects['category_1']->id, $subjects['category_3']->id])->sort()->values()->all());
});

test('a second subject from the same basket is rejected', function () {
    [$student, $grade, , $subjects] = gradeTenStudentWithBasketSubjects(['category_1']);

    $secondCategoryOne = Subject::factory()->create([
        'name' => 'Second Category One',
        'education_level_id' => $grade->education_level_id,
        'basket_id' => $subjects['category_1']->basket_id,
    ]);

    $this->actingAs($student)
        ->put(route('student.interests.update'), [
            'subjects' => [$subjects['category_1']->id, $secondCategoryOne->id],
        ])
        ->assertSessionHasErrors('subjects');

    expect($student->fresh()->interestedSubjects()->count())->toBe(0);
});

test('an incomplete basket choice is saved with a reminder', function () {
    [$student, , , $subjects] = gradeTenStudentWithBasketSubjects(['category_1', 'category_3']);

    $this->actingAs($student)
        ->put(route('student.interests.update'), [
            'subjects' => [$subjects['category_1']->id],
        ])
        ->assertRedirect(route('student.interests.edit'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('basketReminder', ['Category III']);

    expect($student->fresh()->interestedSubjects()->count())->toBe(1);
});

test('the one subject from Category II limit applies to the junior grades too', function () {
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(9))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $basket = SubjectBasket::query()
        ->where('education_level_id', $grade->education_level_id)
        ->where('key', 'category_2')
        ->firstOrFail();

    $first = Subject::factory()->create([
        'name' => 'Grade Nine Art',
        'education_level_id' => $grade->education_level_id,
        'basket_id' => $basket->id,
    ]);
    $second = Subject::factory()->create([
        'name' => 'Grade Nine Music',
        'education_level_id' => $grade->education_level_id,
        'basket_id' => $basket->id,
    ]);

    $this->actingAs($user)
        ->put(route('student.interests.update'), ['subjects' => [$first->id, $second->id]])
        ->assertSessionHasErrors('subjects');

    expect($user->fresh()->interestedSubjects()->count())->toBe(0);
});
