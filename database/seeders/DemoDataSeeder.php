<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\ClassificationStatus;
use App\Enums\MeetingStatus;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Enums\VerificationStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Review;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\LessonCompleted;
use App\Notifications\LessonReminder;
use App\Services\BookingFeeService;
use App\Services\BookingService;
use App\Services\ConversationService;
use App\Services\DisputeService;
use App\Services\Payments\EarningsService;
use App\Services\Payments\PayoutService;
use App\Services\ReviewService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    /**
     * Create demo accounts (with completed profiles) for local development only.
     */
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $student = $this->createUser('Demo Student', 'student@studylikepro.test', User::ROLE_STUDENT);
        StudentProfile::query()->firstOrCreate(
            ['user_id' => $student->id],
            [
                'grade_level' => 'high_school',
                'timezone' => 'Asia/Colombo',
                'learning_goals' => 'Improve maths and physics for board exams.',
                'completed_at' => now(),
            ]
        );

        $teacher = $this->createUser('Demo Teacher', 'teacher@studylikepro.test', User::ROLE_TEACHER);
        $teacherProfile = TeacherProfile::query()->firstOrCreate(
            ['user_id' => $teacher->id],
            [
                'headline' => 'Maths & Physics tutor for high school',
                'bio' => '10+ years helping students build strong fundamentals and exam confidence.',
                'experience_years' => 10,
                'education' => 'M.Sc. Physics, University of Delhi',
                'languages' => ['English', 'Sinhala'],
                'timezone' => 'Asia/Colombo',
                'hourly_rate_minor' => 80000,
                'verification_status' => VerificationStatus::Draft,
                'completed_at' => now(),
            ]
        );

        $mathematics = Subject::query()->where('slug', 'mathematics')->first();
        $physics = Subject::query()->where('slug', 'physics')->first();

        if ($mathematics && $physics) {
            $student->interestedSubjects()->sync([$mathematics->id, $physics->id]);
            $student->interestedTopics()->sync($this->topicIds($mathematics, ['algebra']));

            $teacherProfile->subjects()->sync([$mathematics->id, $physics->id]);
            $teacherProfile->subjects()->updateExistingPivot($mathematics->id, [
                'grade_levels' => ['high_school', 'college'],
            ]);
            $teacherProfile->subjects()->updateExistingPivot($physics->id, [
                'grade_levels' => ['high_school'],
                'rate_per_hour_minor' => 90000,
            ]);
            $teacherProfile->topics()->sync(array_merge(
                $this->topicIds($mathematics, ['algebra', 'geometry', 'calculus']),
                $this->topicIds($physics, ['mechanics', 'electricity-magnetism'])
            ));
        }

        $verifiedTeacher = $this->createUser('Priya Verma', 'priya@studylikepro.test', User::ROLE_TEACHER);
        $verifiedProfile = TeacherProfile::query()->firstOrCreate(
            ['user_id' => $verifiedTeacher->id],
            [
                'headline' => 'CBSE & IB Mathematics specialist',
                'bio' => 'Patient, exam-focused coaching for algebra, geometry and calculus.',
                'experience_years' => 7,
                'education' => 'M.Sc. Mathematics, IIT Bombay',
                'languages' => ['English', 'Sinhala', 'Tamil'],
                'timezone' => 'Asia/Colombo',
                'hourly_rate_minor' => 70000,
                'verification_status' => VerificationStatus::Approved,
                'submitted_at' => now()->subWeek(),
                'verified_at' => now()->subWeek(),
                'completed_at' => now(),
            ]
        );

        if ($mathematics) {
            $verifiedProfile->subjects()->sync([$mathematics->id]);
            $verifiedProfile->subjects()->updateExistingPivot($mathematics->id, [
                'grade_levels' => ['high_school', 'college'],
            ]);
            $verifiedProfile->topics()->sync(
                $this->topicIds($mathematics, ['algebra', 'calculus'])
            );
        }

        $chemistryTeacher = $this->createUser('Arjun Iyer', 'arjun@studylikepro.test', User::ROLE_TEACHER);
        $chemistryProfile = TeacherProfile::query()->firstOrCreate(
            ['user_id' => $chemistryTeacher->id],
            [
                'headline' => 'Chemistry & Biology for boards and NEET',
                'bio' => 'Concept-first coaching with lots of practice and past-paper drills.',
                'experience_years' => 5,
                'education' => 'B.Tech Biotechnology, Anna University',
                'languages' => ['English', 'Tamil'],
                'timezone' => 'Asia/Colombo',
                'hourly_rate_minor' => 60000,
                'verification_status' => VerificationStatus::Approved,
                'submitted_at' => now()->subDays(3),
                'verified_at' => now()->subDays(2),
                'completed_at' => now(),
            ]
        );

        $chemistry = Subject::query()->where('slug', 'chemistry')->first();
        $biology = Subject::query()->where('slug', 'biology')->first();

        if ($chemistry && $biology) {
            $chemistryProfile->subjects()->sync([$chemistry->id, $biology->id]);
            $chemistryProfile->subjects()->updateExistingPivot($chemistry->id, [
                'grade_levels' => ['high_school', 'college'],
                'rate_per_hour_minor' => 65000,
            ]);
            $chemistryProfile->subjects()->updateExistingPivot($biology->id, [
                'grade_levels' => ['high_school'],
            ]);
            $chemistryProfile->topics()->sync(array_merge(
                $this->topicIds($chemistry, ['organic-chemistry', 'chemical-equations']),
                $this->topicIds($biology, ['cell-biology', 'genetics'])
            ));
        }

        $this->seedAvailability($teacherProfile, 60, [
            [1, '18:00', '21:00'],
            [2, '18:00', '21:00'],
            [3, '18:00', '21:00'],
            [4, '18:00', '21:00'],
            [5, '18:00', '21:00'],
            [6, '10:00', '13:00'],
        ], offsetDays: 10);

        $this->seedAvailability($verifiedProfile, 45, [
            [2, '16:00', '19:00'],
            [4, '16:00', '19:00'],
            [0, '09:00', '12:00'],
        ], offsetDays: 21);

        $this->seedAvailability($chemistryProfile, 60, [
            [1, '07:00', '09:00'],
            [3, '07:00', '09:00'],
            [5, '07:00', '09:00'],
            [0, '15:00', '18:00'],
        ], offsetDays: 14);

        $this->seedDemoRequest($student, $verifiedProfile, $mathematics);
        $this->seedDemoBookings($student, $verifiedProfile, $chemistryProfile);
        $this->seedReviewHistory($verifiedProfile);
        $this->seedSupportQueues($student, $verifiedProfile, $chemistryProfile);
    }

    /**
     * One open dispute and one reported review so the admin console has real
     * work waiting for it.
     */
    private function seedSupportQueues(User $student, TeacherProfile $mathematicsTeacher, TeacherProfile $chemistryTeacher): void
    {
        if (Dispute::query()->exists()) {
            return;
        }

        $delivered = Booking::query()
            ->where('status', BookingStatus::Completed->value)
            ->where('teacher_profile_id', $chemistryTeacher->id)
            ->first();

        if ($delivered !== null) {
            app(DisputeService::class)->raise(
                $student,
                Dispute::REASON_PAYMENT,
                'I was charged for the full hour but the lesson stopped after 40 minutes. Can you check the payment?',
                $delivered->conversation,
                $delivered,
            );
        }

        $reported = Review::query()
            ->where('teacher_profile_id', $mathematicsTeacher->id)
            ->orderBy('rating')
            ->first();

        if ($reported !== null) {
            app(ReviewService::class)->flag(
                $reported,
                $mathematicsTeacher->user,
                'unfair',
                'The student left the call early and then rated the session for time we never had.',
            );
        }
    }

    /**
     * A confirmed lesson, a delivered lesson and a hold waiting for payment.
     */
    private function seedDemoBookings(User $student, TeacherProfile $mathematicsTeacher, TeacherProfile $chemistryTeacher): void
    {
        if ($student->bookings()->exists()) {
            return;
        }

        $bookings = app(BookingService::class);
        $timezone = 'Asia/Colombo';
        $mathematics = Subject::query()->where('slug', 'mathematics')->first();
        $chemistry = Subject::query()->where('slug', 'chemistry')->first();

        if ($mathematics) {
            $startsAt = $this->nextWeekday(CarbonInterface::TUESDAY, $timezone)->setTime(17, 0);
            $price = $bookings->priceMinor($mathematicsTeacher, $mathematics, 45);

            $confirmed = Booking::query()->create([
                'student_id' => $student->id,
                'teacher_profile_id' => $mathematicsTeacher->id,
                'subject_id' => $mathematics->id,
                'topic_id' => $mathematics->topics()->where('slug', 'algebra')->value('id'),
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes(45)->utc(),
                'status' => BookingStatus::Confirmed,
                'price_minor' => $price,
                'currency' => 'LKR',
                'learner_name' => $student->name,
                'learner_grade' => 'high_school',
                'confirmed_at' => now()->subDay(),
                ...$bookings->feeBreakdown($price),
                ...$this->bookingFee(),
            ]);
        }

        if ($chemistry && $mathematics) {
            $startsAt = $this->nextWeekday(CarbonInterface::THURSDAY, $timezone)->setTime(16, 0);
            $price = $bookings->priceMinor($mathematicsTeacher, $mathematics, 45);

            // A hold the demo student can still pay for.
            Booking::query()->create([
                'student_id' => $student->id,
                'teacher_profile_id' => $mathematicsTeacher->id,
                'subject_id' => $mathematics->id,
                'topic_id' => $mathematics->topics()->where('slug', 'calculus')->value('id'),
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes(45)->utc(),
                'status' => BookingStatus::PendingPayment,
                'price_minor' => $price,
                'currency' => 'LKR',
                'learner_name' => $student->name,
                'learner_grade' => 'high_school',
                'expires_at' => now()->addMinutes((int) platform_settings()->int('hold_ttl_minutes')),
                ...$bookings->feeBreakdown($price),
                ...$this->bookingFee(),
            ]);

            $deliveredAt = CarbonImmutable::now($timezone)->subDays(6)->setTime(7, 30);
            $price = $bookings->priceMinor($chemistryTeacher, $chemistry, 60);

            $delivered = Booking::query()->create([
                'student_id' => $student->id,
                'teacher_profile_id' => $chemistryTeacher->id,
                'subject_id' => $chemistry->id,
                'topic_id' => $chemistry->topics()->where('slug', 'organic-chemistry')->value('id'),
                'starts_at' => $deliveredAt->utc(),
                'ends_at' => $deliveredAt->addHour()->utc(),
                'status' => BookingStatus::Completed,
                'price_minor' => $price,
                'currency' => 'LKR',
                'learner_name' => $student->name,
                'learner_grade' => 'high_school',
                'confirmed_at' => $deliveredAt->subDays(2)->utc(),
                'started_at' => $deliveredAt->utc(),
                'completed_at' => $deliveredAt->addHour()->utc(),
                ...$bookings->feeBreakdown($price),
                ...$this->bookingFee(),
            ]);

            $this->seedCapturedPayment($confirmed);
            $this->seedCapturedPayment($delivered);

            $this->seedClassroom($confirmed);
            $this->seedClassroom($delivered, completed: true);

            $this->seedConversation($confirmed, $student, teacherName: $mathematicsTeacher->user->name, finished: false);
            $this->seedConversation($delivered, $student, teacherName: $chemistryTeacher->user->name, finished: true);

            $this->seedNotifications($student, $confirmed, $delivered);
        }
    }

    /**
     * The in-app notifications the scheduled jobs would have produced by now,
     * built from the real payloads so the bell and the centre have content.
     */
    private function seedNotifications(User $student, Booking $confirmed, Booking $delivered): void
    {
        $notifications = [
            new BookingConfirmed($confirmed),
            new LessonReminder($confirmed, LessonReminder::DAY_AHEAD_MINUTES),
            new LessonCompleted($delivered),
        ];

        foreach ($notifications as $index => $notification) {
            $student->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toDatabase($student),
                'read_at' => $index === 0 ? now()->subHours(2) : null,
            ]);
        }
    }

    /**
     * A lesson chat with the platform's own notes plus one message each way, so
     * the inbox has something to show.
     */
    private function seedConversation(Booking $booking, User $student, string $teacherName, bool $finished): void
    {
        $conversations = app(ConversationService::class);
        $conversation = $conversations->forBooking($booking);
        $teacherUser = $booking->teacherProfile->user;

        $conversations->systemMessage($booking, $finished
            ? 'Payment received — your lesson is confirmed. Use this chat to share what you want to cover, files or joining notes.'
            : 'Payment received — your lesson on '.$booking->starts_at
                ->copy()
                ->setTimezone(config('studylikepro.default_display_timezone'))
                ->format('D d M Y, H:i').' is confirmed. Use this chat to share what you want to cover, files or joining notes.');

        $conversations->post(
            $conversation,
            $student,
            $finished
                ? 'Thanks for the lesson — the functional groups finally make sense.'
                : 'Hi! Could we spend the first ten minutes on quadratic equations? I keep losing marks there.',
        );

        $conversations->post(
            $conversation,
            $teacherUser,
            $finished
                ? 'Glad it clicked. Send me your next test paper and I will mark the mechanisms for you.'
                : 'Absolutely. Bring the last test paper you wrote and we will work through those questions first.',
        );

        if ($finished) {
            $conversations->systemMessage($booking, 'Lesson delivered. Thanks for learning with Studylikepro — you can still use this thread for follow-up questions.');
        }
    }

    /**
     * A ready classroom, mirroring what the live-classes provider returns once a
     * booking is confirmed.
     */
    private function seedClassroom(Booking $booking, bool $completed = false): void
    {
        $slug = 'slp-'.$booking->id.'-demo';

        $booking->forceFill([
            'meeting_provider' => 'fake',
            'meeting_status' => MeetingStatus::Ready,
            'meeting_external_id' => $slug,
            'meeting_url' => "https://demo.daily.test/{$slug}?t=guest-demo",
            'host_meeting_url' => "https://demo.daily.test/{$slug}?t=host-demo",
            'meeting_started_at' => $completed ? $booking->starts_at : null,
            'meeting_ended_at' => $completed ? $booking->ends_at : null,
        ])->save();
    }

    /**
     * Reviews and the lessons behind them: two past students of the mathematics
     * teacher plus the demo student's own verdict on the delivered chemistry
     * lesson. The historical lessons were paid out long ago, so the teacher's
     * current balances stay as they are elsewhere in this seeder.
     */
    private function seedReviewHistory(TeacherProfile $mathematicsTeacher): void
    {
        $mathematics = Subject::query()->where('slug', 'mathematics')->first();
        $chemistry = Subject::query()->where('slug', 'chemistry')->first();

        if ($mathematics === null || Review::query()->exists()) {
            return;
        }

        $bookings = app(BookingService::class);
        $reviews = app(ReviewService::class);
        $history = [
            [
                'name' => 'Aisha Khan',
                'email' => 'aisha@studylikepro.test',
                'days_ago' => 21,
                'topic' => 'algebra',
                'rating' => 5,
                'comment' => 'Priya turned algebra into something I look forward to. She spotted the gaps from my old tests and fixed them one by one.',
            ],
            [
                'name' => 'Rohan Mehta',
                'email' => 'rohan@studylikepro.test',
                'days_ago' => 28,
                'topic' => 'calculus',
                'rating' => 4,
                'comment' => 'Great session — we ran out of time before the last topic, but the method for limits finally clicked.',
            ],
        ];

        $lessons = [];

        foreach ($history as $entry) {
            $pastStudent = $this->createUser($entry['name'], $entry['email'], User::ROLE_STUDENT);
            StudentProfile::query()->firstOrCreate(
                ['user_id' => $pastStudent->id],
                [
                    'grade_level' => 'high_school',
                    'timezone' => 'Asia/Colombo',
                    'completed_at' => now(),
                ]
            );

            $startsAt = CarbonImmutable::now('Asia/Colombo')->subDays($entry['days_ago'])->setTime(17, 0);
            $price = $bookings->priceMinor($mathematicsTeacher, $mathematics, 45);

            $lesson = Booking::query()->create([
                'student_id' => $pastStudent->id,
                'teacher_profile_id' => $mathematicsTeacher->id,
                'subject_id' => $mathematics->id,
                'topic_id' => $mathematics->topics()->where('slug', $entry['topic'])->value('id'),
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->addMinutes(45)->utc(),
                'status' => BookingStatus::Completed,
                'price_minor' => $price,
                'currency' => 'LKR',
                'learner_name' => $entry['name'],
                'learner_grade' => 'high_school',
                'confirmed_at' => $startsAt->subDays(2)->utc(),
                'started_at' => $startsAt->utc(),
                'completed_at' => $startsAt->addMinutes(45)->utc(),
                ...$bookings->feeBreakdown($price),
                ...$this->bookingFee(),
            ]);

            $this->seedCapturedPayment($lesson);
            $reviews->submit($lesson, $pastStudent, $entry['rating'], $entry['comment']);

            $lessons[] = $lesson;
        }

        // Those two lessons were batched and transferred weeks ago.
        $payout = app(PayoutService::class)->createFor($mathematicsTeacher, 'Paid out from the previous cycle.');
        if ($payout !== null) {
            app(PayoutService::class)->markPaid($payout, 'UTR-77412');
        }

        // The demo student's own review of the delivered chemistry lesson.
        $delivered = Booking::query()
            ->where('status', BookingStatus::Completed->value)
            ->where('teacher_profile_id', '!=', $mathematicsTeacher->id)
            ->first();

        if ($delivered !== null && $chemistry !== null) {
            $reviews->submit(
                $delivered,
                $delivered->student,
                5,
                'The functional groups finally make sense. The diagrams alone are worth the fee.',
            );
        }
    }

    /**
     * The student booking fee snapshot for a demo booking, offer included.
     *
     * @return array<string, mixed>
     */
    private function bookingFee(): array
    {
        $fee = app(BookingFeeService::class)->quote();

        return [
            'booking_fee_minor' => $fee['booking_fee_minor'],
            'booking_fee_discount_minor' => $fee['discount_minor'],
            'booking_fee_promotion_id' => $fee['promotion']?->id,
        ];
    }

    /**
     * A settled payment plus the teacher's ledger row, mirroring what the
     * gateway webhook would have produced.
     */
    private function seedCapturedPayment(Booking $booking): void
    {
        $payment = Payment::query()->create([
            'booking_id' => $booking->id,
            'student_id' => $booking->student_id,
            'gateway' => 'fake',
            'gateway_order_id' => 'order_demo_'.$booking->id,
            'gateway_payment_id' => 'pay_demo_'.$booking->id,
            'amount_minor' => $booking->totalMinor(),
            'currency' => $booking->currency,
            'status' => PaymentStatus::Captured,
            'method' => 'upi',
            'captured_at' => $booking->confirmed_at ?? now(),
            'gateway_payload' => ['seed' => 'demo'],
        ]);

        app(EarningsService::class)->recordForBooking($booking, $payment);
    }

    /**
     * The next date falling on the given weekday in the teacher's timezone.
     */
    private function nextWeekday(int $dayOfWeek, string $timezone): CarbonImmutable
    {
        for ($days = 1; $days <= 8; $days++) {
            $candidate = CarbonImmutable::now($timezone)->startOfDay()->addDays($days);

            if ($candidate->dayOfWeek === $dayOfWeek) {
                return $candidate;
            }
        }

        return CarbonImmutable::now($timezone)->addDays(2)->startOfDay();
    }

    /**
     * An already-matched open request with one pending teacher proposal.
     */
    private function seedDemoRequest(User $student, TeacherProfile $teacher, ?Subject $subject): void
    {
        if ($subject === null || $student->tutoringRequests()->exists()) {
            return;
        }

        $topic = $subject->topics()->where('slug', 'algebra')->first();

        if ($topic === null) {
            return;
        }

        $timezone = 'Asia/Colombo';
        $windowStart = null;

        for ($days = 1; $days <= 8; $days++) {
            $candidate = CarbonImmutable::now($timezone)->startOfDay()->addDays($days);

            if ($candidate->dayOfWeek === CarbonInterface::TUESDAY) {
                $windowStart = $candidate->setTime(16, 0);

                break;
            }
        }

        if ($windowStart === null) {
            return;
        }

        $windowEnd = $windowStart->setTime(19, 0);

        $tutoringRequest = $student->tutoringRequests()->create([
            'subject_id' => $subject->id,
            'topic_id' => $topic->id,
            'description' => 'I keep losing marks on quadratic factorising — especially when the middle term splits into fractions. My board exam is next month and I want to fix the basics before we move on.',
            'classification_status' => ClassificationStatus::Completed,
            'ai_confidence' => 0.92,
            'ai_payload' => [
                'raw' => ['source' => 'demo-seeder'],
                'subject_id' => $subject->id,
                'topic_id' => $topic->id,
                'confidence' => 0.92,
                'alternates' => [],
            ],
            'preferred_windows' => [[
                'starts_at' => $windowStart->utc()->toIso8601String(),
                'ends_at' => $windowEnd->utc()->toIso8601String(),
            ]],
            'budget_minor' => 75000,
            'status' => RequestStatus::Open,
            'expires_at' => $windowStart->addDay(),
        ]);

        $startsAt = $windowStart->setTime(17, 30);
        $endsAt = $startsAt->addMinutes($teacher->lessonDuration());

        $tutoringRequest->responses()->create([
            'teacher_profile_id' => $teacher->id,
            'status' => ResponseStatus::Pending,
            'message' => 'Happy to help. Bring your latest test paper along — we will rebuild factorising from the basics, then drill exam questions.',
            'starts_at' => $startsAt->utc(),
            'ends_at' => $endsAt->utc(),
            'price_minor' => $teacher->effectiveRateFor($subject),
            'responded_at' => now()->subHours(2),
        ]);
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string}>  $ranges  [day, start, end] in the teacher's timezone
     */
    private function seedAvailability(TeacherProfile $profile, int $lessonMinutes, array $ranges, int $offsetDays): void
    {
        $profile->update(['lesson_duration_minutes' => $lessonMinutes]);

        if ($profile->availabilitySlots()->doesntExist()) {
            foreach ($ranges as [$day, $start, $end]) {
                $profile->availabilitySlots()->create([
                    'day_of_week' => $day,
                    'start_minute' => TeacherAvailabilitySlot::toMinute($start),
                    'end_minute' => TeacherAvailabilitySlot::toMinute($end),
                ]);
            }
        }

        if ($profile->timeOff()->doesntExist()) {
            $profile->timeOff()->create([
                'starts_on' => now()->addDays($offsetDays)->toDateString(),
                'ends_on' => now()->addDays($offsetDays + 1)->toDateString(),
                'reason' => 'Family function',
            ]);
        }
    }

    /**
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function topicIds(Subject $subject, array $slugs): array
    {
        return $subject->topics()->whereIn('slug', $slugs)->pluck('id')->all();
    }

    private function createUser(string $name, string $email, string $role): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole($role);

        return $user;
    }
}
