<?php

use App\Models\TeacherProfile;
use App\Models\User;

test('an approved teacher is announced live before reaching the workspace', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->approved()->for($teacher)->create(['approved_seen_at' => null]);

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.welcome.live'));
});

test('the live screen lists next actions and acknowledges only on request', function () {
    $teacher = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->approved()->for($teacher)->create(['approved_seen_at' => null]);

    $this->actingAs($teacher)
        ->get(route('teacher.welcome.live'))
        ->assertOk()
        ->assertSee('You are live')
        ->assertSee('Add a service')
        ->assertSee('Start teaching');

    expect($profile->fresh()->approved_seen_at)->toBeNull();
});

test('acknowledging the announcement stops it from repeating', function () {
    $teacher = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->approved()->for($teacher)->create(['approved_seen_at' => null]);

    $this->actingAs($teacher)
        ->post(route('teacher.welcome.live.ack'))
        ->assertRedirect(route('teacher.dashboard'))
        ->assertSessionHas('status', 'teacher-live');

    expect($profile->fresh()->approved_seen_at)->not->toBeNull();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('a teacher whose announcement was already seen goes straight to the workspace', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.welcome.live'))
        ->assertRedirect(route('teacher.dashboard'));
});

test('a teacher still in review never sees the live screen', function () {
    $teacher = User::factory()->teacher()->awaitingApproval()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.welcome.live'))
        ->assertRedirect(route('teacher.approval.status'));
});

test('approving an application queues the announcement again', function () {
    $teacher = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->pending()->for($teacher)->create();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.verifications.approve', $profile))
        ->assertSessionHas('status', 'teacher-approved');

    expect($profile->fresh()->isApproved())->toBeTrue()
        ->and($profile->fresh()->approved_seen_at)->toBeNull();
});
