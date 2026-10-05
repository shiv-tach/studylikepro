<?php

use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\DisputeResolved;
use App\Services\DisputeService;

/**
 * A paid lesson with an open dispute raised by the student about the teacher.
 *
 * @return array{student: User, teacherUser: User, booking: Booking, payment: Payment, dispute: Dispute}
 */
function disputeScenario(): array
{
    $scenario = paidBookingScenario();

    $dispute = app(DisputeService::class)->raise(
        $scenario['student'],
        Dispute::REASON_NO_SHOW,
        'The lesson never happened.',
        booking: $scenario['booking'],
    );

    return [...$scenario, 'dispute' => $dispute];
}

it('lists disputes with their parties and reasons', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->get(route('admin.disputes.index'))
        ->assertOk()
        ->assertSee($scenario['student']->name)
        ->assertSee($scenario['teacherUser']->name)
        ->assertSee('The other side did not show up');
});

it('shows the dispute with the lesson, money and chat', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->get(route('admin.disputes.show', $scenario['dispute']))
        ->assertOk()
        ->assertSee('The lesson never happened.')
        ->assertSee('The report')
        ->assertSee('Close the case');
});

it('marks a dispute as picked up for review', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.review', $scenario['dispute']))
        ->assertRedirect();

    expect($scenario['dispute']->refresh()->status)->toBe(DisputeStatus::Reviewed)
        ->and($scenario['dispute']->resolved_by)->toBe($admin->id)
        ->and($scenario['student']->notifications()->count())->toBe(0);
});

it('refunds in full and closes the case', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), [
            'resolution' => Dispute::RESOLUTION_REFUND_FULL,
            'notes' => 'Lesson was missed; student refunded.',
        ])
        ->assertRedirect(route('admin.disputes.show', $scenario['dispute']));

    $dispute = $scenario['dispute']->refresh();
    $refund = Refund::query()->findOrFail($dispute->refund_id);

    expect($dispute->status)->toBe(DisputeStatus::Resolved)
        ->and($dispute->resolution)->toBe(Dispute::RESOLUTION_REFUND_FULL)
        ->and($refund->amount_minor)->toBe($scenario['payment']->amount_minor)
        ->and($refund->percent)->toBe(100)
        ->and($refund->status)->toBe(RefundStatus::Processed)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($scenario['teacherUser']->notifications()->pluck('type'))
        ->toContain(DisputeResolved::class)
        ->and($scenario['student']->notifications()->pluck('type'))
        ->toContain(DisputeResolved::class);

    expect(ActivityLog::query()->first()->description)->toContain('Resolved dispute');
});

it('refunds a percentage when the case is only partly the teacher\'s fault', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), [
            'resolution' => Dispute::RESOLUTION_REFUND_PARTIAL,
            'percent' => 50,
        ])
        ->assertRedirect();

    $dispute = $scenario['dispute']->refresh();
    $refund = Refund::query()->findOrFail($dispute->refund_id);

    expect($refund->percent)->toBe(50)
        ->and($refund->amount_minor)->toBe((int) round($scenario['payment']->amount_minor / 2))
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded);
});

it('dismisses a report without moving money', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), [
            'resolution' => Dispute::RESOLUTION_DISMISSED,
            'notes' => 'Lesson recording shows it was delivered.',
        ])
        ->assertRedirect();

    expect($scenario['dispute']->refresh()->status)->toBe(DisputeStatus::Dismissed)
        ->and($scenario['dispute']->refund_id)->toBeNull()
        ->and(Refund::query()->count())->toBe(0)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Captured);
});

it('warns the reported party', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), [
            'resolution' => Dispute::RESOLUTION_WARNED,
            'notes' => 'First warning for poor communication.',
        ])
        ->assertRedirect();

    expect($scenario['dispute']->refresh()->status)->toBe(DisputeStatus::Resolved)
        ->and($scenario['dispute']->resolution)->toBe(Dispute::RESOLUTION_WARNED)
        ->and($scenario['teacherUser']->refresh()->isSuspended())->toBeFalse();
});

it('suspends the reported account when the case warrants it', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), [
            'resolution' => Dispute::RESOLUTION_SUSPENDED,
            'notes' => 'Repeated no-shows.',
        ])
        ->assertRedirect();

    expect($scenario['teacherUser']->refresh()->isSuspended())->toBeTrue()
        ->and($scenario['teacherUser']->suspension_reason)->toContain('Dispute #'.$scenario['dispute']->id);
});

it('refuses a second resolution on a closed dispute', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)->post(route('admin.disputes.resolve', $scenario['dispute']), [
        'resolution' => Dispute::RESOLUTION_DISMISSED,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), [
            'resolution' => Dispute::RESOLUTION_REFUND_FULL,
        ])
        ->assertNotFound();

    expect(Refund::query()->count())->toBe(0);
});

it('validates the resolution choice', function () {
    $admin = User::factory()->admin()->create();
    $scenario = disputeScenario();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $scenario['dispute']), ['resolution' => 'nonsense'])
        ->assertSessionHasErrors('resolution');

    expect($scenario['dispute']->refresh()->status)->toBe(DisputeStatus::Open);
});

it('filters disputes by status and reason', function () {
    $admin = User::factory()->admin()->create();
    $open = disputeScenario();

    $resolvedScenario = paidBookingScenario();
    $resolved = Dispute::factory()->create([
        'booking_id' => $resolvedScenario['booking']->id,
        'raised_by' => $resolvedScenario['student']->id,
        'against_id' => $resolvedScenario['teacherUser']->id,
        'reason' => Dispute::REASON_PAYMENT,
        'status' => DisputeStatus::Resolved,
        'resolution' => Dispute::RESOLUTION_DISMISSED,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.disputes.index', ['status' => DisputeStatus::Resolved->value]))
        ->assertOk()
        ->assertSee(route('admin.disputes.show', $resolved))
        ->assertDontSee(route('admin.disputes.show', $open['dispute']));

    $this->actingAs($admin)
        ->get(route('admin.disputes.index', ['reason' => Dispute::REASON_NO_SHOW]))
        ->assertOk()
        ->assertSee(route('admin.disputes.show', $open['dispute']))
        ->assertDontSee(route('admin.disputes.show', $resolved));

    expect($resolved->refresh()->status)->toBe(DisputeStatus::Resolved);
});
