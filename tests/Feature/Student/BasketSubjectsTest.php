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

test('the interests page groups grade 10 subjects by basket', function () {
    [$student, $grade, , $subjects] = gradeTenStudentWithBasketSubjects(['category_1']);

    $mandatory = Subject::factory()->create([
        'name' => 'Mandatory Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($mandatory)->create(['grade_id' => $grade->id]);

    $this->actingAs($student)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee('Category I')
        ->assertSee('Compulsory subjects')
        ->assertSee($subjects['category_1']->name)
        ->assertSee($mandatory->name)
        // Only a basket that offers a subject in this grade renders a group
        // wrapper; the compulsory subjects keep their own group.
        ->assertSee('data-basket-group', false)
        ->assertSee('pick one')
        ->assertSee('all students')
        ->assertSee('Aesthetic, music, dancing, literature and drama subjects');
});

test('the interests page stays ungrouped below grade 10', function () {
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(9))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $subject = Subject::factory()->create([
        'name' => 'Grade Nine Mathematics',
        'education_level_id' => $grade->education_level_id,
    ]);
    Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

    $this->actingAs($user)
        ->get(route('student.interests.edit'))
        ->assertOk()
        ->assertSee('Grade Nine Mathematics')
        ->assertDontSee('data-basket-group', false)
        ->assertDontSee('pick one');
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

test('the one subject per basket limit does not apply below grade 10', function () {
    $user = User::factory()->student()->onboarded()->create();
    $grade = Grade::query()->whereKey(gradeId(9))->firstOrFail();
    $user->studentProfile->update(['grade_id' => $grade->id]);

    $basket = SubjectBasket::query()
        ->where('education_level_id', $grade->education_level_id)
        ->where('key', 'category_1')
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
        ->assertSessionHasNoErrors();

    expect($user->fresh()->interestedSubjects()->count())->toBe(2);
});
