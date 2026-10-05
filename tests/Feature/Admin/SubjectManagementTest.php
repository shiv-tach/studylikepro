<?php

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('guests are redirected from admin catalog routes', function () {
    $this->get(route('admin.subjects.index'))->assertRedirect('/login');
});

test('non admins cannot manage the catalog', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('admin.subjects.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('admin.subjects.store'), ['name' => 'Hacked'])
        ->assertForbidden();
});

test('admins can list subjects with usage counts', function () {
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'icon' => '🔢']);
    Topic::factory()->for($subject)->count(2)->create();
    Subject::factory()->inactive()->create(['name' => 'Ancient History']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.subjects.index'))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('2 topics')
        ->assertSee('Ancient History')
        ->assertSee('Inactive');
});

test('admins can create a subject with an auto generated slug', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.store'), [
            'name' => 'Computer Science',
            'icon' => '💻',
            'sort_order' => 3,
            'is_active' => '1',
        ])
        ->assertRedirect();

    $subject = Subject::query()->where('name', 'Computer Science')->first();

    expect($subject)->not->toBeNull()
        ->and($subject->slug)->toBe('computer-science')
        ->and($subject->is_active)->toBeTrue()
        ->and($subject->sort_order)->toBe(3);
});

test('creating a subject validates its input', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.subjects.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('admins can update a subject and deactivate it', function () {
    $subject = Subject::factory()->create(['name' => 'Old name', 'is_active' => true]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.subjects.update', $subject), ['name' => 'New name'])
        ->assertRedirect();

    $subject->refresh();

    expect($subject->name)->toBe('New name')
        ->and($subject->is_active)->toBeFalse();
});

test('admins can add, update and delete topics', function () {
    $subject = Subject::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.subjects.topics.store', $subject), ['name' => 'Algebra'])
        ->assertRedirect();

    $topic = $subject->topics()->first();
    expect($topic->slug)->toBe('algebra');

    $this->actingAs($admin)
        ->put(route('admin.subjects.topics.update', [$subject, $topic]), [
            'name' => 'Linear Algebra',
            'is_active' => '1',
        ])
        ->assertRedirect();

    expect($topic->fresh()->name)->toBe('Linear Algebra');

    $this->actingAs($admin)
        ->delete(route('admin.subjects.topics.destroy', [$subject, $topic]))
        ->assertRedirect();

    expect(Topic::find($topic->id))->toBeNull();
});

test('a topic cannot be accessed through a different subject', function () {
    $subjectA = Subject::factory()->create();
    $subjectB = Subject::factory()->create();
    $topic = Topic::factory()->for($subjectA)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.subjects.topics.update', [$subjectB, $topic]), ['name' => 'Moved'])
        ->assertNotFound();
});
