<?php

use App\Models\Message;
use App\Models\User;
use App\Services\ConversationService;

/**
 * The lesson-chat inbox at /messages is rendered by resources/js/messages, so the
 * Blade view only has to hand the Vue app a correct config island (see
 * `messagesConfig()` in tests/Pest.php). These tests pin that contract down: the
 * perspective, the unread counts, the one-line previews and the route map.
 */
test('the inbox island carries the student perspective and the route map', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $config = messagesConfig(
        $this->actingAs($scenario['student'])->get(route('messages.index'))->assertOk()
    );

    expect($config['theme']['preset'])->toBe('classic')
        ->and($config['totalUnread'])->toBe(0)
        ->and($config['urls']['empty'])->toBe(route('student.bookings.index'))
        ->and($config['copy']['unreadSummary'])->toBeNull()
        ->and(collect($config['threads'])->pluck('id')->all())->toBe([$conversation->id]);

    $thread = $config['threads'][0];

    expect($thread['url'])->toBe(route('messages.show', $conversation))
        ->and($thread['counterpart'])->toBe($scenario['teacherUser']->name)
        ->and($thread['initials'])->toBe(mb_strtoupper(mb_substr($scenario['teacherUser']->name, 0, 2)))
        ->and($thread['unread'])->toBe(0)
        ->and($thread['unreadLabel'])->toBeNull()
        ->and($thread['schedule'])->toMatch('/^[A-Z][a-z]{2} \d{2} [A-Z][a-z]{2} \d{4}, \d{2}:\d{2}$/')
        ->and($thread['preview'])->toBe('No messages yet — say hello.')
        ->and($thread['previewEmpty'])->toBeTrue()
        ->and($thread['previewSystem'])->toBeFalse();
});

test('an unread thread raises the banner and the badge', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    Message::factory()->from($scenario['teacherUser'])->create([
        'conversation_id' => $conversation->id,
        'body' => 'See you in five minutes.',
    ]);

    $config = messagesConfig(
        $this->actingAs($scenario['student'])->get(route('messages.index'))->assertOk()
    );

    expect($config['totalUnread'])->toBe(1)
        ->and($config['copy']['unreadSummary'])->toBe('1 unread message waiting for you.');

    $thread = $config['threads'][0];

    expect($thread['unread'])->toBe(1)
        ->and($thread['unreadLabel'])->toBe('1 unread message')
        ->and($thread['preview'])->toBe('See you in five minutes.')
        ->and($thread['previewEmpty'])->toBeFalse()
        ->and($thread['previewSystem'])->toBeFalse();
});

test('the thread preview follows the latest message', function () {
    $scenario = classroomScenario();
    $service = app(ConversationService::class);
    $conversation = $service->forBooking($scenario['booking']);

    // An own message is prefixed so the sender is obvious at a glance.
    $service->post($conversation, $scenario['student'], 'Should I revise quadratics?');

    $thread = collect(messagesConfig(
        $this->actingAs($scenario['student'])->get(route('messages.index'))->assertOk()
    )['threads'])->firstWhere('id', $conversation->id);

    expect($thread['preview'])->toBe('You: Should I revise quadratics?');

    // A caption-less photo reads as "Photo".
    Message::factory()->from($scenario['teacherUser'])->create([
        'conversation_id' => $conversation->id,
        'body' => null,
        'attachment_path' => 'messages/question.jpg',
    ]);

    $thread = collect(messagesConfig(
        $this->actingAs($scenario['student'])->get(route('messages.index'))->assertOk()
    )['threads'])->firstWhere('id', $conversation->id);

    expect($thread['preview'])->toBe('Photo');

    // A system notice keeps its own wording and is flagged for italics.
    $service->systemMessage($scenario['booking'], 'Lesson rescheduled to Sunday.');

    $thread = collect(messagesConfig(
        $this->actingAs($scenario['student'])->get(route('messages.index'))->assertOk()
    )['threads'])->firstWhere('id', $conversation->id);

    expect($thread['preview'])->toBe('Lesson rescheduled to Sunday.')
        ->and($thread['previewSystem'])->toBeTrue();
});

test('the teacher perspective points the empty state at the schedule', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $config = messagesConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('messages.index'))->assertOk()
    );

    expect($config['urls']['empty'])->toBe(route('teacher.schedule.index'));
    expect($config['copy']['emptyCta'])->toBe('Open my schedule');

    $thread = collect($config['threads'])->firstWhere('id', $conversation->id);

    expect($thread['counterpart'])->toBe($scenario['student']->name);
});

test('an empty inbox carries the empty copy and its call to action', function () {
    $student = User::factory()->student()->onboarded()->create();

    $config = messagesConfig(
        $this->actingAs($student)->get(route('messages.index'))->assertOk()
    );

    expect($config['threads'])->toBe([])
        ->and($config['totalUnread'])->toBe(0)
        ->and($config['copy']['emptyTitle'])->toBe('No lesson chats yet')
        ->and($config['copy']['emptyCta'])->toBe('See my lessons')
        ->and($config['urls']['empty'])->toBe(route('student.bookings.index'));
});
