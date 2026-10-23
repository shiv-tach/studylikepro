<?php

use App\Enums\BookingStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\PaperMarkingOrder;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\Subject;
use App\Models\User;

/**
 * /teacher/orders and /teacher/orders/{purchase} are rendered by
 * resources/js/teacher-orders and resources/js/teacher-order-show, so Blade only
 * has to hand each app a correct config island (see teacherOrdersConfig() and
 * teacherOrderShowConfig() in tests/Pest.php). These tests pin that contract
 * down: the status rail and its counts, the search, the money, the timeline, the
 * plan balance, the sessions and the marking link.
 */

/**
 * An onboarded teacher, one student and a paid lesson plan between them.
 *
 * @return array<string, mixed>
 */
function teacherOrdersScenario(array $purchaseOverrides = []): array
{
    $teacherUser = User::factory()->teacher()->onboarded()->create();
    $teacher = $teacherUser->teacherProfile;

    $student = User::factory()->student()->onboarded()->create(['name' => 'Nadeesha Perera']);

    $subject = Subject::factory()->create(['name' => 'Order Page Mathematics']);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'grade_id' => Grade::factory()->create()->id,
        'name' => 'Order Page Algebra',
    ]);

    $offering = ServiceOffering::factory()->forType(ServiceType::Lesson)->create([
        'teacher_profile_id' => $teacher->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'title' => 'Monthly maths plan',
        'price_minor' => 120000,
        'allocation_minutes' => 240,
        'default_session_minutes' => 60,
        'max_sessions' => 4,
    ]);

    $purchase = ServicePurchase::factory()->forOffering($offering)->create([
        'student_id' => $student->id,
        'price_minor' => 120000,
        'commission_percent' => 15,
        'platform_fee_minor' => 18000,
        'teacher_payout_minor' => 102000,
        'booking_fee_minor' => 10000,
        'booking_fee_discount_minor' => 0,
        'allocation_minutes' => 240,
        'max_sessions' => 4,
        'status' => PurchaseStatus::Active,
        'paid_at' => now()->subDay(),
        'expires_at' => now()->addDays(60),
        ...$purchaseOverrides,
    ]);

    return compact('teacherUser', 'teacher', 'student', 'subject', 'lesson', 'offering', 'purchase');
}

/**
 * One booked session against a purchase, one hour long.
 */
function teacherOrderSession(array $scenario, BookingStatus $status = BookingStatus::Confirmed): Booking
{
    return Booking::factory()->create([
        'student_id' => $scenario['student']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'service_purchase_id' => $scenario['purchase']->id,
        'subject_id' => $scenario['subject']->id,
        'lesson_id' => $scenario['lesson']->id,
        'status' => $status,
        'starts_at' => now()->addDays(2)->setTime(18, 0),
        'ends_at' => now()->addDays(2)->setTime(19, 0),
    ]);
}

test('the orders page renders the island and its mount point', function () {
    ['teacherUser' => $teacherUser] = teacherOrdersScenario();

    $this->actingAs($teacherUser)
        ->get(route('teacher.orders.index'))
        ->assertOk()
        ->assertSee('id="teacher-orders-config"', false)
        ->assertSee('id="teacher-orders-app"', false)
        ->assertSee('teacher-orders/main.js', false);
});

test('the island carries the status rail, the totals and the order card', function () {
    ['teacherUser' => $teacherUser, 'student' => $student, 'purchase' => $purchase] = teacherOrdersScenario();

    $config = teacherOrdersConfig(
        $this->actingAs($teacherUser)->get(route('teacher.orders.index'))->assertOk()
    );

    $card = $config['purchases'][0];
    $activeTab = collect($config['statusTabs'])->firstWhere('key', PurchaseStatus::Active->value);
    $allTab = collect($config['statusTabs'])->firstWhere('active', true);

    expect($config['stats']['total'])->toBe(1)
        ->and($config['stats']['active'])->toBe(1)
        ->and($config['stats']['earnedLabel'])->toContain('1,020')
        ->and($activeTab['count'])->toBe(1)
        ->and($allTab['key'])->toBe('')
        ->and($card['studentName'])->toBe($student->name)
        ->and($card['url'])->toBe(route('teacher.orders.show', $purchase))
        ->and($card['statusLabel'])->toBe('Active')
        ->and($card['payoutLabel'])->toContain('1,020')
        ->and($card['scopeTitle'])->toBe('Order Page Algebra')
        ->and($config['copy']['toolbar']['viewLabel'])->toBe('Order layout')
        ->and($config['copy']['toolbar']['viewList'])->toBe('List view')
        ->and($config['copy']['toolbar']['viewCard'])->toBe('Card view')
        ->and($config['urls']['services'])->toBe(route('teacher.services.index'));
});

test('the status filter narrows the list and keeps the search term in every tab', function () {
    ['teacherUser' => $teacherUser] = teacherOrdersScenario();

    $config = teacherOrdersConfig(
        $this->actingAs($teacherUser)->get(route('teacher.orders.index', [
            'status' => PurchaseStatus::Active->value,
            'search' => 'Nadeesha',
        ]))->assertOk()
    );

    $allTab = collect($config['statusTabs'])->firstWhere('key', '');

    expect($config['purchases'])->toHaveCount(1)
        ->and($config['filtered'])->toBeTrue()
        ->and(collect($config['statusTabs'])->firstWhere('active', true)['key'])->toBe(PurchaseStatus::Active->value)
        ->and($allTab['url'])->toContain('search=Nadeesha')
        ->and($allTab['url'])->not->toContain('status=active');
});

test('the search matches the student, the service and the order number', function () {
    ['teacherUser' => $teacherUser, 'purchase' => $purchase] = teacherOrdersScenario();

    $byStudent = teacherOrdersConfig(
        $this->actingAs($teacherUser)->get(route('teacher.orders.index', ['search' => 'Nadeesha']))->assertOk()
    );

    $byService = teacherOrdersConfig(
        $this->actingAs($teacherUser)->get(route('teacher.orders.index', ['search' => 'Monthly maths']))->assertOk()
    );

    $byReference = teacherOrdersConfig(
        $this->actingAs($teacherUser)->get(route('teacher.orders.index', ['search' => (string) $purchase->id]))->assertOk()
    );

    $byNothing = teacherOrdersConfig(
        $this->actingAs($teacherUser)->get(route('teacher.orders.index', ['search' => 'no-such-order']))->assertOk()
    );

    expect($byStudent['purchases'])->toHaveCount(1)
        ->and($byService['purchases'])->toHaveCount(1)
        ->and($byReference['purchases'])->toHaveCount(1)
        ->and($byNothing['purchases'])->toBe([]);
});

test('an empty book points at the services console', function () {
    $teacherUser = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($teacherUser)
        ->get(route('teacher.orders.index'))
        ->assertOk()
        ->assertSee('No orders yet');
});

test('a teacher cannot open another teacher order', function () {
    ['purchase' => $purchase] = teacherOrdersScenario();

    $other = User::factory()->teacher()->onboarded()->create();

    $this->actingAs($other)
        ->get(route('teacher.orders.show', $purchase))
        ->assertForbidden();
});

test('the order island carries the money, the timeline and the sessions', function () {
    $scenario = teacherOrdersScenario();
    $session = teacherOrderSession($scenario);

    $config = teacherOrderShowConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.orders.show', $scenario['purchase']))
            ->assertOk()
    );

    $timelineKeys = collect($config['timeline'])->pluck('key')->all();
    $labels = collect($config['money']['rows'])->pluck('label')->all();

    expect($config['order']['studentName'])->toBe('Nadeesha Perera')
        ->and($config['order']['statusLabel'])->toBe('Active')
        ->and($config['money']['payout']['value'])->toContain('1,020')
        ->and($labels)->toContain('Service price')
        ->and($labels)->toContain('Student paid')
        ->and($config['timeline'])->toHaveCount(3)
        ->and($timelineKeys)->toContain('ordered')
        ->and($timelineKeys)->toContain('paid')
        ->and($config['sessions'])->toHaveCount(1)
        ->and($config['sessions'][0]['url'])->toBe(route('teacher.bookings.show', $session))
        // A confirmed session reads blue with a calendar glyph, so it can never
        // be mistaken for one that has already been completed.
        ->and($config['sessions'][0]['status'])->toBe(['label' => 'Confirmed', 'tone' => 'sky', 'icon' => 'calendar'])
        ->and($config['sessions'][0]['joinable'])->toBeFalse()
        ->and($config['urls']['index'])->toBe(route('teacher.orders.index'));
});

test('a confirmed session never reads like a completed one', function () {
    $scenario = teacherOrdersScenario();
    teacherOrderSession($scenario, BookingStatus::Confirmed);
    teacherOrderSession($scenario, BookingStatus::Completed);
    teacherOrderSession($scenario, BookingStatus::Cancelled);

    $config = teacherOrderShowConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.orders.show', $scenario['purchase']))
            ->assertOk()
    );

    $chips = collect($config['sessions'])
        ->mapWithKeys(fn (array $session) => [$session['status']['label'] => $session['status']])
        ->all();

    expect($chips['Confirmed'])->toBe(['label' => 'Confirmed', 'tone' => 'sky', 'icon' => 'calendar'])
        ->and($chips['Completed'])->toBe(['label' => 'Completed', 'tone' => 'emerald', 'icon' => 'check-circle'])
        ->and($chips['Cancelled'])->toBe(['label' => 'Cancelled', 'tone' => 'slate', 'icon' => 'close']);
});

test('the order island carries the plan balance of a lesson plan', function () {
    $scenario = teacherOrdersScenario();
    teacherOrderSession($scenario);

    $config = teacherOrderShowConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.orders.show', $scenario['purchase']))
            ->assertOk()
    );

    expect($config['plan']['remainingValue'])->toBe('180 min')
        ->and($config['plan']['progress']['percent'])->toBe(25)
        ->and($config['plan']['sessionsLabel'])->toBe('1 of 4 sessions booked')
        ->and($config['statusNote'])->toContain('180');
});

test('a cancelled order carries the reason on its timeline', function () {
    $scenario = teacherOrdersScenario([
        'status' => PurchaseStatus::Cancelled,
        'cancelled_at' => now()->subHour(),
        'cancelled_by' => ServicePurchase::CANCELLED_BY_STUDENT,
        'cancellation_reason' => 'Booked the wrong lesson',
    ]);

    $config = teacherOrderShowConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.orders.show', $scenario['purchase']))
            ->assertOk()
    );

    $cancelled = collect($config['timeline'])->firstWhere('key', 'cancelled');

    expect($cancelled)->not->toBeNull()
        ->and($cancelled['note'])->toContain('Booked the wrong lesson')
        ->and($config['plan'])->toBeNull();
});

test('a paper marking order links its marking console', function () {
    ['teacherUser' => $teacherUser, 'teacher' => $teacher] = teacherOrdersScenario();

    $offering = ServiceOffering::factory()->forType(ServiceType::PaperMarking)->create([
        'teacher_profile_id' => $teacher->id,
        'title' => 'Mark my past paper',
    ]);

    $purchase = ServicePurchase::factory()->forOffering($offering)->create([
        'status' => PurchaseStatus::Active,
        'paid_at' => now()->subDay(),
    ]);

    $order = PaperMarkingOrder::factory()->forPurchase($purchase)->create();

    $config = teacherOrderShowConfig(
        $this->actingAs($teacherUser)
            ->get(route('teacher.orders.show', $purchase))
            ->assertOk()
    );

    expect($config['marking'])->not->toBeNull()
        ->and($config['marking']['url'])->toBe(route('teacher.marking.show', $order))
        ->and($config['plan'])->toBeNull();
});
