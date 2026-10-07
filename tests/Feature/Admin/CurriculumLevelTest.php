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
});

test('admins can list the levels with grades and subject counts', function () {
    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    Subject::factory()->create(['education_level_id' => $ol->id, 'name' => 'Mathematics']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.curriculum.index'))
        ->assertOk()
        ->assertSee('Primary')
        ->assertSee('O/L (Ordinary Level)')
        ->assertSee('A/L (Advanced Level)')
        ->assertSee('Grade 6')
        ->assertSee('Grade 11')
        ->assertSee('1 subject')
        ->assertSee('Grades 6-11');
});

test('admins can rename, reorder and deactivate a level', function () {
    $level = EducationLevel::query()->where('key', 'primary')->firstOrFail();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', $level), [
            'name' => 'Primary school',
            'icon' => 'P',
            'sort_order' => 9,
        ])
        ->assertRedirect(route('admin.curriculum.index'));

    $level->refresh();

    expect($level->name)->toBe('Primary school')
        ->and($level->icon)->toBe('P')
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

test('deactivating a level drops it from the public catalog', function () {
    // Prime the cached catalog, then switch the level off.
    $this->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertSee('Primary');

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.curriculum.levels.update', 'primary'), ['name' => 'Primary'])
        ->assertRedirect(route('admin.curriculum.index'));

    $this->get(route('catalog.subjects.index'))
        ->assertOk()
        ->assertDontSee('Primary');
});
