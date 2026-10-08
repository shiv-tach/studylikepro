<?php

use App\Enums\EarningStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\LessonSummary;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\SummaryService;

/**
 * A summary service bought and paid for, with the matching pending earning.
 *
 * @return array{student: User, teacherUser: User, teacher: TeacherProfile, purchase: ServicePurchase}
 */
function summaryPurchaseScenario(): array
{
    $offering = ServiceOffering::factory()->forType(ServiceType::Summary)->create();

    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $offering->forceFill(['teacher_profile_id' => $teacher->id])->save();

    $purchase = app(PurchaseService::class)->create($offering, $student);
    $purchase->forceFill([
        'status' => PurchaseStatus::Active,
        'paid_at' => now(),
    ])->save();

    // The ledger still requires a booking_id, so a purchase earning is seated
    // against a stand-in booking row until that constraint is relaxed.
    $booking = Booking::factory()->create(['teacher_profile_id' => $teacher->id]);
    TeacherEarning::query()->create([
        'teacher_profile_id' => $teacher->id,
        'booking_id' => $booking->id,
        'service_purchase_id' => $purchase->id,
        'amount_minor' => $purchase->teacher_payout_minor,
        'currency' => $purchase->currency,
        'status' => EarningStatus::Pending,
    ]);

    return [
        'student' => $student,
        'teacherUser' => $teacherUser,
        'teacher' => $teacher,
        'purchase' => $purchase->fresh(),
    ];
}

it('lets the teacher write a draft summary and update it', function () {
    $scenario = summaryPurchaseScenario();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.summaries.index'))
        ->assertOk()
        ->assertSee($scenario['student']->name)
        ->assertSee('Draft');

    $summary = LessonSummary::query()->firstOrFail();

    expect($summary->isDraft())->toBeTrue()
        ->and($summary->teacher_profile_id)->toBe($scenario['teacher']->id)
        ->and($summary->purchase_id)->toBe($scenario['purchase']->id);

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.summaries.edit', $summary))
        ->assertOk()
        ->assertSee('Save draft');

    $this->actingAs($scenario['teacherUser'])
        ->from(route('teacher.summaries.edit', $summary))
        ->put(route('teacher.summaries.update', $summary), [
            'content' => 'We covered quadratics today; practise the past papers before Saturday.',
        ])
        ->assertRedirect(route('teacher.summaries.edit', $summary))
        ->assertSessionHas('status', 'summary-saved');

    $summary->refresh();

    expect($summary->content)->toBe('We covered quadratics today; practise the past papers before Saturday.')
        ->and($summary->isDraft())->toBeTrue();

    // The student still sees the waiting note while it is a draft.
    $this->actingAs($scenario['student'])
        ->get(route('student.summaries.show', $scenario['purchase']))
        ->assertOk()
        ->assertSee('Awaiting delivery')
        ->assertDontSee('We covered quadratics');
});

it('shows the student the summary once it is delivered', function () {
    $scenario = summaryPurchaseScenario();
    $summary = app(SummaryService::class)->ensureForPurchase($scenario['purchase']);

    app(SummaryService::class)->save($summary, "Here is your personalised summary.\nIt covers the whole lesson.");

    $this->actingAs($scenario['student'])
        ->get(route('student.summaries.show', $scenario['purchase']))
        ->assertOk()
        ->assertSee('Awaiting delivery');

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.summaries.deliver', $summary))
        ->assertRedirect(route('teacher.summaries.index'))
        ->assertSessionHas('status', 'summary-delivered');

    $this->actingAs($scenario['student'])
        ->get(route('student.summaries.show', $scenario['purchase']))
        ->assertOk()
        ->assertSee('Here is your personalised summary.')
        ->assertDontSee('Awaiting delivery');
});

it('delivering completes the purchase and makes the earning eligible', function () {
    $scenario = summaryPurchaseScenario();
    $summary = app(SummaryService::class)->ensureForPurchase($scenario['purchase']);

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.summaries.deliver', $summary))
        ->assertRedirect(route('teacher.summaries.index'))
        ->assertSessionHas('status', 'summary-delivered');

    $summary->refresh();

    expect($summary->isDelivered())->toBeTrue()
        ->and($summary->delivered_at)->not->toBeNull();

    $purchase = $scenario['purchase']->fresh();

    expect($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->completed_at)->not->toBeNull()
        ->and($purchase->earning?->status)->toBe(EarningStatus::Eligible);
});

it('stops another teacher from writing or delivering', function () {
    $scenario = summaryPurchaseScenario();
    $summary = app(SummaryService::class)->ensureForPurchase($scenario['purchase']);

    $otherTeacherUser = User::factory()->teacher()->create();
    TeacherProfile::factory()->approved()->create(['user_id' => $otherTeacherUser->id]);

    $this->actingAs($otherTeacherUser)
        ->get(route('teacher.summaries.edit', $summary))
        ->assertForbidden();

    $this->actingAs($otherTeacherUser)
        ->put(route('teacher.summaries.update', $summary), ['content' => str_repeat('nope ', 10)])
        ->assertForbidden();

    $this->actingAs($otherTeacherUser)
        ->post(route('teacher.summaries.deliver', $summary))
        ->assertForbidden();

    expect($summary->fresh()->isDraft())->toBeTrue();
});

it('keeps a stranger from viewing someone else\'s summary purchase', function () {
    $scenario = summaryPurchaseScenario();

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.summaries.show', $scenario['purchase']))
        ->assertForbidden();
});

it('refuses to deliver a summary whose purchase is no longer active', function () {
    $scenario = summaryPurchaseScenario();
    $summary = app(SummaryService::class)->ensureForPurchase($scenario['purchase']);

    $scenario['purchase']->forceFill(['status' => PurchaseStatus::Cancelled])->save();

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.summaries.deliver', $summary))
        ->assertForbidden();

    expect($summary->fresh()->isDraft())->toBeTrue();
});

it('validates the summary content length', function () {
    $scenario = summaryPurchaseScenario();
    $summary = app(SummaryService::class)->ensureForPurchase($scenario['purchase']);

    $this->actingAs($scenario['teacherUser'])
        ->put(route('teacher.summaries.update', $summary), ['content' => 'Too short.'])
        ->assertSessionHasErrors('content');

    expect($summary->fresh()->content)->toBeNull();
});

it('404s when the purchase is not a summary service', function () {
    $scenario = summaryPurchaseScenario();

    $scenario['purchase']->forceFill(['service_type' => ServiceType::Lesson])->save();

    $this->actingAs($scenario['student'])
        ->get(route('student.summaries.show', $scenario['purchase']))
        ->assertNotFound();
});
