<?php

use App\Enums\BookingStatus;
use App\Enums\DisputeStatus;
use App\Enums\EarningStatus;
use App\Enums\PaymentStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\EducationLevel;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Subject;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\DisputeResolved;
use App\Services\AdminCsvExporter;
use App\Services\ConversationService;
use App\Services\Payments\FakePaymentGateway;
use App\Services\ReviewService;
use App\Services\Sms\FakeSmsGateway;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * The launch acceptance run from section 11 of the plan, executed against the
 * real routes: a student books a verified teacher's slot directly, the money
 * moves, the lesson happens, the review lands — and support can refund and
 * resolve afterwards.
 *
 * Everything here goes through HTTP; only the payment and video providers are
 * faked by the offline gateways.
 */
beforeEach(function () {
    // A tiny catalog: one O/L subject and one lesson for the booking.
    $this->subject = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'education_level_id' => EducationLevel::query()->where('key', 'ol')->value('id'),
    ]);
    $this->lesson = Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_id' => gradeId(11),
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    // The evening slot the whole story hangs on: two days out, 18:00–20:00 Sri Lanka time.
    $this->windowStart = CarbonImmutable::now('Asia/Colombo')->addDays(2)->setTime(18, 0);
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

    // The form now collects the two parts; a `name` override is split the same
    // way the migration and the model do, so existing callers keep working.
    [$firstName, $lastName] = User::splitName($overrides['name'] ?? 'Test '.ucfirst($role));

    $payload = [
        'first_name' => $overrides['first_name'] ?? $firstName,
        'last_name' => $overrides['last_name'] ?? ($lastName ?? 'User'),
        'email' => $email,
        // Unique per call because the column is unique.
        'mobile' => $overrides['mobile'] ?? '07'.fake()->numberBetween(0, 8).fake()->unique()->numerify('#######'),
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => $role,
    ];

    if ($role === User::ROLE_TEACHER) {
        [, $payload['invite']] = makeTeacherInvite();
    }

    test()->post('/register', $payload)->assertRedirect(route('register.verify.show'));
    test()->post(route('register.verify.store'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('dashboard', absolute: false));

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
 * window, ready to be booked. Returns the user; the profile is on it.
 */
function verifiedTeacher(Subject $subject, Lesson $lesson, CarbonImmutable $windowStart): User
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

    // Onboarding step 2: a weekly range is required before verification opens.
    test()->actingAs($teacherUser)
        ->put(route('teacher.availability.setup.update'), [
            'days' => [
                ['value' => 1, 'enabled' => '1', 'ranges' => [['start_time' => '15:00', 'end_time' => '17:00']]],
            ],
        ])
        ->assertRedirect(route('teacher.onboarding.verification'));

    $teacherUser->refresh()->unsetRelation('teacherProfile');

    test()->actingAs($teacherUser)
        ->post(route('teacher.verification.documents.store'), [
            'type' => 'id_proof',
            'document' => UploadedFile::fake()->image('aadhaar.jpg', 800, 500),
        ])
        ->assertRedirect();

    $teacherUser->refresh()->unsetRelation('teacherProfile');

    // The application cannot be submitted until at least one subject is picked.
    test()->actingAs($teacherUser)
        ->post(route('teacher.verification.subjects.store'), [
            'subjects' => [$subject->id],
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

    // The one-time "You're live" screen is acknowledged, as a real teacher
    // would do on their first page load after approval.
    $profile->update(['approved_seen_at' => now()]);

    // Teach this subject's lesson, at this rate, on the evening the student booked.
    $teacherUser->refresh()->unsetRelation('teacherProfile');

    test()->actingAs($teacherUser)->post(route('teacher.subjects.update', $subject), [
        'lessons' => [$lesson->id],
        'grades' => [gradeId(11)],
        'rate_per_hour' => 800,
    ])->assertRedirect();

    test()->actingAs($teacherUser)->post(route('teacher.availability.slots.store'), [
        'day_of_week' => $windowStart->dayOfWeek,
        'start_time' => '18:00',
        'end_time' => '21:00',
    ])->assertRedirect();

    return $teacherUser;
}

it('runs the core flow from direct booking to review', function () {
    // 1. Student signs up, finishes the onboarding wizard and picks what they need help with.
    $student = signUp(User::ROLE_STUDENT, ['name' => 'Aarav Mehta']);

    $this->actingAs($student)->post(route('student.onboarding.store'), [
        'level_id' => EducationLevel::query()->where('key', 'ol')->value('id'),
        'grade_id' => gradeId(11),
        'learning_language' => 'English',
    ])->assertRedirect(route('student.dashboard'));

    $this->actingAs($student)->put(route('student.interests.update'), [
        'subjects' => [$this->subject->id],
        'lessons' => [$this->lesson->id],
    ])->assertRedirect();

    // 2. A teacher applies, uploads ID, accepts the terms and gets verified.
    $teacherUser = verifiedTeacher($this->subject, $this->lesson, $this->windowStart);

    // 3. The student books the teacher's open slot; that creates a payable hold.
    $this->actingAs($student)->post(route('student.bookings.store', $teacherUser->teacherProfile), [
        'subject_id' => $this->subject->id,
        'lesson_id' => $this->lesson->id,
        'duration' => 60,
        'starts_at' => $this->windowStart->toIso8601String(),
        'learner_name' => 'Aarav Mehta',
    ])->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->student_id)->toBe($student->id)
        ->and($booking->subject_id)->toBe($this->subject->id)
        ->and($booking->lesson_id)->toBe($this->lesson->id)
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
        'details' => 'The lesson stopped after twenty minutes and we never covered the lesson.',
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
