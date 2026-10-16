<?php

use App\Enums\BookingStatus;
use App\Enums\MarkingOrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonSummary;
use App\Models\PaperMarkingOrder;
use App\Models\Payment;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;

/**
 * One purchase in detail is rendered by resources/js/student-purchase-show, so
 * the Blade view only has to hand the Vue app a correct config island (see
 * `purchaseShowConfig()` in tests/Pest.php). These tests pin that contract down:
 * the status panel and its actions, the plan balance, the delivery card and the
 * sessions booked against the purchase.
 */
/**
 * An onboarded student who bought one service from an approved teacher.
 *
 * @return array<string, mixed>
 */
function purchaseDetailScenario(
    string $type = ServiceType::Lesson->value,
    array $offeringOverrides = [],
    array $purchaseOverrides = [],
): array {
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $subject = Subject::factory()->create(['name' => 'Mathematics']);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'grade_id' => Grade::factory()->create()->id,
        'name' => 'Quadratic factorising',
    ]);

    $offering = ServiceOffering::factory()->forType(ServiceType::from($type))->create([
        'teacher_profile_id' => $teacher->id,
        'subject_id' => $subject->id,
        'lesson_id' => in_array($type, [ServiceType::Lesson->value, ServiceType::Summary->value], true)
            ? $lesson->id
            : null,
        'price_minor' => 120000,
        ...$offeringOverrides,
    ]);

    $purchase = ServicePurchase::factory()->forOffering($offering)->create([
        'student_id' => $student->id,
        'price_minor' => 120000,
        'booking_fee_minor' => 0,
        ...$purchaseOverrides,
    ]);

    return compact('student', 'teacher', 'teacherUser', 'subject', 'lesson', 'offering', 'purchase');
}

/**
 * A session booked against a purchase, one hour long.
 */
function purchaseDetailSession(array $scenario, string $startsAt, BookingStatus $status = BookingStatus::Confirmed): Booking
{
    [$dayOffset, $hour] = explode('@', $startsAt);

    return Booking::factory()->create([
        'student_id' => $scenario['student']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'service_purchase_id' => $scenario['purchase']->id,
        'subject_id' => $scenario['subject']->id,
        'lesson_id' => $scenario['lesson']->id,
        'starts_at' => now()->addDays((int) $dayOffset)->setTime((int) $hour, 0),
        'ends_at' => now()->addDays((int) $dayOffset)->setTime((int) $hour + 1, 0),
        'status' => $status,
    ]);
}

/**
 * The display timezone every date on the page is formatted in.
 */
function purchaseDetailTimezone(): string
{
    return config('studylikepro.default_display_timezone');
}

test('the purchase island carries the status, the money and the route map', function () {
    $scenario = purchaseDetailScenario();

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['theme']['preset'])->toBe('classic');
    expect($config['csrfToken'])->toBe(csrf_token());
    expect($config['urls']['index'])->toBe(route('student.purchases.index'));
    expect($config['flash'])->toBeNull();
    expect($config['error'])->toBeNull();

    expect($config['status']['value'])->toBe(PurchaseStatus::Active->value);
    expect($config['status']['label'])->toBe(PurchaseStatus::Active->label());
    expect($config['status']['tone'])->toBe('primary');

    expect($config['status']['price'])->toBe([
        'label' => 'Total price',
        'value' => platform_settings()->formatMinor(120000),
        // No booking fee on this purchase, so the breakdown line is dropped.
        'fee' => null,
    ]);

    // An active purchase counts its window down in days, not minutes.
    expect($config['status']['expiry']['label'])->toBe('Valid until');
    expect($config['status']['expiry']['value'])->toBe(
        $scenario['purchase']->expires_at->copy()->setTimezone(purchaseDetailTimezone())->format('d M Y, H:i')
    );

    // Nothing to pay for and nothing paid yet.
    expect($config['status']['actions'])->toBe([]);
    expect($config['status']['payment'])->toBeNull();
    expect($config['status']['hold'])->toBeNull();
    expect($config['status']['expired'])->toBe(['value' => 'expired', 'label' => 'Expired']);
    expect($config['copy']['statusLabel'])->toBe('Status');
    expect($config['copy']['payWithin'])->toBe('Pay within :time');
});

test('the student\'s own theme preset reaches the island', function () {
    $scenario = purchaseDetailScenario();

    $scenario['student']->forceFill([
        'theme' => ['preset' => 'ocean', 'accent' => 'cyan', 'mode' => 'dark', 'sidebarStyle' => 'flat'],
    ])->save();

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['theme']['preset'])->toBe('ocean');
});

test('a live hold offers to pay for the purchase before its window closes', function () {
    $scenario = purchaseDetailScenario(purchaseOverrides: [
        'status' => PurchaseStatus::PendingPayment,
        'paid_at' => null,
        'booking_fee_minor' => 2500,
    ]);

    $purchase = $scenario['purchase'];

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])->get(route('student.purchases.show', $purchase))->assertOk()
    );

    expect($config['status']['value'])->toBe(PurchaseStatus::PendingPayment->value);
    expect($config['status']['tone'])->toBe('amber');
    expect($config['status']['expiry']['label'])->toBe('Payment hold until');
    expect($config['status']['price']['fee'])->toBe(
        'includes '.platform_settings()->formatMinor(2500).' platform fee'
    );

    // Both actions are real POSTs, and the pay button carries what it charges.
    expect(collect($config['status']['actions'])->pluck('url')->all())->toBe([
        route('student.purchases.checkout', $purchase),
        route('student.purchases.cancel', $purchase),
    ]);
    expect($config['status']['actions'][0]['label'])->toBe('Pay now');
    expect($config['status']['actions'][0]['amount'])->toBe(platform_settings()->formatMinor(122500));
    expect($config['status']['actions'][1]['label'])->toBe('Cancel purchase');

    // The deadline is an instant, so the seconds tick without a request and the
    // panel knows what to become if the countdown runs out.
    expect($config['status']['hold']['deadline'])->toBe($purchase->expires_at->toIso8601String());

    // A hold buys nothing yet, so no plan balance is offered.
    expect($config['plan'])->toBeNull();
});

test('a hold whose time ran out reads as expired and drops the payment', function () {
    $scenario = purchaseDetailScenario(purchaseOverrides: [
        'status' => PurchaseStatus::PendingPayment,
        'paid_at' => null,
        'expires_at' => now()->subMinute(),
    ]);

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    // Paying is already impossible, so the page must not offer it.
    expect($config['status']['value'])->toBe(PurchaseStatus::Expired->value);
    expect($config['status']['label'])->toBe(PurchaseStatus::Expired->label());
    expect($config['status']['tone'])->toBe('slate');
    expect($config['status']['actions'])->toBe([]);
    expect($config['status']['hold'])->toBeNull();
    expect($config['status']['expiry']['label'])->toBe('Valid until');
});

test('an active plan carries its balance, its progress and the way to book', function () {
    $scenario = purchaseDetailScenario();

    purchaseDetailSession($scenario, '1@18');
    // A cancelled session returns its minutes, so it counts against nothing.
    purchaseDetailSession($scenario, '2@18', BookingStatus::Cancelled);

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['plan']['title'])->toBe('Your plan');
    expect($config['plan']['intro'])->toBe('240 minutes split into sessions the way you like.');
    expect($config['plan']['remainingTitle'])->toBe('Time left');
    expect($config['plan']['remainingValue'])->toBe('180 min');
    expect($config['plan']['remainingTone'])->toBe('primary');
    expect($config['plan']['progress'])->toBe([
        'percent' => 25,
        'label' => '60 of 240 min used',
    ]);
    expect($config['plan']['exhausted'])->toBeFalse();
    expect($config['plan']['bookUrl'])->toBe(route('student.purchase-sessions.create', $scenario['purchase']));

    // Both sessions are listed, each with the tone of its own status.
    $items = collect($config['sessions']['items']);

    expect($config['sessions']['title'])->toBe('Sessions');
    expect($items->pluck('status.tone')->all())->toBe(['emerald', 'slate']);
    expect($items->pluck('status.label')->all())->toBe(['Confirmed', 'Cancelled']);
    expect($items->pluck('durationLabel')->all())->toBe(['60 min', '60 min']);
    expect($items->pluck('scope')->all())->toBe([
        'Mathematics · Quadratic factorising',
        'Mathematics · Quadratic factorising',
    ]);
    expect($items->first()['url'])->toBe(route('student.bookings.show', Booking::query()->orderBy('starts_at')->first()));
    expect($items->first()['when'])->toBe(
        Booking::query()->orderBy('starts_at')->first()->starts_at->copy()
            ->setTimezone(purchaseDetailTimezone())->format('D, d M Y · H:i')
    );
});

test('a plan that used up its session cap asks for no more sessions', function () {
    $scenario = purchaseDetailScenario(offeringOverrides: ['max_sessions' => 1]);

    purchaseDetailSession($scenario, '1@18');

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['plan']['exhausted'])->toBeTrue();
    expect($config['plan']['bookUrl'])->toBeNull();
    expect($config['plan']['limitLabel'])->toBe('This plan has reached its session limit.');
});

test('a plan with no time left is done and keeps no booking link', function () {
    $scenario = purchaseDetailScenario(purchaseOverrides: ['allocation_minutes' => 60]);

    purchaseDetailSession($scenario, '1@18');

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['plan']['remainingValue'])->toBe('Done');
    expect($config['plan']['remainingTone'])->toBe('emerald');
    expect($config['plan']['progress']['percent'])->toBe(100);
    expect($config['plan']['bookUrl'])->toBeNull();
});

test('a summary purchase links to its delivery screen and reports whether it is ready', function () {
    $scenario = purchaseDetailScenario(type: ServiceType::Summary->value);

    $student = $scenario['student'];
    $purchase = $scenario['purchase'];

    $writing = purchaseShowConfig(
        $this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk()
    );

    expect($writing['delivery']['icon'])->toBe('book');
    expect($writing['delivery']['title'])->toBe('Lesson summary');
    expect($writing['delivery']['url'])->toBe(route('student.summaries.show', $purchase));
    expect($writing['delivery']['badge'])->toBe(['label' => 'In progress', 'tone' => 'amber']);
    expect($writing['delivery']['body'])->toBe('Your teacher is writing it — you will be notified when it is ready.');

    LessonSummary::factory()->create([
        'purchase_id' => $purchase->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'subject_id' => $scenario['subject']->id,
        'lesson_id' => $scenario['lesson']->id,
        'status' => LessonSummary::STATUS_DELIVERED,
    ]);

    $delivered = purchaseShowConfig(
        $this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk()
    );

    expect($delivered['delivery']['badge'])->toBe(['label' => 'Ready', 'tone' => 'emerald']);
    expect($delivered['delivery']['body'])->toBe('Delivered — tap to read.');
});

test('a marking purchase reports the state of its order', function () {
    $scenario = purchaseDetailScenario(
        type: ServiceType::PaperMarking->value,
        offeringOverrides: ['turnaround_hours' => 48],
    );

    $student = $scenario['student'];
    $purchase = $scenario['purchase'];

    $order = PaperMarkingOrder::factory()->forPurchase($purchase)->create();

    $beingMarked = purchaseShowConfig(
        $this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk()
    );

    expect($beingMarked['delivery']['icon'])->toBe('clipboard');
    expect($beingMarked['delivery']['title'])->toBe('Paper marking');
    expect($beingMarked['delivery']['url'])->toBe(route('student.marking-orders.show', $order));
    expect($beingMarked['delivery']['badge'])->toBe(['label' => 'Submitted', 'tone' => 'amber']);
    expect($beingMarked['delivery']['body'])->toBe('Being marked — usually back within 48 hours.');

    // Past the deadline the card stops promising a turnaround time.
    $order->update(['status' => MarkingOrderStatus::InProgress, 'deadline_at' => now()->subDay()]);

    $overdue = purchaseShowConfig(
        $this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk()
    );

    expect($overdue['delivery']['badge'])->toBe(['label' => 'Being marked', 'tone' => 'primary']);
    expect($overdue['delivery']['body'])->toBe('Past the turnaround deadline — contact support if nothing arrives soon.');

    // A returned paper is the one state the student has to act on.
    $order->update(['status' => MarkingOrderStatus::Returned, 'returned_at' => now()]);

    $returned = purchaseShowConfig(
        $this->actingAs($student)->get(route('student.purchases.show', $purchase))->assertOk()
    );

    expect($returned['delivery']['badge'])->toBe(['label' => 'Returned', 'tone' => 'sky']);
    expect($returned['delivery']['body'])->toBe('Your marked paper is ready — open it to accept or dispute.');
});

test('the page announces what a redirect sent it back with', function () {
    $scenario = purchaseDetailScenario();

    $student = $scenario['student'];
    $url = route('student.purchases.show', $scenario['purchase']);

    $booked = purchaseShowConfig(
        $this->actingAs($student)->withSession(['status' => 'session-booked'])->get($url)->assertOk()
    );

    expect($booked['flash'])->toBe([
        'tone' => 'emerald',
        'icon' => 'calendar',
        'message' => 'Session booked — it is already paid for from your plan.',
    ]);

    // A status this page has no banner for leaves it clean rather than guessing.
    $unknown = purchaseShowConfig(
        $this->actingAs($student)->withSession(['status' => 'hold-created'])->get($url)->assertOk()
    );

    expect($unknown['flash'])->toBeNull();
});

test('a purchase that paid through checkout reports when and how', function () {
    $scenario = purchaseDetailScenario();

    $payment = Payment::factory()->create([
        'booking_id' => null,
        'service_purchase_id' => $scenario['purchase']->id,
        'student_id' => $scenario['student']->id,
        'amount_minor' => 120000,
        'currency' => 'LKR',
        'status' => PaymentStatus::Captured,
        'gateway' => 'demo',
        'captured_at' => now()->subMinutes(5),
    ]);

    $config = purchaseShowConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['status']['payment']['label'])->toBe('Paid '.$payment->captured_at->copy()
        ->setTimezone(purchaseDetailTimezone())->format('d M Y, H:i').' via demo');
});
