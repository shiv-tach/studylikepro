<?php

use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('the verification page redirects until the profile is complete', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.verification'))
        ->assertRedirect(route('teacher.profile'));
});

test('teachers can upload a verification document', function () {
    Storage::fake('local');

    $user = onboardingTeacher();

    $this->actingAs($user)
        ->post(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('id-card.jpg'),
        ])
        ->assertRedirect(route('teacher.verification'));

    $document = $user->teacherProfile->documents()->first();

    expect($document)->not->toBeNull()
        ->and($document->type)->toBe('id_proof')
        ->and($document->original_name)->toBe('id-card.jpg');

    Storage::disk('local')->assertExists($document->path);
});

test('invalid document uploads are rejected', function () {
    Storage::fake('local');

    $user = onboardingTeacher();

    $this->actingAs($user)
        ->post(route('teacher.verification.documents.store'), [
            'type' => 'passport',
            'document' => UploadedFile::fake()->create('virus.exe', 10),
        ])
        ->assertSessionHasErrors(['type', 'document']);

    expect($user->teacherProfile->documents()->count())->toBe(0);
});

test('documents cannot be uploaded while the application is pending review', function () {
    Storage::fake('local');

    $user = User::factory()->teacher()->create();
    TeacherProfile::factory()->pending()->for($user)->create();

    $this->actingAs($user)
        ->post(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('id.jpg'),
        ])
        ->assertRedirect(route('teacher.verification'))
        ->assertSessionHas('status', 'documents-locked');

    expect($user->teacherProfile->documents()->count())->toBe(0);
});

test('teachers can remove their documents before submission', function () {
    Storage::fake('local');

    $user = onboardingTeacher();
    $document = TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();
    Storage::disk('local')->put($document->path, 'file-contents');

    $this->actingAs($user)
        ->delete(route('teacher.verification.documents.destroy', $document))
        ->assertRedirect(route('teacher.verification'));

    expect(TeacherVerificationDocument::find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($document->path);
});

test('teachers cannot delete another teachers document', function () {
    Storage::fake('local');

    $owner = onboardingTeacher();
    $document = TeacherVerificationDocument::factory()->for($owner->teacherProfile)->create();

    $other = onboardingTeacher();

    $this->actingAs($other)
        ->delete(route('teacher.verification.documents.destroy', $document))
        ->assertForbidden();

    expect(TeacherVerificationDocument::find($document->id))->not->toBeNull();
});

test('documents are locked after the application is submitted', function () {
    Storage::fake('local');

    $user = User::factory()->teacher()->create();
    TeacherProfile::factory()->pending()->for($user)->create();
    $document = TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)
        ->delete(route('teacher.verification.documents.destroy', $document))
        ->assertForbidden();
});

test('submitting without a government ID fails', function () {
    $user = onboardingTeacher();

    $this->actingAs($user)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertSessionHasErrors('document');

    expect($user->teacherProfile->fresh()->verification_status->value)->toBe('draft');
});

test('submitting without accepting the terms fails', function () {
    $user = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)
        ->post(route('teacher.verification.submit'))
        ->assertSessionHasErrors('agree');

    expect($user->teacherProfile->fresh()->verification_status->value)->toBe('draft');
});

test('teachers can submit their application for review', function () {
    $user = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertRedirect(route('teacher.verification'))
        ->assertSessionHas('status', 'submitted-for-review');

    $profile = $user->teacherProfile->fresh();

    expect($profile->verification_status->value)->toBe('pending')
        ->and($profile->submitted_at)->not->toBeNull()
        ->and($profile->agreement_accepted_at)->not->toBeNull()
        ->and($profile->agreement_version)->toBe((string) config('studylikepro.support.policy_version'));
});

test('rejected teachers can resubmit their application', function () {
    $user = User::factory()->teacher()->create();
    TeacherProfile::factory()->rejected()->for($user)->create();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertRedirect(route('teacher.verification'));

    $profile = $user->teacherProfile->fresh();

    expect($profile->verification_status->value)->toBe('pending')
        ->and($profile->verification_notes)->toBeNull();
});

test('the verification page points teachers to upload an ID before they can submit', function () {
    $user = onboardingTeacher();

    $this->actingAs($user)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('Government ID required')
        ->assertSee('Upload now')
        ->assertSee('Upload your government ID above to enable submission.');
});

test('the verification page shows the submission checklist and confirmation once the ID is uploaded', function () {
    $user = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)
        ->get(route('teacher.verification'))
        ->assertOk()
        ->assertSee('Government ID uploaded')
        ->assertDontSee('Government ID required')
        ->assertSee('Submit your application for review?');
});

test('students cannot access teacher verification routes', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('teacher.verification'))
        ->assertForbidden();
});
