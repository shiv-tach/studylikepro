<?php

use App\Models\Message;
use App\Models\User;
use App\Services\BookingTransitionService;
use App\Services\ConversationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('opens a lesson chat with a system message when a booking is confirmed', function () {
    $booking = ownedBooking();

    app(BookingTransitionService::class)->confirm($booking);
    $booking->refresh();

    $conversation = app(ConversationService::class)->forBooking($booking);

    expect($conversation->student_id)->toBe($booking->student_id)
        ->and($conversation->teacher_profile_id)->toBe($booking->teacher_profile_id)
        ->and($conversation->booking_id)->toBe($booking->id)
        ->and($conversation->messages()->count())->toBe(1)
        ->and($conversation->messages()->first()->isSystem())->toBeTrue();

    // The same booking always resolves to the same thread.
    expect(app(ConversationService::class)->forBooking($booking)->id)->toBe($conversation->id);
});

it('keeps the chat between the two participants', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk();
    $this->actingAs($scenario['teacherUser'])->get(route('messages.show', $conversation))->assertOk();

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('messages.show', $conversation))->assertForbidden();

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('messages.show', $conversation))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('messages.show', $conversation))->assertForbidden();
});

it('sends guests to the login page from the chat', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->get(route('messages.show', $conversation))->assertRedirect(route('login'));
    $this->get(route('messages.index'))->assertRedirect(route('login'));
});

it('only lists the threads a user takes part in', function () {
    $mine = classroomScenario();
    $theirs = classroomScenario();

    $myConversation = app(ConversationService::class)->forBooking($mine['booking']);
    app(ConversationService::class)->forBooking($theirs['booking']);

    $this->actingAs($mine['student'])
        ->get(route('messages.index'))
        ->assertOk()
        ->assertSee($mine['teacherUser']->name)
        ->assertDontSee($theirs['teacherUser']->name);

    expect($myConversation->isParticipant($mine['student']))->toBeTrue()
        ->and($myConversation->isParticipant($theirs['student']))->toBeFalse();
});

it('posts a message and returns it to the chat client', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $response = $this->actingAs($scenario['student'])
        ->postJson(route('messages.store', $conversation), ['body' => 'Should I revise quadratic equations?'])
        ->assertCreated();

    $response->assertJsonPath('message.mine', true)
        ->assertJsonPath('message.body', 'Should I revise quadratic equations?')
        ->assertJsonPath('message.sender', $scenario['student']->name);

    $message = Message::query()->latest('id')->first();

    expect($message->sender_id)->toBe($scenario['student']->id)
        ->and($message->conversation_id)->toBe($conversation->id)
        ->and($conversation->fresh()->last_message_at)->not->toBeNull();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('Should I revise quadratic equations?');
});

it('requires a body or a photo and validates the photo', function () {
    Storage::fake('public');

    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])
        ->postJson(route('messages.store', $conversation), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');

    $this->actingAs($scenario['student'])
        ->postJson(route('messages.store', $conversation), ['image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
        ->assertStatus(422)
        ->assertJsonValidationErrors('image');
});

it('stores a photo message with an attachment url', function () {
    Storage::fake('public');

    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])
        ->postJson(route('messages.store', $conversation), [
            'body' => 'Here is the question.',
            'image' => UploadedFile::fake()->image('question.jpg'),
        ])
        ->assertCreated();

    $message = Message::query()->latest('id')->first();

    expect($message->attachment_path)->not->toBeNull()
        ->and($message->attachmentUrl())->toContain('/storage/messages/');
});

it('polls for new messages after a cursor and reports the unread count', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $first = $conversation->messages()->create(['sender_id' => $scenario['student']->id, 'body' => 'Hello!']);
    $second = $conversation->messages()->create(['sender_id' => $scenario['teacherUser']->id, 'body' => 'Bring your notes.']);
    $third = $conversation->messages()->create(['sender_id' => $scenario['teacherUser']->id, 'body' => 'We start with algebra.']);

    $payload = $this->actingAs($scenario['student'])
        ->getJson(route('messages.poll', $conversation).'?after='.$first->id)
        ->assertOk()
        ->json();

    expect($payload['messages'])->toHaveCount(2)
        ->and(array_column($payload['messages'], 'id'))->toBe([$second->id, $third->id])
        ->and($payload['messages'][0]['mine'])->toBeFalse()
        ->and($payload['messages'][0]['sender'])->toBe($scenario['teacherUser']->name)
        ->and($payload['latest_id'])->toBe($third->id)
        ->and($payload['unread'])->toBe(2);

    // Polling acknowledged the thread for this user.
    expect($conversation->fresh()->lastReadIdFor($scenario['student']))->toBe($third->id);

    $again = $this->actingAs($scenario['student'])
        ->getJson(route('messages.poll', $conversation).'?after='.$third->id)
        ->assertOk()
        ->json();

    expect($again['messages'])->toBe([])
        ->and($again['unread'])->toBe(0);
});

it('counts unread messages until the thread is opened', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);
    $conversations = app(ConversationService::class);

    $conversation->messages()->create(['sender_id' => $scenario['teacherUser']->id, 'body' => 'Are you ready for tomorrow?']);

    expect($conversations->unreadCountFor($scenario['student']))->toBe(1)
        ->and($conversations->unreadCountFor($scenario['teacherUser']))->toBe(0);

    $this->actingAs($scenario['student'])->get(route('messages.index'))
        ->assertOk()
        ->assertSee('1 unread message waiting for you');

    // Opening the thread marks everything read.
    $this->actingAs($scenario['student'])->get(route('messages.show', $conversation))->assertOk();

    expect($conversations->unreadCountFor($scenario['student']))->toBe(0);
});

it('shows the unread badge in the sidebar', function () {
    $scenario = classroomScenario();
    app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertSee(route('messages.index'), false)
        ->assertSee('Messages');
});
