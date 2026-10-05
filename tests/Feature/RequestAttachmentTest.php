<?php

use App\Models\RequestAttachment;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\Topic;
use App\Models\TutoringRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * @return array<string, mixed>
 */
function requestAttachmentScenario(): array
{
    Storage::fake('local');

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $topic = Topic::factory()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    $student = User::factory()->student()->onboarded()->create();

    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
    ]);

    $attachment = RequestAttachment::factory()->create([
        'tutoring_request_id' => $request->id,
        'path' => 'tutoring-requests/'.$request->id.'/question.png',
        'original_name' => 'question.png',
        'mime_type' => 'image/png',
    ]);

    Storage::disk('local')->put($attachment->path, 'question-image-bytes');

    return compact('subject', 'topic', 'student', 'request', 'attachment');
}

it('lets the owner, an admin, and a matching teacher download the attachment', function () {
    $scenario = requestAttachmentScenario();

    $matching = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);
    $matching->topics()->attach($scenario['topic']->id);

    $ownerResponse = $this->actingAs($scenario['student'])
        ->get(route('request-attachments.show', $scenario['attachment']))
        ->assertOk();

    expect($ownerResponse->headers->get('content-disposition'))->toContain('question.png')
        ->and($ownerResponse->streamedContent())->toBe('question-image-bytes');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('request-attachments.show', $scenario['attachment']))
        ->assertOk();

    $this->actingAs($matching->user)
        ->get(route('request-attachments.show', $scenario['attachment']))
        ->assertOk();
});

it('blocks unrelated teachers and other students', function () {
    $scenario = requestAttachmentScenario();

    $outsider = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);

    $this->actingAs($outsider->user)
        ->get(route('request-attachments.show', $scenario['attachment']))
        ->assertForbidden();

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('request-attachments.show', $scenario['attachment']))
        ->assertForbidden();
});

it('sends guests to the login page', function () {
    $scenario = requestAttachmentScenario();

    $this->get(route('request-attachments.show', $scenario['attachment']))
        ->assertRedirect(route('login'));
});

it('returns not found when the stored file has gone missing', function () {
    $scenario = requestAttachmentScenario();

    Storage::disk('local')->delete($scenario['attachment']->path);

    $this->actingAs($scenario['student'])
        ->get(route('request-attachments.show', $scenario['attachment']))
        ->assertNotFound();
});
