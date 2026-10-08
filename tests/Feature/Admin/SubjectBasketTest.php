<?php

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Models\User;

function olSubjectBasket(string $key): SubjectBasket
{
    return SubjectBasket::query()
        ->where('key', $key)
        ->whereHas('educationLevel', fn ($query) => $query->where('key', 'ol'))
        ->firstOrFail();
}

test('non admins cannot manage the baskets', function () {
    $basket = olSubjectBasket('category_1');

    $this->actingAs(User::factory()->teacher()->create())
        ->put(route('admin.curriculum.baskets.update', $basket), ['name' => 'Renamed'])
        ->assertForbidden();

    expect($basket->fresh()->name)->toBe('Category I');
});

test('the curriculum page lists the o/l baskets', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.curriculum.index'))
        ->assertOk()
        ->assertSee('Subject baskets')
        ->assertSee('Category I')
        ->assertSee('Category III');
});

test('admins can rename, reorder and switch a basket off', function () {
    $basket = olSubjectBasket('category_2');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.baskets.update', $basket), [
            'name' => 'Category II - Technical',
            'description' => 'Technical and practical subjects',
            'icon' => '🛠️',
            'sort_order' => 5,
        ])
        ->assertRedirect(route('admin.curriculum.index'));

    $basket->refresh();

    expect($basket->name)->toBe('Category II - Technical')
        ->and($basket->description)->toBe('Technical and practical subjects')
        ->and($basket->icon)->toBe('🛠️')
        ->and($basket->sort_order)->toBe(5)
        ->and($basket->is_active)->toBeFalse();
});

test('admins can assign a subject to a basket of its level', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $basket = olSubjectBasket('category_1');
    $subject = Subject::factory()->create(['name' => 'Art', 'education_level_id' => $ol->id]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.subjects.update', $subject), ['name' => 'Art', 'basket_id' => $basket->id])
        ->assertRedirect();

    expect($subject->fresh()->basket_id)->toBe($basket->id);

    // Clearing the choice turns the subject back into a mandatory one.
    $this->actingAs($admin)
        ->put(route('admin.subjects.update', $subject->refresh()), ['name' => 'Art', 'basket_id' => ''])
        ->assertRedirect();

    expect($subject->fresh()->basket_id)->toBeNull();
});

test('a basket edit reaches the public catalog right away', function () {
    $this->seed(CatalogSeeder::class);

    $basket = olSubjectBasket('category_1');
    $grade10 = Grade::query()
        ->where('education_level_id', $basket->education_level_id)
        ->where('number', 10)
        ->firstOrFail();

    // Warm the cached catalog the public pages read.
    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade10->id]))
        ->assertOk()
        ->assertSee('pick one');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.baskets.update', $basket), [
            'name' => 'Category I - Aesthetic',
            'description' => 'Art, music, dancing and literature',
            'sort_order' => 0,
            'is_active' => '1',
        ])
        ->assertRedirect(route('admin.curriculum.index'));

    $this->get(route('catalog.subjects.index', ['level' => 'ol', 'grade' => $grade10->id]))
        ->assertOk()
        ->assertSee('Category I - Aesthetic')
        ->assertSee('Art, music, dancing and literature');
});

test('a basket from another level is rejected', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $subject = Subject::factory()->create(['name' => 'O/L Art', 'education_level_id' => $ol->id]);
    $foreignBasket = SubjectBasket::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.subjects.update', $subject), [
            'name' => 'O/L Art',
            'basket_id' => $foreignBasket->id,
        ])
        ->assertSessionHasErrors('basket_id');

    expect($subject->fresh()->basket_id)->toBeNull();
});
