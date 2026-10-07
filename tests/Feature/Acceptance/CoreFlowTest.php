<?php

use App\Enums\BookingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\DisputeStatus;
use App\Enums\EarningStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Subject;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\Topic;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Notifications\DisputeResolved;
use App\Services\AdminCsvExporter;
use App\Services\ConversationService;
use App\Services\Payments\FakePaymentGateway;
use App\Services\ReviewService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * The launch acceptance run from section 11 of the plan, executed against the
 * real routes: a student uploads a question, the AI picks the topic, a verified
 * teacher takes the lesson, the money moves, the lesson happens, the review
 * lands — and support can refund and resolve afterwards.
 *
 * Everything here goes through HTTP; only the two external providers are faked
 * (OpenAI via Http::fake, payments and video by the offline gateways).
 */
beforeEach(function () {
    Http::preventStrayRequests();

    // A tiny catalog: one subject, one topic, and the model is told to pick it.
    $this->subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $this->topic = Topic::factory()->create([
        'subject_id' => $this->subject->id,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    // The evening slot the whole story hangs on: two days out, 18:00–20:00 Sri Lanka time.
    $this->windowStart = CarbonImmutable::now('Asia/Colombo')->addDays(2)->setTime(18, 0);

    Http::fake([
        '*/chat/completions' => Http::response([
            'choices' => [[
                'message' => ['content' => json_encode([
                    'subject' => 'Mathematics',
                    'topic' => 'Algebra',
                    'confidence' => 0.93,
                    'alternates' => [],
                ])],
                'finish_reason' => 'stop',
            ]],
        ]),
    ]);
});

/**
 * Registers through the real form and marks the email verified, which is what
 * clicking the verification link does.
 *
 * @param  array<string, mixed>  $overrides
 */
function signUp(string $role, array $overrides = []): User
{
    $email = $overrides['email'] ?? $role.'-'.Str::lower(Str::random(6)).'@example.test';

    // Registering signs the new account in; the next sign-up needs a clean guest.
    Auth::logout();

    $payload = [
        'name' => $overrides['name'] ?? 'Test '.ucfirst($role),
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => $role,
    ];

    if ($role === User::ROLE_TEACHER) {
        [, $payload['invite']] = makeTeacherInvite();
    }

    test()->post('/register', $payload)->assertRedirect();

    $user = User::query()->where('email', $email)->firstOrFail();
    $user->markEmailAsVerified();

    return $user;
}

/**
 * The checkout + signed provider callback, exactly as the gateway would deliver
 * it.
 */
function payForBooking(Booking $booking, User $student): Payment
{
    test()->actingAs($student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect();

    $payment = $booking->payments()->latest('id')->firstOrFail();
    $gateway = app(FakePaymentGateway::class);
    [$body, $signature] = $gateway->localCapturePayload($payment);

    test()->post(route('payments.webhook', 'fake'), [
        'payload' => json_encode($body),
        'signature' => $signature,
    ])->assertOk()->assertJson(['status' => 'processed']);

    return $payment->refresh();
}

/**
 * A verified teacher with the catalog attached and an evening availability
 * window, ready to answer requests. Returns the user; the profile is on it.
 */
function verifiedTeacher(Subject $subject, Topic $topic, CarbonImmutable $windowStart): User
{
    $teacherUser = signUp(User::ROLE_TEACHER, ['name' => 'Verified Tutor']);

    test()->actingAs($teacherUser)
        ->put(route('teacher.profile.update'), [
            'headline' => 'Algebra and calculus coach',
            'bio' => 'Ten years of board-exam coaching with weekly progress notes.',
            'experience_years' => 10,
            'education' => 'M.Sc. Mathematics, University of Delhi',
            'languages' => ['English', 'Sinhala'],
            'hourly_rate' => 800,
        ])
        ->assertRedirect();

    // Fresh instances: the guard caches the model (including a null relation)
    // between requests inside one test, unlike a real per-request lifecycle.
    $teacherUser->refresh()->unsetRelation('teacherProfile');

    test()->actingAs($teacherUser)
        ->post(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('aadhaar.jpg', 800, 500),
        ])
        ->assertRedirect();

    $teacherUser->refresh()->unsetRelation('teacherProfile');

    test()->actingAs($teacherUser)
        ->post(route('teacher.verification.submit'), ['agree' => '1'])
        ->assertRedirect();

    $profile = $teacherUser->teacherProfile()->firstOrFail();

    expect($profile->verification_status)->toBe(VerificationStatus::Pending);

    $admin = User::factory()->admin()->create();
    test()->actingAs($admin)
        ->post(route('admin.verifications.approve', $profile))
        ->assertRedirect();

    expect($profile->refresh()->verification_status)->toBe(VerificationStatus::Approved);

    // Teach this subject, at this rate, on the evening the student asked for.
    test()->actingAs($teacherUser)->post(route('teacher.subjects.store'), [
        'subjects' => [$subject->id],
    ])->assertRedirect(route('teacher.subjects.index'));

    $teacherUser->refresh()->unsetRelation('teacherProfile');

    test()->actingAs($teacherUser)->post(route('teacher.subjects.update', $subject), [
        'topics' => [$topic->id],
        'grade_levels' => ['high_school'],
        'rate_per_hour' => 800,
    ])->assertRedirect();

    test()->actingAs($teacherUser)->post(route('teacher.availability.slots.store'), [
        'day_of_week' => $windowStart->dayOfWeek,
        'start_time' => '18:00',
        'end_time' => '21:00',
    ])->assertRedirect();

    return $teacherUser;
}

it('runs the core flow from question photo to review', function () {
    // 1. Student signs up, completes the profile and picks what they need help with.
    $student = signUp(User::ROLE_STUDENT, ['name' => 'Aarav Mehta']);

    $this->actingAs($student)->put(route('student.profile.update'), [
        'grade_level' => 'high_school',
        'learning_goals' => 'Board exams in two months.',
    ])->assertRedirect();

    $this->actingAs($student)->put(route('student.interests.update'), [
        'subjects' => [$this->subject->id],
        'topics' => [$this->topic->id],
    ])->assertRedirect();

    // 2. A teacher applies, uploads ID, accepts the terms and gets verified.
    $teacherUser = verifiedTeacher($this->subject, $this->topic, $this->windowStart);

    // 3. The student uploads the question photo; the AI classifies it inline.
    $this->actingAs($student)->post(route('student.requests.store'), [
        'description' => 'I keep losing marks on quadratic factorising. Can we work through last year\'s paper?',
        'budget' => '900',
        'attachments' => [UploadedFile::fake()->image('question.png', 900, 1200)],
        'windows' => [[
            'date' => $this->windowStart->toDateString(),
            'from' => '18:00',
            'to' => '20:00',
        ]],
    ])->assertRedirect();

    $request = TutoringRequest::query()->firstOrFail();

    expect($request->classification_status)->toBe(ClassificationStatus::Completed)
        ->and($request->subject_id)->toBe($this->subject->id)
        ->and($request->topic_id)->toBe($this->topic->id)
        ->and($request->status)->toBe(RequestStatus::Open)
        ->and($request->attachments()->count())->toBe(1);

    // The teacher sees the question in their inbox, including the photo.
    $this->actingAs($teacherUser)
        ->get(route('teacher.requests.index'))
        ->assertOk()
        ->assertSee('quadratic factorising');

    $attachment = $request->attachments()->firstOrFail();
    $this->actingAs($teacherUser)->get(route('request-attachments.show', $attachment))->assertOk();

    // 4. The teacher proposes the slot; the student gets a payable hold.
    $this->actingAs($teacherUser)->post(route('teacher.requests.respond', $request), [
        'action' => 'accept',
        'starts_at' => $this->windowStart->toIso8601String(),
        'message' => 'Bring the paper and we will work through question 4 first.',
    ])->assertRedirect(route('teacher.requests.index'));

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->price_minor)->toBe(80000);

    $this->actingAs($student)
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Your slot is held')
        ->assertSee('Pay');

    // 5. Payment: checkout, then the signed provider callback confirms the lesson.
    $payment = payForBooking($booking, $student);
    $booking->refresh();

    expect($payment->status)->toBe(PaymentStatus::Captured)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->meetingIsReady())->toBeTrue();

    $earning = TeacherEarning::query()->firstOrFail();

    expect($earning->status)->toBe(EarningStatus::Pending)
        ->and($earning->amount_minor)->toBe($booking->teacher_payout_minor);

    // Both sides can open the classroom and the receipt.
    $this->actingAs($student)->get(route('classroom.show', $booking))->assertOk();
    $this->actingAs($teacherUser)->get(route('classroom.show', $booking))->assertOk();
    $this->actingAs($student)->get(route('receipts.show', $booking))->assertOk()->assertSee('800');

    // 6. They talk before the lesson.
    $conversation = $booking->conversation ?? app(ConversationService::class)->forBooking($booking);

    $this->actingAs($student)
        ->post(route('messages.store', $conversation), ['body' => 'Should I bring the textbook too?'])
        ->assertRedirect();

    $this->actingAs($teacherUser)
        ->post(route('messages.store', $conversation), ['body' => 'Just the question paper is enough.'])
        ->assertRedirect();

    expect($conversation->messages()->count())->toBe(3); // two messages plus the confirmation note

    // 7. The lesson happens: the teacher starts it and closes it out.
    $booking->forceFill([
        'starts_at' => now()->subMinutes(5),
        'ends_at' => now()->addMinutes(55),
    ])->save();

    $this->actingAs($teacherUser)
        ->post(route('teacher.bookings.start', $booking))
        ->assertRedirect(route('teacher.bookings.show', $booking));

    $this->actingAs($teacherUser)
        ->post(route('teacher.bookings.complete', $booking))
        ->assertRedirect(route('teacher.bookings.show', $booking));

    $booking->refresh();
    $earning->refresh();

    expect($booking->status)->toBe(BookingStatus::Completed)
        ->and($earning->status)->toBe(EarningStatus::Eligible)
        ->and($earning->netMinor())->toBe($booking->teacher_payout_minor);

    // The chat recorded the delivery for both sides.
    $this->actingAs($student)
        ->get(route('messages.show', $conversation))
        ->assertOk()
        ->assertSee('Lesson delivered');

    // 8. The student reviews the lesson and the teacher's rating updates.
    $this->actingAs($student)->post(route('student.reviews.store', $booking), [
        'rating' => 5,
        'comment' => 'Explained factorising in a way that finally stuck. Booked again for next week.',
    ])->assertRedirect();

    $profile = TeacherProfile::query()->findOrFail($booking->teacher_profile_id);

    expect($profile->rating_count)->toBe(1)
        ->and((float) $profile->rating_avg)->toBe(5.0)
        ->and($profile->lessons_completed_count)->toBe(1);

    // 9. The history and the earnings pages show the same story.
    $this->actingAs($student)
        ->get(route('student.bookings.index'))
        ->assertOk()
        ->assertSee('Completed')
        ->assertSee('Verified Tutor');

    $this->actingAs($teacherUser)
        ->get(route('teacher.earnings.index'))
        ->assertOk()
        ->assertSee('800');

    // 10. And the teacher sees the review on their own page.
    $this->actingAs($teacherUser)
        ->get(route('teacher.reviews.index'))
        ->assertOk()
        ->assertSee('Explained factorising in a way that finally stuck');
});

it('lets support refund a lesson and settle a dispute', function () {
    // A paid, delivered lesson the student is unhappy about.
    $scenario = paidBookingScenario();
    $student = $scenario['student'];
    $teacherUser = $scenario['teacherUser'];
    $booking = $scenario['booking'];

    $conversation = app(ConversationService::class)->forBooking($booking);

    $booking->forceFill([
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'completed_at' => now()->subDay(),
    ])->save();

    // The student reports the problem from the chat.
    $this->actingAs($student)->post(route('messages.report', $conversation), [
        'reason' => Dispute::REASON_QUALITY,
        'details' => 'The lesson stopped after twenty minutes and we never covered the topic.',
    ])->assertRedirect();

    $dispute = Dispute::query()->firstOrFail();

    expect($dispute->status)->toBe(DisputeStatus::Open)
        ->and($dispute->against_id)->toBe($teacherUser->id);

    // Support picks the case up and refunds in full.
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.disputes.show', $dispute))
        ->assertOk()
        ->assertSee('The lesson stopped after twenty minutes');

    $this->actingAs($admin)
        ->post(route('admin.disputes.review', $dispute))
        ->assertRedirect();

    $this->actingAs($admin)
        ->post(route('admin.disputes.resolve', $dispute), [
            'resolution' => Dispute::RESOLUTION_REFUND_FULL,
            'notes' => 'Classroom log shows a 20-minute session; refunded in full.',
        ])
        ->assertRedirect(route('admin.disputes.show', $dispute));

    $dispute->refresh();
    $refund = Refund::query()->findOrFail($dispute->refund_id);

    expect($dispute->status)->toBe(DisputeStatus::Resolved)
        ->and($refund->percent)->toBe(100)
        ->and($refund->amount_minor)->toBe($scenario['payment']->amount_minor)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Refunded);

    // Everyone was told, and the decision is in the audit trail.
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $student->id,
        'type' => DisputeResolved::class,
    ]);
    $this->assertDatabaseHas('notifications', [
        'notifiable_id' => $teacherUser->id,
        'type' => DisputeResolved::class,
    ]);
    $this->assertDatabaseHas('activity_log', ['action' => 'admin.disputes.resolve']);

    // The student sees the outcome on the lesson and in the chat.
    $this->actingAs($student)
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Refund');

    // Support can also refund straight from the payments console.
    $other = paidBookingScenario();
    $admin2 = User::factory()->admin()->create();

    $this->actingAs($admin2)
        ->post(route('admin.payments.refund', $other['payment']), [
            'percent' => 50,
            'reason' => 'Agreed goodwill refund.',
        ])
        ->assertRedirect(route('admin.payments.show', $other['payment']));

    expect($other['payment']->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($other['payment']->refundableMinor())->toBe($other['payment']->amount_minor - (int) round($other['payment']->amount_minor / 2));
});

it('gives support the reports and exports they run the business on', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $scenario['booking']->forceFill([
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'completed_at' => now()->subDay(),
    ])->save();

    app(ReviewService::class)->submit($scenario['booking']->refresh(), $scenario['student'], 4, 'Solid lesson.');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Collected')
        ->assertSee(platform_settings()->formatMinor(100000))
        ->assertSee('Teacher performance')
        ->assertSee($scenario['teacherUser']->name);

    foreach (AdminCsvExporter::TYPES as $type) {
        $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => $type]))
            ->assertOk();
    }

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Collected (30 days)')
        ->assertSee('Platform commission');
});
