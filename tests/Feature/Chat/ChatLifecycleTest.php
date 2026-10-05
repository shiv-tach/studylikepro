<?php

use App\Enums\MessageType;
use App\Models\Dispute;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\DisputeRaised;
use App\Services\BookingTransitionService;
use App\Services\ConversationService;
use App\Services\Payments\RefundService;
use Illuminate\Support\Facades\Notification;

it('notes a cancellation in the lesson chat', function () {
    $scenario = classroomScenario(minutesFromNow: 60 * 72);
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']), ['reason' => 'Plans changed.'])
        ->assertRedirect();

    $message = $conversation->messages()->latest('id')->first();

    expect($message->type)->toBe(MessageType::System)
        ->and($message->body)->toContain('Student cancelled this lesson')
        ->and($message->body)->toContain('Plans changed.');

    // A thread never existed for a lesson that was never booked.
    expect($conversation->messages()->count())->toBe(1);
});

it('notes delivery in the lesson chat', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];
    $conversation = app(ConversationService::class)->forBooking($booking);

    $this->travelTo($booking->starts_at->addMinutes(10));
    app(BookingTransitionService::class)->complete($booking);

    $message = $conversation->messages()->latest('id')->first();

    expect($message->isSystem())->toBeTrue()
        ->and($message->body)->toContain('Lesson delivered');
});

it('notes a refund in the lesson chat', function () {
    $scenario = paidBookingScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    app(RefundService::class)->refund($scenario['payment'], 50, Refund::INITIATED_BY_ADMIN, 'Goodwill.');

    $message = $conversation->messages()->latest('id')->first();

    expect($message->isSystem())->toBeTrue()
        ->and($message->body)->toContain('refund of')
        ->and($message->body)->toContain(platform_settings()->formatMinor($scenario['payment']->refresh()->refundedMinor()));
});

it('raises a dispute from the chat report action and alerts admins', function () {
    Notification::fake();

    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($scenario['student'])
        ->post(route('messages.report', $conversation), [
            'reason' => Dispute::REASON_NO_SHOW,
            'details' => 'They never joined the room.',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'report-received');

    $dispute = Dispute::query()->firstOrFail();

    expect($dispute->booking_id)->toBe($scenario['booking']->id)
        ->and($dispute->conversation_id)->toBe($conversation->id)
        ->and($dispute->raised_by)->toBe($scenario['student']->id)
        ->and($dispute->reason)->toBe(Dispute::REASON_NO_SHOW)
        ->and($dispute->details)->toBe('They never joined the room.')
        ->and($dispute->status->value)->toBe('open');

    Notification::assertSentTo($admin, DisputeRaised::class);
});

it('refuses a second open report on the same conversation', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])->post(route('messages.report', $conversation), ['reason' => Dispute::REASON_PAYMENT]);

    $this->actingAs($scenario['student'])
        ->post(route('messages.report', $conversation), ['reason' => Dispute::REASON_BEHAVIOUR])
        ->assertRedirect()
        ->assertSessionHas('status', 'report-already-open');

    expect(Dispute::query()->count())->toBe(1);

    // The thread shows that support already has it.
    $this->actingAs($scenario['student'])
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('Report under review');
});

it('validates the report reason and keeps outsiders out', function () {
    $scenario = classroomScenario();
    $conversation = app(ConversationService::class)->forBooking($scenario['booking']);

    $this->actingAs($scenario['student'])
        ->post(route('messages.report', $conversation), ['reason' => 'not-a-reason'])
        ->assertSessionHasErrors('reason');

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->post(route('messages.report', $conversation), ['reason' => Dispute::REASON_OTHER])
        ->assertForbidden();
});
