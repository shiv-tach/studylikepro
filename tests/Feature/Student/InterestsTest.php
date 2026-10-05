<?php

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('the interests page requires onboarding', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('student.interests.edit'))
        ->assertRedirect(route('student.profile'));
});

test('students can save subject and topic interests', function () {
    $math = Subject::factory()->create();
    $algebra = Topic::factory()->for($math)->create();
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.interests.update'), [
            'subjects' => [$math->id],
            'topics' => [$algebra->id],
        ])
        ->assertRedirect(route('student.interests.edit'));

    expect($user->fresh()->interestedSubjects()->pluck('subjects.id')->all())->toBe([$math->id])
        ->and($user->fresh()->interestedTopics()->pluck('topics.id')->all())->toBe([$algebra->id]);
});

test('topics from unselected subjects are dropped on save', function () {
    $math = Subject::factory()->create();
    $english = Subject::factory()->create();
    $englishTopic = Topic::factory()->for($english)->create();
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.interests.update'), [
            'subjects' => [$math->id],
            'topics' => [$englishTopic->id],
        ])
        ->assertRedirect();

    expect($user->fresh()->interestedTopics()->count())->toBe(0);
});

test('interests reject inactive subjects', function () {
    $inactive = Subject::factory()->inactive()->create();
    $user = User::factory()->student()->onboarded()->create();

    $this->actingAs($user)
        ->put(route('student.interests.update'), ['subjects' => [$inactive->id]])
        ->assertSessionHasErrors('subjects.0');
});

test('teachers cannot update student interests', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->put(route('student.interests.update'), ['subjects' => []])
        ->assertForbidden();
});
