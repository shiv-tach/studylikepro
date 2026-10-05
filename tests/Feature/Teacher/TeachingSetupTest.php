<?php

use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;

test('the subjects page requires an onboarded teacher', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.subjects.index'))
        ->assertRedirect(route('teacher.profile'));
});

test('teachers can select their subjects', function () {
    $math = Subject::factory()->create(['name' => 'Mathematics']);
    $physics = Subject::factory()->create(['name' => 'Physics']);
    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => [$math->id, $physics->id]])
        ->assertRedirect(route('teacher.subjects.index'));

    expect($user->teacherProfile->subjects()->pluck('subjects.id')->all())
        ->toEqualCanonicalizing([$math->id, $physics->id]);
});

test('removing a subject also removes its topics', function () {
    $math = Subject::factory()->create();
    $topic = Topic::factory()->for($math)->create();
    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id]);
    $profile->topics()->sync([$topic->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.store'), ['subjects' => []])
        ->assertRedirect();

    expect($profile->fresh()->subjects()->count())->toBe(0)
        ->and($profile->fresh()->topics()->count())->toBe(0);
});

test('teachers can configure topics, grade levels and a rate override per subject', function () {
    $math = Subject::factory()->create();
    [$algebra, $geometry] = Topic::factory()->for($math)->count(2)->create();
    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [
            'topics' => [$algebra->id, $geometry->id],
            'grade_levels' => ['high_school', 'college'],
            'rate_per_hour' => 750,
        ])
        ->assertRedirect(route('teacher.subjects.index'));

    $profile->refresh();

    expect($profile->topics()->pluck('topics.id')->all())->toEqualCanonicalizing([$algebra->id, $geometry->id]);

    $pivot = $profile->subjects()->where('subjects.id', $math->id)->first()->pivot;

    expect($pivot->grade_levels)->toBe(['high_school', 'college'])
        ->and($pivot->rate_per_hour_minor)->toBe(75000);
});

test('updating one subject keeps topics of other subjects', function () {
    $math = Subject::factory()->create();
    $physics = Subject::factory()->create();
    $algebra = Topic::factory()->for($math)->create();
    $mechanics = Topic::factory()->for($physics)->create();
    $user = User::factory()->teacher()->onboarded()->create();
    $profile = $user->teacherProfile;

    $profile->subjects()->sync([$math->id, $physics->id]);
    $profile->topics()->sync([$algebra->id, $mechanics->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $physics), ['topics' => [$mechanics->id]])
        ->assertRedirect();

    expect($profile->fresh()->topics()->pluck('topics.id')->all())
        ->toEqualCanonicalizing([$algebra->id, $mechanics->id]);
});

test('topics from another subject are rejected', function () {
    $math = Subject::factory()->create();
    $english = Subject::factory()->create();
    $englishTopic = Topic::factory()->for($english)->create();
    $user = User::factory()->teacher()->onboarded()->create();

    $user->teacherProfile->subjects()->sync([$math->id]);

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), ['topics' => [$englishTopic->id]])
        ->assertSessionHasErrors('topics.0');
});

test('teachers cannot configure a subject they do not teach', function () {
    $math = Subject::factory()->create();
    $user = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($user)
        ->post(route('teacher.subjects.update', $math), [])
        ->assertForbidden();
});
