<?php

use App\Enums\EarningStatus;
use App\Enums\MarkingOrderStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\PaperMarkingOrder;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\PaperMarkingService;
use App\Services\PurchaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A paper-marking offering published by an approved teacher.
 */
function markingOffering(): ServiceOffering
{
    $teacher = TeacherProfile::factory()->approved()->create();

    return ServiceOffering::factory()
        ->forType(ServiceType::PaperMarking)
        ->create(['teacher_profile_id' => $teacher->id]);
}

/**
 * A paid, submitted marking order owned by the given student.
 *
 * @return array{order: PaperMarkingOrder, purchase: ServicePurchase, teacher: TeacherProfile, student: User}
 */
function submittedMarkingOrder(?User $student = null, ?TeacherProfile $teacher = null): array
{
    $student ??= User::factory()->student()->onboarded()->create();
    $teacher ??= TeacherProfile::factory()->approved()->create();

    $offering = ServiceOffering::factory()
        ->forType(ServiceType::PaperMarking)
        ->create(['teacher_profile_id' => $teacher->id]);

    $purchase = ServicePurchase::factory()
        ->forOffering($offering)
        ->create(['student_id' => $student->id]);

    $order = PaperMarkingOrder::factory()->forPurchase($purchase)->create();

    return compact('order', 'purchase', 'teacher', 'student');
}

/**
 * Pay a pending-payment purchase the way a captured gateway payment would:
 * activate it and seat its earning. The payments ledger still requires a
 * booking_id, so the earning rides a stand-in booking row until that
 * constraint is relaxed (mirrors tests/Feature/Summaries/SummaryFlowTest.php).
 */
function payForPurchase(ServicePurchase $purchase): void
{
    app(PurchaseService::class)->activate($purchase);

    if ($purchase->fresh()->earning !== null) {
        return;
    }

    $booking = Booking::factory()->create(['teacher_profile_id' => $purchase->teacher_profile_id]);

    TeacherEarning::query()->create([
        'teacher_profile_id' => $purchase->teacher_profile_id,
        'booking_id' => $booking->id,
        'service_purchase_id' => $purchase->id,
        'amount_minor' => $purchase->teacher_payout_minor,
        'currency' => $purchase->currency,
        'status' => EarningStatus::Pending,
    ]);
}

it('creates a pending-payment purchase and a marking order when the student submits a paper', function () {
    Storage::fake('local');

    $offering = markingOffering();
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->post(route('student.marking.store', $offering), [
            'files' => [UploadedFile::fake()->create('paper.pdf', 120, 'application/pdf')],
            'page_count' => 4,
        ])
        ->assertRedirect();

    $purchase = ServicePurchase::query()->where('offering_id', $offering->id)->firstOrFail();

    expect($purchase->status)->toBe(PurchaseStatus::PendingPayment)
        ->and($purchase->student_id)->toBe($student->id);

    $order = $purchase->markingOrder;

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(MarkingOrderStatus::Submitted)
        ->and($order->submitted_at)->not->toBeNull()
        ->and($order->page_count)->toBe(4)
        ->and($order->max_pages)->toBe($offering->max_pages)
        ->and($order->turnaround_hours)->toBe($offering->turnaround_hours)
        ->and($order->files)->toBeArray()->toHaveCount(1)
        ->and($order->files[0]['original_name'])->toBe('paper.pdf')
        ->and($order->files[0]['path'])->toStartWith('marking-orders/'.$order->id);

    Storage::disk('local')->assertExists($order->files[0]['path']);
});

it('hides unpaid orders from the teacher inbox and shows them once paid', function () {
    Storage::fake('local');

    ['order' => $order, 'purchase' => $purchase, 'teacher' => $teacher, 'student' => $student] = submittedMarkingOrder();

    $purchase->forceFill(['status' => PurchaseStatus::PendingPayment, 'paid_at' => null])->save();

    $this->actingAs($teacher->user)
        ->get(route('teacher.marking.index'))
        ->assertOk()
        ->assertDontSee($student->name);

    // The student still sees the pending-payment notice on their own order.
    $this->actingAs($student)
        ->get(route('student.marking-orders.show', $order))
        ->assertOk()
        ->assertSee('Go to payment');

    payForPurchase($purchase->refresh());

    $this->actingAs($teacher->user)
        ->get(route('teacher.marking.index'))
        ->assertOk()
        ->assertSee($student->name)
        ->assertSee($order->status->label());

    $this->actingAs($teacher->user)
        ->get(route('teacher.marking.show', $order))
        ->assertOk()
        ->assertSee('sample.pdf');
});

it('lets the teacher deliver the marking: order returned, purchase completed, earning eligible', function () {
    Storage::fake('local');

    $offering = markingOffering();
    $teacherUser = $offering->teacherProfile->user;
    $student = User::factory()->student()->onboarded()->create();

    $order = app(PaperMarkingService::class)->submit(
        $offering,
        $student,
        [UploadedFile::fake()->create('paper.pdf', 120, 'application/pdf')],
        3,
    );

    $purchase = $order->purchase;

    expect($purchase->status)->toBe(PurchaseStatus::PendingPayment);

    payForPurchase($purchase);

    expect($purchase->fresh()->status)->toBe(PurchaseStatus::Active);

    $this->actingAs($teacherUser)
        ->post(route('teacher.marking.mark', $order), [
            'marked_files' => [UploadedFile::fake()->create('marked.pdf', 200, 'application/pdf')],
            'feedback' => 'Solid work — check the circled steps.',
            'marks' => [['label' => 'Question 1', 'value' => '18/20']],
        ])
        ->assertRedirect(route('teacher.marking.show', $order));

    $order->refresh();
    $purchase->refresh();

    expect($order->status)->toBe(MarkingOrderStatus::Returned)
        ->and($order->returned_at)->not->toBeNull()
        ->and($order->marked_files)->toBeArray()->toHaveCount(1)
        ->and($order->marked_files[0]['path'])->toStartWith('marking-orders/'.$order->id.'/marked')
        ->and($order->feedback)->toBe('Solid work — check the circled steps.')
        ->and($order->marks)->toBe([['label' => 'Question 1', 'value' => '18/20']])
        ->and($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->earning->status)->toBe(EarningStatus::Eligible);
});

it('lets the student accept a returned marking', function () {
    ['order' => $order, 'student' => $student] = submittedMarkingOrder();

    $order->forceFill([
        'status' => MarkingOrderStatus::Returned,
        'returned_at' => now(),
    ])->save();

    $this->actingAs($student)
        ->post(route('student.marking-orders.accept', $order))
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(MarkingOrderStatus::Accepted)
        ->and($order->accepted_at)->not->toBeNull();
});

it('lets the student dispute a returned marking and flags the purchase disputed', function () {
    ['order' => $order, 'purchase' => $purchase, 'student' => $student] = submittedMarkingOrder();

    $order->forceFill([
        'status' => MarkingOrderStatus::Returned,
        'returned_at' => now(),
    ])->save();

    $this->actingAs($student)
        ->post(route('student.marking-orders.dispute', $order), [
            'reason' => 'The marked copy belongs to a different paper entirely.',
        ])
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(MarkingOrderStatus::Disputed)
        ->and($order->disputed_at)->not->toBeNull()
        ->and($order->dispute_reason)->toBe('The marked copy belongs to a different paper entirely.')
        ->and($purchase->fresh()->status)->toBe(PurchaseStatus::Disputed);
});

it('stops a teacher from marking another teacher\'s order', function () {
    Storage::fake('local');

    ['order' => $order] = submittedMarkingOrder();

    $intruder = TeacherProfile::factory()->approved()->create();

    $this->actingAs($intruder->user)
        ->post(route('teacher.marking.mark', $order), [
            'marked_files' => [UploadedFile::fake()->create('marked.pdf', 200, 'application/pdf')],
        ])
        ->assertStatus(403);

    expect($order->fresh()->status)->toBe(MarkingOrderStatus::Submitted);
});

it('stops a student from accepting someone else\'s marking order', function () {
    ['order' => $order] = submittedMarkingOrder();

    $order->forceFill([
        'status' => MarkingOrderStatus::Returned,
        'returned_at' => now(),
    ])->save();

    $stranger = User::factory()->student()->onboarded()->create();

    $this->actingAs($stranger)
        ->post(route('student.marking-orders.accept', $order))
        ->assertStatus(403);

    expect($order->fresh()->status)->toBe(MarkingOrderStatus::Returned);
});

it('renders the submission page for a paper-marking offering', function () {
    $offering = markingOffering();
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get(route('student.marking.create', $offering))
        ->assertOk()
        ->assertSee($offering->subject->name)
        ->assertSee(platform_settings()->formatMinor($offering->price_minor));
});

it('404s the submission page for a non-marking offering', function () {
    $teacher = TeacherProfile::factory()->approved()->create();
    $offering = ServiceOffering::factory()
        ->forType(ServiceType::Summary)
        ->create(['teacher_profile_id' => $teacher->id]);

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.marking.create', $offering))
        ->assertNotFound();
});

it('renders the order page for the student', function () {
    ['order' => $order, 'student' => $student] = submittedMarkingOrder();

    $this->actingAs($student)
        ->get(route('student.marking-orders.show', $order))
        ->assertOk()
        ->assertSee('sample.pdf')
        ->assertSee($order->status->label());
});

it('lets the owner download their uploaded paper', function () {
    Storage::fake('local');

    ['order' => $order, 'student' => $student] = submittedMarkingOrder();

    Storage::disk('local')->put($order->files[0]['path'], 'paper-bytes');

    $this->actingAs($student)
        ->get(route('student.marking-orders.download', [$order, 'files', 0]))
        ->assertOk();

    $stranger = User::factory()->student()->onboarded()->create();

    $this->actingAs($stranger)
        ->get(route('student.marking-orders.download', [$order, 'files', 0]))
        ->assertStatus(403);
});
