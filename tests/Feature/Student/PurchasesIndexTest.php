<?php

use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;

/**
 * The purchase wallet is rendered by resources/js/student-purchases, so the
 * Blade view only has to hand the Vue app a correct config island (see
 * `purchasesConfig()` in tests/Pest.php). These tests pin that contract down:
 * the tabs, the service-type chips, the summary figures, the cards and the one
 * action each card offers.
 */
/**
 * An onboarded student who bought one service from an approved teacher.
 *
 * @return array<string, mixed>
 */
function walletScenario(
    array $offeringOverrides = [],
    array $purchaseOverrides = [],
    string $type = ServiceType::Lesson->value,
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
        ...$purchaseOverrides,
    ]);

    return compact('student', 'teacher', 'teacherUser', 'subject', 'lesson', 'offering', 'purchase');
}

test('the purchase island carries the tabs, the type chips and the route map', function () {
    $scenario = walletScenario();

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])->get(route('student.purchases.index'))->assertOk()
    );

    expect($config['tab'])->toBe('active');
    expect($config['selectedType'])->toBe('');
    expect($config['filtered'])->toBeFalse();
    expect($config['theme']['preset'])->toBe('classic');
    expect($config['csrfToken'])->toBe(csrf_token());
    expect($config['urls']['index'])->toBe(route('student.purchases.index'));
    expect($config['urls']['teachers'])->toBe(route('student.teachers.index'));
    expect($config['urls']['reset'])->toBe(route('student.purchases.index', ['tab' => 'active']));

    // Both tabs count the student's whole history, not just this page.
    expect(collect($config['tabs'])->pluck('label', 'key')->all())->toBe([
        'active' => 'Active (1)',
        'past' => 'Past (0)',
    ]);

    // "All services" plus every service type, so an empty result can say that
    // the student owns nothing of that kind yet.
    expect(collect($config['typeOptions'])->pluck('label')->all())->toBe([
        'All services',
        'Lesson plans',
        'Lesson summaries',
        'Paper marking',
        'Paper discussions',
        'Q&A sessions',
    ]);
    expect($config['typeOptions'][0]['url'])->toBe(route('student.purchases.index', ['tab' => 'active']));
    expect($config['typeOptions'][1]['url'])->toBe(route('student.purchases.index', ['tab' => 'active', 'type' => 'lesson']));
});

test('the student\'s own theme preset reaches the island', function () {
    $student = User::factory()->student()->onboarded()->create([
        'theme' => ['preset' => 'ocean', 'accent' => 'cyan', 'mode' => 'dark', 'sidebarStyle' => 'flat'],
    ]);

    $config = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index'))->assertOk()
    );

    expect($config['theme']['preset'])->toBe('ocean');
});

test('a purchase card carries its type, price, balance and next action', function () {
    $scenario = walletScenario();

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])->get(route('student.purchases.index'))->assertOk()
    );

    $card = collect($config['purchases'])->firstWhere('id', $scenario['purchase']->id);

    expect($card['url'])->toBe(route('student.purchases.show', $scenario['purchase']));
    expect($card['typeIcon'])->toBe('graduation');
    expect($card['typeLabel'])->toBe('Lesson plan');
    expect($card['status'])->toBe(PurchaseStatus::Active->value);
    expect($card['statusLabel'])->toBe(PurchaseStatus::Active->label());
    expect($card['title'])->toBe('Quadratic factorising');
    expect($card['teacherLabel'])->toBe('with '.$scenario['teacherUser']->name);
    expect($card['scope'])->toBe('Mathematics · Quadratic factorising');
    expect($card['price'])->toBe(platform_settings()->formatMinor(120000));
    expect($card['purchasedLabel'])->toStartWith('Bought ');
    expect($card['balance'])->toBe(['label' => '240 min left', 'tone' => 'primary']);
    expect($card['progress'])->toBe([
        'percent' => 0,
        'exhausted' => false,
        'label' => '0 of 240 min used',
    ]);
    expect($card['delivery'])->toBeNull();
    expect($card['expiresLabel'])->toStartWith('Usable until ');

    // An active plan is the one service the student can spend again.
    expect($card['action'])->toBe([
        'kind' => 'link',
        'url' => route('student.purchase-sessions.create', $scenario['purchase']),
        'label' => 'Book a session',
        'variant' => 'primary',
    ]);
});

test('a purchase awaiting payment offers to pay, and counts towards the pending tile', function () {
    $scenario = walletScenario(purchaseOverrides: [
        'status' => PurchaseStatus::PendingPayment,
        'paid_at' => null,
    ]);

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])->get(route('student.purchases.index'))->assertOk()
    );

    $card = collect($config['purchases'])->firstWhere('id', $scenario['purchase']->id);

    expect($card['action']['kind'])->toBe('form');
    expect($card['action']['url'])->toBe(route('student.purchases.checkout', $scenario['purchase']));
    expect($card['action']['label'])->toBe('Pay now');

    // The hold is counted down from an instant, so the seconds never come from
    // the server, and the card knows what to become if it runs out.
    expect($card['payBy']['deadline'])->toBe($scenario['purchase']->expires_at->toIso8601String());
    expect($card['expiredStatus'])->toBe(['value' => 'expired', 'label' => 'Expired']);
    expect($card['expiresLabel'])->toBeNull();

    expect(collect($config['stats'])->pluck('value', 'key')->all())->toBe([
        'pending' => 1,
        'ready' => 0,
        'past' => 0,
    ]);
});

test('a hold whose time ran out leaves the active tab and reads as expired', function () {
    $scenario = walletScenario(purchaseOverrides: [
        'status' => PurchaseStatus::PendingPayment,
        'paid_at' => null,
        'expires_at' => now()->subMinute(),
    ]);

    $student = $scenario['student'];

    $active = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index'))->assertOk()
    );

    // Nothing to pay for, so nothing sits in the list the student acts on.
    expect($active['purchases'])->toBeEmpty();
    expect(collect($active['stats'])->pluck('value', 'key')->all())->toBe([
        'pending' => 0,
        'ready' => 0,
        'past' => 1,
    ]);

    $past = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index', ['tab' => 'past']))->assertOk()
    );

    $card = collect($past['purchases'])->firstWhere('id', $scenario['purchase']->id);

    expect($card['status'])->toBe(PurchaseStatus::Expired->value);
    expect($card['statusLabel'])->toBe(PurchaseStatus::Expired->label());
    expect($card['action']['label'])->toBe('View details');
    expect($card['payBy'])->toBeNull();
    expect($card['expiredStatus'])->toBeNull();
    expect($card['balance'])->toBeNull();
});

test('a paid summary reports delivery, an unpaid one does not', function () {
    $scenario = walletScenario(
        purchaseOverrides: [
            'status' => PurchaseStatus::PendingPayment,
            'paid_at' => null,
        ],
        type: ServiceType::Summary->value,
    );

    $student = $scenario['student'];

    $unpaid = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index'))->assertOk()
    );

    expect(collect($unpaid['purchases'])->firstWhere('id', $scenario['purchase']->id)['delivery'])->toBeNull();

    // Paying for it is what puts the teacher to work, so only then does the
    // card say anything about the summary being written.
    $scenario['purchase']->update(['status' => PurchaseStatus::Active, 'paid_at' => now()]);

    $paid = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index'))->assertOk()
    );

    expect(collect($paid['purchases'])->firstWhere('id', $scenario['purchase']->id)['delivery'])
        ->toBe(['label' => 'Summary being written', 'tone' => 'amber']);
});

test('the summary tiles split the wallet into payable, usable and finished', function () {
    $scenario = walletScenario();

    foreach ([PurchaseStatus::PendingPayment, PurchaseStatus::Completed] as $status) {
        ServicePurchase::factory()->forOffering($scenario['offering'])->create([
            'student_id' => $scenario['student']->id,
            'status' => $status,
            'paid_at' => $status === PurchaseStatus::Completed ? now() : null,
            'completed_at' => $status === PurchaseStatus::Completed ? now() : null,
        ]);
    }

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])->get(route('student.purchases.index'))->assertOk()
    );

    expect(collect($config['stats'])->pluck('value', 'key')->all())->toBe([
        'pending' => 1,
        'ready' => 1,
        'past' => 1,
    ]);

    // Only the statuses the active tab lists reach the page.
    expect(collect($config['purchases'])->pluck('title')->all())
        ->toHaveCount(2);
});

test('a finished purchase moves to the past tab', function () {
    $scenario = walletScenario(purchaseOverrides: [
        'status' => PurchaseStatus::Completed,
        'completed_at' => now(),
    ]);

    $student = $scenario['student'];

    $active = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index'))->assertOk()
    );

    expect($active['purchases'])->toBeEmpty();

    $past = purchasesConfig(
        $this->actingAs($student)->get(route('student.purchases.index', ['tab' => 'past']))->assertOk()
    );

    expect($past['tab'])->toBe('past');
    expect($past['urls']['reset'])->toBe(route('student.purchases.index', ['tab' => 'past']));
    expect(collect($past['purchases'])->pluck('id')->all())->toBe([$scenario['purchase']->id]);
});

test('each tab and chip link keeps the other filters applied', function () {
    $scenario = walletScenario();

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.index', ['tab' => 'past', 'type' => ServiceType::Lesson->value]))
            ->assertOk()
    );

    expect(collect($config['tabs'])->pluck('url', 'key')->all())->toBe([
        'active' => route('student.purchases.index', ['tab' => 'active', 'type' => 'lesson']),
        'past' => route('student.purchases.index', ['tab' => 'past', 'type' => 'lesson']),
    ]);

    // The chip for the type already selected is the link that clears it again.
    expect(collect($config['typeOptions'])->pluck('url', 'value')->all())->toBe([
        '' => route('student.purchases.index', ['tab' => 'past']),
        'lesson' => route('student.purchases.index', ['tab' => 'past', 'type' => 'lesson']),
        'summary' => route('student.purchases.index', ['tab' => 'past', 'type' => 'summary']),
        'paper_marking' => route('student.purchases.index', ['tab' => 'past', 'type' => 'paper_marking']),
        'paper_discussion' => route('student.purchases.index', ['tab' => 'past', 'type' => 'paper_discussion']),
        'qna' => route('student.purchases.index', ['tab' => 'past', 'type' => 'qna']),
    ]);
});

test('a service-type filter that matches nothing asks for the filter to be cleared', function () {
    $scenario = walletScenario();

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.purchases.index', ['type' => ServiceType::Qna->value]))
            ->assertOk()
    );

    expect($config['selectedType'])->toBe(ServiceType::Qna->value);
    expect($config['filtered'])->toBeTrue();
    expect($config['purchases'])->toBeEmpty();
    expect($config['urls']['reset'])->toBe(route('student.purchases.index', ['tab' => 'active']));
});

test('a cancelled purchase is announced on the page', function () {
    $scenario = walletScenario();

    $config = purchasesConfig(
        $this->actingAs($scenario['student'])
            ->withSession(['status' => 'purchase-cancelled'])
            ->get(route('student.purchases.index'))
            ->assertOk()
    );

    expect($config['flash']['cancelled'])->toBeTrue();
    expect($config['copy']['cancelled'])->toBe('The purchase was cancelled.');
});
