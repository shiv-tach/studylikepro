<?php

use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;

test('an application in review says it is not visible to students', function () {
    $teacher = User::factory()->teacher()->awaitingApproval()->create();
    $subject = Subject::query()->active()->first() ?? Subject::factory()->create(['is_active' => true]);
    $teacher->teacherProfile->subjects()->attach($subject->id, [
        'status' => \App\Enums\ApprovalStatus::Pending,
        'grade_levels' => [],
    ]);
    TeacherVerificationDocument::factory()->for($teacher->teacherProfile)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.approval.status'))
        ->assertOk()
        ->assertSee('Application received')
        ->assertSee('not visible to students yet')
        ->assertSee('Expected decision by')
        ->assertSee('Preview my public profile')
        ->assertDontSee('You are live');
});

test('an approved teacher sees the live state instead of a stale review', function () {
    $teacher = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacher)
        ->get(route('teacher.approval.status'))
        ->assertOk()
        ->assertSee('You are live')
        ->assertSee('Go to dashboard')
        ->assertSee('View my public profile')
        ->assertDontSee('Application received');
});

test('a rejected application shows the reviewer notes and the way back', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->rejected()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.approval.status'))
        ->assertOk()
        ->assertSee('Action needed')
        ->assertSee('Please upload a clearer photo of your government ID.')
        ->assertSee('Update my application');
});

test('an application past its review promise gets an apology and a contact route', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 12:00:00'));

    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->pending()->for($teacher)->create([
        'submitted_at' => now()->subWeekdays(4),
    ]);

    $this->actingAs($teacher)
        ->get(route('teacher.approval.status'))
        ->assertOk()
        ->assertSee('This is taking longer than usual')
        ->assertSee('Contact our team');
});

test('the status page is guarded until the application is submitted', function () {
    $teacher = User::factory()->teacher()->create();
    TeacherProfile::factory()->incomplete()->for($teacher)->create();

    $this->actingAs($teacher)
        ->get(route('teacher.approval.status'))
        ->assertRedirect(route('teacher.verification'));
});
