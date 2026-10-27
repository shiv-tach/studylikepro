<?php

use App\Models\ActivityLog;
use App\Models\EducationLevel;
use App\Models\Subject;
use App\Models\User;

test('guests are redirected from the curriculum page', function () {
    $this->get(route('admin.curriculum.index'))->assertRedirect('/login');
});

test('non admins cannot manage the curriculum', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('admin.curriculum.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->put(route('admin.curriculum.levels.update', 'ol'), ['name' => 'Hacked'])
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->delete(route('admin.curriculum.levels.destroy', 'ol'))
        ->assertForbidden();
});

test('admins can list the levels with grades and subject counts', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    Subject::factory()->create(['education_level_id' => $ol->id, 'name' => 'Mathematics']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.curriculum.index'))
        ->assertOk()
        ->assertDontSee('Primary')
        ->assertSee('O/L (Ordinary Level)')
        ->assertSee('A/L (Advanced Level)')
        ->assertSee('Grade 6')
        ->assertSee('Grade 11')
        ->assertSee('1 subject')
        ->assertSee('Grades 6-11')
        ->assertSee('Delete level');
});

test('admins can rename, reorder and deactivate a level', function () {
    $level = EducationLevel::query()->where('key', 'al')->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', $level), [
            'name' => 'Advanced Level',
            'icon' => 'A',
            'sort_order' => 9,
        ])
        ->assertRedirect(route('admin.curriculum.index'));

    $level->refresh();

    expect($level->name)->toBe('Advanced Level')
        ->and($level->icon)->toBe('A')
        ->and($level->sort_order)->toBe(9)
        ->and($level->is_active)->toBeFalse();
});

test('updating a level validates its input', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', 'ol'), ['name' => ''])
        ->assertSessionHasErrors('name');

    expect(EducationLevel::query()->where('key', 'ol')->value('name'))->toBe('O/L (Ordinary Level)');
});

test('an unknown level key is not found', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', 'not-a-level'), ['name' => 'Nope'])
        ->assertNotFound();
});

test('level changes are written to the activity log', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', 'ol'), ['name' => 'Ordinary Level']);

    $entry = ActivityLog::query()->where('action', 'admin.curriculum.levels.update')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->description)->toContain('Ordinary Level');
});

test('admins can delete a level that has no subjects', function () {
    $level = EducationLevel::factory()->create(['name' => 'Vocational']);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.curriculum.levels.destroy', $level))
        ->assertRedirect(route('admin.curriculum.index'));

    expect(EducationLevel::query()->whereKey($level->id)->exists())->toBeFalse();

    $entry = ActivityLog::query()->where('action', 'admin.curriculum.levels.destroy')->latest('id')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->description)->toContain('Vocational');
});

test('a level that still has subjects cannot be deleted', function () {
    $level = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    Subject::factory()->create(['education_level_id' => $level->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.curriculum.levels.destroy', $level))
        ->assertRedirect(route('admin.curriculum.index'))
        ->assertSessionHas('status', 'level-in-use');

    expect(EducationLevel::query()->whereKey($level->id)->exists())->toBeTrue();
});

test('deactivating a level drops it from the public catalog', function () {
    // Prime the cached catalog, then switch the level off.
    $this->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertSee('Advanced Level');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', 'al'), ['name' => 'Advanced Level'])
        ->assertRedirect(route('admin.curriculum.index'));

    $this->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertDontSee('Advanced Level');
});
