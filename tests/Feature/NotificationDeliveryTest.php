<?php

use App\Enums\ClassificationStatus;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The database notification channel must work end to end: these notifications
 * are queried from the notifications table by the notifiable.
 *
 * @return array<string, mixed>
 */
function notificationScenario(): array
{
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 60000,
        'lesson_duration_minutes' => 60,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $lesson->grade_id]]);
    $teacher->lessons()->attach($lesson->id);

    $windowStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(18, 0);
    TeacherAvailabilitySlot::factory()
        ->on($windowStart->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'preferred_windows' => [[
            'starts_at' => $windowStart->toIso8601String(),
            'ends_at' => $windowStart->setTime(20, 0)->toIso8601String(),
        ]],
    ]);

    return compact('subject', 'lesson', 'teacher', 'teacherUser', 'student', 'request', 'windowStart');
}

it('stores an in-app notification for the student when a teacher accepts', function () {
    $scenario = notificationScenario();

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'accept',
            'starts_at' => $scenario['windowStart']->setTime(18, 0)->toIso8601String(),
        ])
        ->assertRedirect(route('teacher.requests.index'));

    $notification = $scenario['student']->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and(data_get($notification->data, 'title'))->toContain('accepted your request')
        ->and(data_get($notification->data, 'url'))->toBe(route('student.requests.show', $scenario['request']));
});

it('stores an in-app notification for matching teachers when a lesson is confirmed', function () {
    $scenario = notificationScenario();

    $scenario['request']->update([
        'subject_id' => null,
        'lesson_id' => null,
        'classification_status' => ClassificationStatus::LowConfidence,
    ]);

    $this->actingAs($scenario['student'])
        ->put(route('student.requests.lesson.update', $scenario['request']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $scenario['lesson']->id,
        ])
        ->assertRedirect(route('student.requests.show', $scenario['request']));

    $notification = $scenario['teacherUser']->fresh()->notifications()->first();

    expect($notification)->not->toBeNull()
        ->and(data_get($notification->data, 'title'))->toContain('Algebra')
        ->and(data_get($notification->data, 'url'))->toBe(route('teacher.requests.index'));
});
