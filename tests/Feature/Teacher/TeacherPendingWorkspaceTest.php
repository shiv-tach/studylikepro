<?php

use App\Models\TeacherProfile;
use App\Models\User;

test('an application in review is sent to the status page instead of the workspace', function () {
    $teacher = User::factory()->teacher()->awaitingApproval()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.approval.status'))
        ->assertSessionHas('status', 'approval-pending');

    foreach ([
        route('teacher.services.index'),
        route('teacher.subjects.index'),
        route('teacher.schedule.index'),
        route('teacher.earnings.index'),
        route('teacher.reviews.index'),
        route('teacher.orders.index'),
        route('teacher.marking.index'),
    ] as $url) {
        $this->actingAs($teacher)
            ->get($url)
            ->assertRedirect(route('teacher.approval.status'));
    }
});

test('a rejected teacher is told action is needed', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->rejected()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertRedirect(route('teacher.approval.status'))
        ->assertSessionHas('status', 'approval-action');
});

test('the pending workspace keeps the pages that can still be used', function () {
    $teacher = User::factory()->teacher()->awaitingApproval()->create();
    \App\Models\TeacherAvailabilitySlot::factory()->create(['teacher_profile_id' => $teacher->teacherProfile->id]);

    $urls = [
        'status' => route('teacher.approval.status'),
        'profile' => route('teacher.profile'),
        'availability' => route('teacher.availability.index'),
        'verification' => route('teacher.verification'),
        'preview' => route('teacher.profile.preview'),
    ];

    foreach ($urls as $name => $url) {
        $response = $this->actingAs($teacher)->get($url);

        expect($response->status())->toBe(200, "{$name} returned {$response->status()} and redirected to ".($response->headers->get('Location') ?? 'nowhere'));
    }
});

test('an approved teacher keeps the full workspace', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('the sidebar shows only the pending workspace links before approval', function () {
    $teacher = User::factory()->teacher()->awaitingApproval()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('Approval status')
        ->assertSee('Preview profile')
        ->assertDontSee('Earnings')
        ->assertDontSee('Services &amp; pricing', false);
});

test('every workspace page carries the review banner until approval', function () {
    $teacher = User::factory()->teacher()->awaitingApproval()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertSee('your profile is not visible to students yet')
        ->assertSee('View approval status');
});

test('an approved teacher never sees the review banner', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.profile'))
        ->assertOk()
        ->assertDontSee('your profile is not visible to students yet');
});
