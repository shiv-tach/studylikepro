<?php

use App\Enums\ApprovalStatus;
use App\Models\Subject;
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
    $profile = TeacherProfile::factory()->pending()->for($user)->create();
    $profile->availabilitySlots()->create([
        'day_of_week' => 1,
        'start_minute' => 18 * 60,
        'end_minute' => 21 * 60,
    ]);

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
    $profile = TeacherProfile::factory()->pending()->for($user)->create();
    $profile->availabilitySlots()->create([
        'day_of_week' => 1,
        'start_minute' => 18 * 60,
        'end_minute' => 21 * 60,
    ]);
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

test('the terms checklist keeps showing acceptance after the application is submitted', function () {
    $user = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)->post(route('teacher.verification.submit'), ['agree' => '1']);

    $config = verificationConfig($this->actingAs($user)->get(route('teacher.verification'))->assertOk());

    expect($config['canEditDocuments'])->toBeFalse()
        ->and($config['values']['agree'])->toBeTrue();
});

test('the terms checklist stays unticked until the teacher accepts them', function () {
    $user = onboardingTeacher();

    $config = verificationConfig($this->actingAs($user)->get(route('teacher.verification'))->assertOk());

    expect($config['values']['agree'])->toBeFalse();
});

test('a failed submission without the terms does not tick the checklist', function () {
    $user = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();

    $this->actingAs($user)
        ->post(route('teacher.verification.submit'))
        ->assertSessionHasErrors('agree');

    $config = verificationConfig($this->actingAs($user)->get(route('teacher.verification'))->assertOk());

    expect($config['values']['agree'])->toBeFalse();
});

test('rejected teachers can resubmit their application', function () {
    $user = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->rejected()->for($user)->create();
    $profile->availabilitySlots()->create([
        'day_of_week' => 1,
        'start_minute' => 18 * 60,
        'end_minute' => 21 * 60,
    ]);

    $subject = Subject::query()->active()->first() ?? Subject::factory()->create(['is_active' => true]);
    $profile->subjects()->attach($subject->id, [
        'status' => ApprovalStatus::Approved,
        'grade_levels' => [],
    ]);

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

test('the verification page ships the checklist, documents and notices as an island', function () {
    $user = onboardingTeacher();
    TeacherVerificationDocument::factory()->for($user->teacherProfile)->create(['type' => 'degree']);

    $response = $this->actingAs($user)->get(route('teacher.verification'))->assertOk();

    $response->assertSee('id="teacher-verification-app"', false);

    $config = verificationConfig($response);

    expect($config['canEditDocuments'])->toBeTrue()
        ->and($config['status']['current'])->toBe('draft')
        ->and($config['documents'])->toHaveCount(1)
        ->and($config['documents'][0]['type'])->toBe('degree')
        ->and($config['documents'][0]['isRequired'])->toBeFalse()
        // The id is the only required type, so it is the one offered first.
        ->and($config['selectedType'])->toBe('id_proof')
        ->and(collect($config['documentTypes'])->firstWhere('value', 'id_proof')['required'])->toBeTrue()
        ->and($config['currentStep'])->toBe(3)
        ->and($config['urls']['upload'])->toBe(route('teacher.verification.documents.store'));
});

test('an uploaded document is shipped with the flash notice for the toast', function () {
    Storage::fake('local');

    $user = onboardingTeacher();

    $this->actingAs($user)
        ->post(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('id-card.jpg'),
        ])
        ->assertRedirect(route('teacher.verification'));

    $config = verificationConfig($this->actingAs($user)->get(route('teacher.verification'))->assertOk());

    expect($config['notices'])->toHaveCount(1)
        ->and($config['notices'][0]['tone'])->toBe('emerald')
        ->and($config['highlightDocumentId'])->toBe($config['documents'][0]['id'])
        ->and($config['documents'][0]['originalName'])->toBe('id-card.jpg');
});

test('the upload returns the new document when the page asks for json', function () {
    Storage::fake('local');

    $user = onboardingTeacher();

    $response = $this->actingAs($user)
        ->postJson(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('id-card.jpg'),
        ])
        ->assertOk();

    $payload = $response->json();

    expect($payload['document']['type'])->toBe('id_proof')
        ->and($payload['document']['typeLabel'])->toBe('Government ID')
        ->and($payload['document']['isRequired'])->toBeTrue()
        ->and($payload['document']['destroyUrl'])->toContain('/verification/documents/')
        ->and($payload['notices'][0]['tone'])->toBe('emerald')
        ->and($payload['notices'][0]['message'])->toContain('submit your application');
});

test('a json upload reports the validation errors field by field', function () {
    Storage::fake('local');

    $this->actingAs(onboardingTeacher())
        ->postJson(route('teacher.verification.documents.store'), [
            'type' => 'passport',
            'document' => UploadedFile::fake()->create('virus.exe', 10),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['type', 'document']);
});

test('a json upload is refused with a notice once the documents are locked', function () {
    Storage::fake('local');

    $user = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->pending()->for($user)->create();
    $profile->availabilitySlots()->create([
        'day_of_week' => 1,
        'start_minute' => 18 * 60,
        'end_minute' => 21 * 60,
    ]);

    $this->actingAs($user)
        ->postJson(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('id.jpg'),
        ])
        ->assertStatus(409)
        ->assertJsonPath('notices.0.tone', 'amber');
});

test('a json removal answers with the removed id and a notice', function () {
    Storage::fake('local');

    $user = onboardingTeacher();
    $document = TeacherVerificationDocument::factory()->for($user->teacherProfile)->create();
    Storage::disk('local')->put($document->path, 'file-contents');

    $this->actingAs($user)
        ->deleteJson(route('teacher.verification.documents.destroy', $document))
        ->assertOk()
        ->assertJsonPath('removedId', $document->id)
        ->assertJsonPath('notices.0.tone', 'slate');

    expect(TeacherVerificationDocument::find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing($document->path);
});

test('students cannot access teacher verification routes', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('teacher.verification'))
        ->assertForbidden();
});
