<?php

use App\Models\User;
use App\Notifications\DailyDigest;
use App\Notifications\LessonCompleted;
use Illuminate\Support\Facades\Notification;

it('lists the in-app notifications with their title, body and read state', function () {
    $scenario = paidBookingScenario();

    $scenario['student']->notify(new LessonCompleted($scenario['booking']));
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $this->actingAs($scenario['student'])
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Lesson completed')
        ->assertSee('Mark all as read');

    expect($scenario['student']->unreadNotifications()->count())->toBe(2);
});

it('marks a notification read when it is opened and follows its link', function () {
    $scenario = paidBookingScenario();
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $notification = $scenario['student']->notifications()->firstOrFail();

    $this->actingAs($scenario['student'])
        ->post(route('notifications.open', $notification->id))
        ->assertRedirect(route('student.bookings.show', $scenario['booking']));

    expect($notification->fresh()->read_at)->not->toBeNull()
        ->and($scenario['student']->unreadNotifications()->count())->toBe(0);
});

it('never opens somebody else\'s notification', function () {
    $scenario = paidBookingScenario();
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $notification = $scenario['student']->notifications()->firstOrFail();

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->post(route('notifications.open', $notification->id))
        ->assertNotFound();

    expect($notification->fresh()->read_at)->toBeNull();
});

it('clears the whole bell with one action', function () {
    $scenario = paidBookingScenario();
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $this->actingAs($scenario['student'])
        ->post(route('notifications.read-all'))
        ->assertRedirect()
        ->assertSessionHas('status', 'notifications-read');

    expect($scenario['student']->unreadNotifications()->count())->toBe(0);
});

it('reports the unread count for the bell', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['student'])
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('unread', 0);

    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $this->actingAs($scenario['student'])
        ->getJson(route('notifications.unread-count'))
        ->assertOk()
        ->assertJsonPath('unread', 1);
});

it('caps the bell badge once the pile gets deep', function () {
    $scenario = paidBookingScenario();

    for ($i = 0; $i < 12; $i++) {
        $scenario['student']->notify(new LessonCompleted($scenario['booking']));
    }

    $this->actingAs($scenario['student'])
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertSee('9+')
        ->assertSee(route('notifications.index'), false);
});

it('honours the email preference on every notification', function () {
    $scenario = paidBookingScenario();
    $notification = new LessonCompleted($scenario['booking']);

    expect($notification->via($scenario['student']))->toBe(['mail', 'database']);

    $this->actingAs($scenario['student'])
        ->post(route('settings.notifications.update'), [])
        ->assertRedirect(route('settings.index'));

    $student = $scenario['student']->fresh();

    expect($student->wantsEmailNotifications())->toBeFalse()
        ->and($notification->via($student))->toBe(['database']);

    // In-app delivery keeps working with email switched off.
    Notification::fake();
    $student->notify($notification);
    Notification::assertSentTo($student, LessonCompleted::class);

    $this->actingAs($student)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('Save notification preferences')
        ->assertDontSee('checked', false);

    $this->actingAs($student)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertSee('Emails are off')
        ->assertSee('Turn emails on');
});

it('turns emails back on from the settings page', function () {
    $student = User::factory()->student()->onboarded()->create([
        'notification_preferences' => ['email' => false],
    ]);

    $this->actingAs($student)
        ->post(route('settings.notifications.update'), ['email' => '1'])
        ->assertRedirect(route('settings.index'));

    expect($student->fresh()->wantsEmailNotifications())->toBeTrue();
});

it('sends the daily digest to people with lessons or unread activity', function () {
    Notification::fake();

    $scenario = classroomScenario();
    $idle = User::factory()->student()->onboarded()->create();

    $this->artisan('studylikepro:send-daily-digest')->assertSuccessful();

    Notification::assertSentTo($scenario['student'], DailyDigest::class);
    Notification::assertSentTo($scenario['teacherUser'], DailyDigest::class);
    Notification::assertNotSentTo($idle, DailyDigest::class);
});

it('skips the digest when email is switched off or there is nothing to say', function () {
    Notification::fake();

    $scenario = classroomScenario();
    $scenario['student']->update(['notification_preferences' => ['email' => false]]);

    $this->artisan('studylikepro:send-daily-digest')->assertSuccessful();

    Notification::assertNotSentTo($scenario['student']->fresh(), DailyDigest::class);
    Notification::assertSentTo($scenario['teacherUser'], DailyDigest::class);
});

it('mentions today\'s lesson in the digest mail', function () {
    $scenario = classroomScenario();

    $mail = (new DailyDigest([
        ['title' => '45 minute Mathematics lesson with Priya Verma', 'detail' => '17:00 (in about 2 hours)'],
    ], 3, 1))->toMail($scenario['student']);

    $rendered = (string) $mail->render();

    expect($rendered)->toContain('45 minute Mathematics lesson with Priya Verma')
        ->and($rendered)->toContain('3 unread messages')
        ->and($rendered)->toContain('1 notification');
});
