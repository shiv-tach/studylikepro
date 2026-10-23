<?php

use App\Models\Dispute;
use App\Models\Message;
use App\Services\ConversationService;

/**
 * The lesson thread at /messages/{conversation} is rendered by
 * resources/js/messages-thread, so the Blade view only has to hand the Vue app a
 * correct config island (see `messagesThreadConfig()` in tests/Pest.php). These
 * tests pin that contract down: the perspective, the first page of messages, the
 * lesson line, the route map and the report form's state.
 */
test('the thread island carries the perspective, the lesson line and the route map', function () {
    $scenario = classroomScenario();
    $service = app(ConversationService::class);
    $conversation = $service->forBooking($scenario['booking']);
    $service->post($conversation, $scenario['student'], 'Should I revise quadratics?');

    $config = messagesThreadConfig(
        $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk()
    );

    expect($config['counterpart'])->toBe($scenario['teacherUser']->name)
        ->and($config['initials'])->toBe(mb_strtoupper(mb_substr($scenario['teacherUser']->name, 0, 2)))
        ->and($config['theme']['preset'])->toBe('classic')
        ->and($config['urls']['index'])->toBe(route('messages.index'))
        ->and($config['urls']['poll'])->toBe(route('messages.poll', $conversation))
        ->and($config['urls']['store'])->toBe(route('messages.store', $conversation))
        ->and($config['urls']['report'])->toBe(route('messages.report', $conversation))
        ->and($config['lesson']['url'])->toBe(route('student.bookings.show', $scenario['booking']))
        ->and($config['lesson']['schedule'])->toMatch('/^[A-Z][a-z]{2} \d{2} [A-Z][a-z]{2} \d{4}, \d{1,2}:\d{2} [AP]M$/')
        ->and($config['report']['open'])->toBeFalse()
        ->and($config['flash'])->toBeNull()
        ->and(collect($config['report']['reasons'])->pluck('value')->all())->toBe(array_keys(Dispute::REASONS));
});

test('the island reuses the payload the poller returns, from the reader perspective', function () {
    $scenario = classroomScenario();
    $service = app(ConversationService::class);
    $conversation = $service->forBooking($scenario['booking']);
    $service->systemMessage($scenario['booking'], 'Lesson delivered');
    $service->post($conversation, $scenario['student'], 'Thanks!');
    $service->post($conversation, $scenario['teacherUser'], 'See you next week.');

    $config = messagesThreadConfig(
        $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk()
    );

    $messages = collect($config['messages']);

    expect($messages)->toHaveCount(3)
        // Oldest first, so the thread reads the way a chat does.
        ->and($messages->pluck('type')->all())->toBe(['system', 'text', 'text'])
        ->and($messages->pluck('mine')->all())->toBe([false, true, false])
        ->and($messages[0]['sender'])->toBeNull()
        ->and($messages[1]['sender'])->toBe($scenario['student']->name)
        ->and($messages[2]['body'])->toBe('See you next week.');

    // The teacher sees the same thread from the other side.
    $teachersView = messagesThreadConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('messages.show', $conversation))->assertOk()
    );

    expect(collect($teachersView['messages'])->pluck('mine')->all())->toBe([false, false, true])
        ->and($teachersView['counterpart'])->toBe($scenario['student']->name);
});

test('opening the thread marks it read and carries no unread flash', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    Message::factory()->from($scenario['teacherUser'])->create([
        'conversation_id' => $conversation->id,
        'body' => 'Are you ready for tomorrow?',
    ]);

    $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk();

    expect(app(ConversationService::class)->unreadCountFor($scenario['student']))->toBe(0);
});

test('an open report closes the dialog and shows the review chip', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $config = messagesThreadConfig(
        $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk()
    );

    expect($config['report']['open'])->toBeFalse()
        ->and($config['copy']['reportCta'])->toBe('Report a problem');

    $this->actingAs($scenario['student'])
        ->post(route('messages.report', $conversation), ['reason' => Dispute::REASON_NO_SHOW])
        ->assertSessionHas('status', 'report-received');

    $config = messagesThreadConfig(
        $this->actingAs($scenario['student'])
            ->get(route('messages.show', $conversation))
            ->assertOk()
            ->assertSee('Report under review')
    );

    expect($config['report']['open'])->toBeTrue()
        // The island escapes non-ASCII as \uXXXX, so the banner text is asserted
        // through the decoded payload rather than the raw HTML.
        ->and($config['flash']['tone'])->toBe('emerald')
        ->and($config['flash']['message'])->toBe('Thanks — our support team has this conversation and will follow up by email or in the app.');
});

test('a rejected report hands the dialog back its values and its message', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])
        ->post(route('messages.report', $conversation), ['reason' => 'not-a-reason', 'details' => 'They left after ten minutes.'])
        ->assertSessionHasErrors('reason');

    $config = messagesThreadConfig(
        $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk()
    );

    expect($config['report']['errors']['reason'])->not->toBeNull()
        ->and($config['report']['old']['details'])->toBe('They left after ten minutes.')
        ->and($config['report']['open'])->toBeFalse();
});

test('a caption-less photo reaches the thread with its attachment url', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    Message::factory()->from($scenario['teacherUser'])->create([
        'conversation_id' => $conversation->id,
        'body' => null,
        'attachment_path' => 'messages/question.jpg',
    ]);

    $config = messagesThreadConfig(
        $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk()
    );

    $message = collect($config['messages'])->firstWhere('mine', false);

    expect($message['body'])->toBeNull()
        ->and($message['attachment_url'])->toContain('/storage/messages/question.jpg');
});
