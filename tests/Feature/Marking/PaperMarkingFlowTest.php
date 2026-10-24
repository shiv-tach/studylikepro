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
 * constraint is relaxed (mirrors the service purchase flow tests).
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

it('renders the submission page with per-file validation errors after a rejected upload', function () {
    Storage::fake('local');

    $offering = markingOffering();
    $student = User::factory()->student()->onboarded()->create();

    $this->from(route('student.marking.create', $offering))
        ->actingAs($student)
        ->post(route('student.marking.store', $offering), [
            'files' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ])
        ->assertSessionHasErrors('files.0')
        ->assertRedirect(route('student.marking.create', $offering));

    $this->actingAs($student)
        ->get(route('student.marking.create', $offering))
        ->assertOk()
        ->assertSee('files.0');
});

it('404s the submission page for a non-marking offering', function () {
    $teacher = TeacherProfile::factory()->approved()->create();
    $offering = ServiceOffering::factory()
        ->forType(ServiceType::Qna)
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

it('renders the marking inbox island with a counted status rail and its paid orders', function () {
    Storage::fake('local');

    ['order' => $order, 'teacher' => $teacher, 'student' => $student] = submittedMarkingOrder();

    $config = teacherMarkingConfig(
        $this->actingAs($teacher->user)->get(route('teacher.marking.index'))->assertOk()
    );

    expect(collect($config['statusTabs'])->pluck('key')->all())
        ->toBe(['', 'submitted', 'in_progress', 'returned', 'accepted', 'disputed', 'resolved', 'cancelled'])
        ->and($config['statusTabs'][0]['active'])->toBeTrue()
        ->and($config['statusTabs'][0]['count'])->toBe(1)
        ->and($config['statusTabs'][1]['count'])->toBe(1)
        ->and($config['stats']['awaiting'])->toBe(1)
        ->and($config['stats']['overdue'])->toBe(0)
        ->and($config['orders'])->toHaveCount(1)
        ->and($config['orders'][0]['id'])->toBe($order->id)
        ->and($config['orders'][0]['url'])->toBe(route('teacher.marking.show', $order))
        ->and($config['orders'][0]['studentName'])->toBe($student->name)
        ->and($config['orders'][0]['status'])->toMatchArray([
            'value' => 'submitted',
            'label' => 'Submitted',
            'tone' => 'amber',
        ])
        ->and($config['orders'][0]['actionLabel'])->toBe('Mark paper')
        ->and($config['orders'][0]['deadlineLabel'])->toStartWith('Due ');
});

it('filters the marking inbox with a real status link', function () {
    Storage::fake('local');

    $teacher = TeacherProfile::factory()->approved()->create();

    submittedMarkingOrder(null, $teacher);
    $returned = submittedMarkingOrder(null, $teacher);

    $returned['order']->forceFill([
        'status' => MarkingOrderStatus::Returned,
        'returned_at' => now(),
        'marked_files' => [],
    ])->save();

    $config = teacherMarkingConfig(
        $this->actingAs($teacher->user)
            ->get(route('teacher.marking.index', ['status' => 'returned']))
            ->assertOk()
    );

    $tabs = collect($config['statusTabs']);

    expect($config['orders'])->toHaveCount(1)
        ->and($config['orders'][0]['id'])->toBe($returned['order']->id)
        ->and($tabs->firstWhere('key', 'returned')['active'])->toBeTrue()
        ->and($tabs->firstWhere('key', 'submitted')['count'])->toBe(1)
        ->and($tabs->firstWhere('key', 'returned')['count'])->toBe(1)
        ->and($config['stats']['returned'])->toBe(1);
});

it('renders the marking console island with the paper and the delivery form', function () {
    Storage::fake('local');

    ['order' => $order, 'teacher' => $teacher] = submittedMarkingOrder();

    $config = teacherMarkingShowConfig(
        $this->actingAs($teacher->user)->get(route('teacher.marking.show', $order))->assertOk()
    );

    expect($config['canMark'])->toBeTrue()
        ->and($config['files'])->toHaveCount(1)
        ->and($config['files'][0]['name'])->toBe('sample.pdf')
        ->and($config['files'][0]['sizeLabel'])->toBe('1 KB')
        ->and($config['files'][0]['url'])->toBe(route('teacher.marking.download', [$order, 'files', 0]))
        ->and($config['form']['maxFiles'])->toBe((int) config('studylikepro.services.marking_max_files'))
        ->and($config['form']['marks'])->toBe([['label' => '', 'value' => '']])
        ->and($config['form']['errors']['order'])->toBeNull()
        ->and($config['delivery'])->toBeNull()
        ->and($config['order']['subjectName'])->toBe($order->subject->name)
        ->and($config['order']['status']['tone'])->toBe('amber');
});

it('replays the feedback and the mark rows when a delivery is rejected', function () {
    Storage::fake('local');

    ['order' => $order, 'teacher' => $teacher] = submittedMarkingOrder();

    $this->from(route('teacher.marking.show', $order))
        ->actingAs($teacher->user)
        ->post(route('teacher.marking.mark', $order), [
            'feedback' => 'Solid work on the first half.',
            'marks' => [
                ['label' => 'Question 1', 'value' => '18/20'],
                ['label' => 'Question 2', 'value' => ''],
            ],
        ])
        ->assertSessionHasErrors('marked_files')
        ->assertRedirect(route('teacher.marking.show', $order));

    $config = teacherMarkingShowConfig(
        $this->actingAs($teacher->user)->get(route('teacher.marking.show', $order))
    );

    expect($config['form']['feedback'])->toBe('Solid work on the first half.')
        ->and($config['form']['marks'])->toHaveCount(2)
        ->and($config['form']['marks'][0])->toBe(['label' => 'Question 1', 'value' => '18/20'])
        ->and($config['form']['errors']['marked_files'])->not->toBeEmpty();
});

it('shows the delivered copy and the dispute on a disputed order', function () {
    Storage::fake('local');

    ['order' => $order, 'teacher' => $teacher] = submittedMarkingOrder();

    $order->forceFill([
        'status' => MarkingOrderStatus::Disputed,
        'returned_at' => now(),
        'marked_files' => [
            [
                'path' => 'marking-orders/'.$order->id.'/marked/sample.pdf',
                'original_name' => 'sample-marked.pdf',
                'mime_type' => 'application/pdf',
                'size' => 1100,
            ],
        ],
        'feedback' => 'Good work overall.',
        'marks' => [['label' => 'Question 1', 'value' => '18/20']],
        'dispute_reason' => 'The marked copy belongs to a different paper.',
    ])->save();

    $config = teacherMarkingShowConfig(
        $this->actingAs($teacher->user)->get(route('teacher.marking.show', $order))->assertOk()
    );

    expect($config['canMark'])->toBeFalse()
        ->and($config['form'])->toBeNull()
        ->and($config['delivery']['feedback'])->toBe('Good work overall.')
        ->and($config['delivery']['marks'])->toBe([['label' => 'Question 1', 'value' => '18/20']])
        ->and($config['delivery']['files'][0]['name'])->toBe('sample-marked.pdf')
        ->and($config['delivery']['files'][0]['url'])->toBe(route('teacher.marking.download', [$order, 'marked_files', 0]))
        ->and($config['dispute']['reason'])->toBe('The marked copy belongs to a different paper.')
        ->and($config['order']['status']['tone'])->toBe('rose');
});
