<?php

use App\Enums\ApprovalStatus;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherVerificationDocument;
use App\Models\User;
use App\Notifications\TeacherVerificationApproved;
use App\Notifications\TeacherVerificationRejected;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * @return array{0: User, 1: TeacherProfile}
 */
function createPendingTeacherAccount(): array
{
    $user = User::factory()->teacher()->create();
    $profile = TeacherProfile::factory()->pending()->for($user)->create();

    return [$user, $profile];
}

test('guests and non admins cannot access the verification queue', function () {
    $this->get(route('admin.verifications.index'))->assertRedirect('/login');

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('admin.verifications.index'))
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->create())
        ->post(route('admin.verifications.approve', TeacherProfile::factory()->pending()->create()))
        ->assertForbidden();
});

test('the queue lists pending applications by default', function () {
    [, $pending] = createPendingTeacherAccount();
    $approved = TeacherProfile::factory()->approved()->for(User::factory()->teacher())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.verifications.index'))
        ->assertOk()
        ->assertSee($pending->user->name)
        ->assertDontSee($approved->user->name);
});

test('admins can view a single application with documents', function () {
    [, $profile] = createPendingTeacherAccount();
    TeacherVerificationDocument::factory()->for($profile)->create(['original_name' => 'my-id.pdf']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.verifications.show', $profile))
        ->assertOk()
        ->assertSee($profile->headline)
        ->assertSee('my-id.pdf');
});

test('admins can approve a pending application and the teacher is notified', function () {
    Notification::fake();

    [$user, $profile] = createPendingTeacherAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.verifications.approve', $profile))
        ->assertRedirect(route('admin.verifications.index'))
        ->assertSessionHas('status', 'teacher-approved');

    $profile->refresh();

    expect($profile->verification_status->value)->toBe('approved')
        ->and($profile->verified_at)->not->toBeNull();

    Notification::assertSentTo($user, TeacherVerificationApproved::class);
});

test('approving grants the checked subject its grade scope and lessons', function () {
    Notification::fake();

    [, $profile] = createPendingTeacherAccount();

    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade = Grade::query()->where('education_level_id', $ol->id)->where('number', 11)->firstOrFail();

    $subject = Subject::factory()->create([
        'name' => 'Verification Scope Mathematics',
        'slug' => 'verification-scope-mathematics',
        'education_level_id' => $ol->id,
    ]);
    $lesson = Lesson::factory()->for($subject)->create(['grade_id' => $grade->id]);

    $profile->subjects()->attach($subject->id, ['status' => ApprovalStatus::Pending]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.verifications.approve', $profile), ['subject_ids' => [$subject->id]])
        ->assertRedirect(route('admin.verifications.index'))
        ->assertSessionHas('status', 'teacher-approved');

    $pivot = $profile->subjects()->where('subjects.id', $subject->id)->firstOrFail()->pivot;

    expect($pivot->status)->toBe(ApprovalStatus::Approved)
        ->and($pivot->approved_at)->not->toBeNull()
        ->and(array_map('intval', $pivot->grade_levels))->toContain($grade->id)
        ->and($profile->lessons()->pluck('lessons.id')->all())->toContain($lesson->id);
});

test('unchecked subjects are rejected and lose their lessons', function () {
    Notification::fake();

    [, $profile] = createPendingTeacherAccount();

    $ol = EducationLevel::query()->where('key', 'ol')->firstOrFail();
    $grade = Grade::query()->where('education_level_id', $ol->id)->where('number', 11)->firstOrFail();

    $kept = Subject::factory()->create(['education_level_id' => $ol->id]);
    $dropped = Subject::factory()->create(['education_level_id' => $ol->id]);

    $keptLesson = Lesson::factory()->for($kept)->create(['grade_id' => $grade->id]);
    $droppedLesson = Lesson::factory()->for($dropped)->create(['grade_id' => $grade->id]);

    $profile->subjects()->attach($kept->id, ['status' => ApprovalStatus::Pending]);
    $profile->subjects()->attach($dropped->id, ['status' => ApprovalStatus::Pending]);
    $profile->lessons()->sync([$keptLesson->id, $droppedLesson->id]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.verifications.approve', $profile), ['subject_ids' => [$kept->id]])
        ->assertSessionHas('status', 'teacher-approved');

    $droppedPivot = $profile->subjects()->where('subjects.id', $dropped->id)->firstOrFail()->pivot;

    expect($droppedPivot->status)->toBe(ApprovalStatus::Rejected)
        ->and($droppedPivot->approved_at)->toBeNull()
        ->and($profile->lessons()->pluck('lessons.id')->all())->toBe([$keptLesson->id]);
});

test('admins can reject a pending application with a reason', function () {
    Notification::fake();

    [$user, $profile] = createPendingTeacherAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.verifications.reject', $profile), ['reason' => 'The uploaded ID is unreadable.'])
        ->assertRedirect(route('admin.verifications.index'))
        ->assertSessionHas('status', 'teacher-rejected');

    $profile->refresh();

    expect($profile->verification_status->value)->toBe('rejected')
        ->and($profile->verification_notes)->toBe('The uploaded ID is unreadable.');

    Notification::assertSentTo($user, TeacherVerificationRejected::class);
});

test('rejecting requires a reason', function () {
    [, $profile] = createPendingTeacherAccount();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.verifications.reject', $profile), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($profile->fresh()->verification_status->value)->toBe('pending');
});

test('decisions are only possible while an application is pending', function () {
    Notification::fake();

    $profile = TeacherProfile::factory()->approved()->for(User::factory()->teacher())->create();

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.verifications.show', $profile))
        ->post(route('admin.verifications.approve', $profile))
        ->assertRedirect(route('admin.verifications.show', $profile))
        ->assertSessionHas('status', 'verification-not-pending');

    Notification::assertNothingSent();
});

test('private documents stream to the owner and admins only', function () {
    Storage::fake('local');

    [$owner, $profile] = createPendingTeacherAccount();
    Storage::disk('local')->put('verification-documents/1/id.pdf', 'secret-document');
    $document = TeacherVerificationDocument::factory()->for($profile)->create([
        'path' => 'verification-documents/1/id.pdf',
    ]);

    $this->actingAs($owner)
        ->get(route('verification-documents.show', $document))
        ->assertOk()
        ->assertStreamedContent('secret-document');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('verification-documents.show', $document))
        ->assertOk();

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('verification-documents.show', $document))
        ->assertForbidden();
});

test('guests cannot stream verification documents', function () {
    Storage::fake('local');

    $document = TeacherVerificationDocument::factory()->create();

    $this->get(route('verification-documents.show', $document))->assertRedirect('/login');
});

test('missing document files return 404', function () {
    Storage::fake('local');

    [, $profile] = createPendingTeacherAccount();
    $document = TeacherVerificationDocument::factory()->for($profile)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('verification-documents.show', $document))
        ->assertNotFound();
});
