<?php

use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\PaperMarkingOrder;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\LessonCompleted;
use Illuminate\Support\Carbon;

/**
 * The signed-in chrome — sidebar, top bar and notification bell — is rendered by
 * resources/js/app-shell, so the Blade layout only has to hand the Vue shell a
 * correct config island. These tests pin that contract down: the route map, the
 * navigation for the signed-in role, and the bell. `shellConfig()` reads the
 * island and lives in tests/Pest.php.
 */
test('the shell island carries the user, the route map and the student menu', function () {
    $student = User::factory()->student()->onboarded()->create();

    $config = shellConfig(
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
    );

    expect($config['user']['name'])->toBe($student->displayName());
    expect($config['user']['fullName'])->toBe($student->name);
    expect($config['user']['initials'])->toBe(mb_substr($student->name, 0, 2));
    expect($config['user']['roleLabel'])->toBe('Student');
    expect($config['theme']['preset'])->toBe('classic');

    expect($config['urls']['profile'])->toBe(route('profile.edit'));
    expect($config['urls']['settings'])->toBe(route('settings.index'));
    expect($config['urls']['logout'])->toBe(route('logout'));
    expect($config['urls']['notificationOpen'])->toContain('NOTIFICATION_ID');

    expect(collect($config['navItems'])->pluck('label')->all())
        ->toContain('My subjects', 'My lessons', 'Find a teacher');
});

test('an onboarding teacher only gets the three setup steps in the sidebar', function () {
    $teacher = User::factory()->teacher()->create();

    $config = shellConfig(
        $this->actingAs($teacher)->get(route('teacher.profile'))->assertOk()
    );

    $labels = collect($config['navItems'])->pluck('label')->all();

    // The dashboard/setup steps, then the profile + settings links every role gets.
    expect(array_slice($labels, 0, 3))->toEqual(['Teaching profile', 'Weekly availability', 'Verification']);
    expect($labels)->not->toContain('Earnings');
});

test('a teacher sidebar counts the lessons still to teach and never badges reviews', function () {
    $scenario = paidBookingScenario(hoursAhead: 48);

    $config = shellConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('teacher.schedule.index'))->assertOk()
    );

    $items = collect($config['navItems'])->keyBy('label');

    expect($items['My lessons']['badge'])->toBe(1)
        ->and($items['Reviews']['badge'])->toBeNull();
});

test('the teacher sidebar counts the papers still waiting to be marked', function () {
    $teacher = TeacherProfile::factory()->approved()->create();

    $offering = ServiceOffering::factory()->forType(ServiceType::PaperMarking)->create([
        'teacher_profile_id' => $teacher->id,
    ]);

    $purchaseFor = fn (PurchaseStatus $status, ?Carbon $paidAt = null) => ServicePurchase::factory()
        ->forOffering($offering)
        ->create([
            'status' => $status,
            'paid_at' => $paidAt,
        ]);

    PaperMarkingOrder::factory()->forPurchase($purchaseFor(PurchaseStatus::Active, now()->subHour()))->create();
    PaperMarkingOrder::factory()->forPurchase($purchaseFor(PurchaseStatus::Active, now()->subHour()))->inProgress()->create();

    // Neither of these is work: one is not paid for, the other is delivered.
    PaperMarkingOrder::factory()->forPurchase($purchaseFor(PurchaseStatus::PendingPayment))->create();
    PaperMarkingOrder::factory()->forPurchase($purchaseFor(PurchaseStatus::Active, now()->subHour()))->returned()->create();

    $config = shellConfig(
        $this->actingAs($teacher->user)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect(collect($config['navItems'])->keyBy('label')['Paper Marking']['badge'])->toBe(2);
});

test('the marking badge is absent when nothing is waiting', function () {
    $teacher = TeacherProfile::factory()->approved()->create();

    $config = shellConfig(
        $this->actingAs($teacher->user)->get(route('teacher.schedule.index'))->assertOk()
    );

    expect(collect($config['navItems'])->keyBy('label')['Paper Marking']['badge'])->toBeNull();
});

test('the notification bell count and rows ride in the island', function () {
    $scenario = paidBookingScenario();
    $scenario['student']->notify(new LessonCompleted($scenario['booking']));

    $config = shellConfig(
        $this->actingAs($scenario['student'])->get(route('student.dashboard'))->assertOk()
    );

    expect($config['notifications']['unreadCount'])->toBe(1);
    expect($config['notifications']['items'])->toHaveCount(1);
    expect($config['notifications']['items'][0]['unread'])->toBeTrue();
    expect($config['notifications']['items'][0]['title'])->not->toBe('');
});

test('the layout ships the Vue mount point and a no-JS navigation fallback', function () {
    $student = User::factory()->student()->onboarded()->create();

    $response = $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

    $response->assertSee('id="studylikepro-shell"', false);
    $response->assertSee('<noscript>', false);

    // The Alpine drawer is gone; the shell owns that state now.
    $response->assertDontSee('sidebarOpen', false);
});
