<?php

use App\Enums\ApprovalStatus;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\BookingRequestReceived;
use App\Services\BookingFeeService;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * An approved maths teacher with a two-hour evening window two days out,
 * a Grade 11 lesson, and a Grade 11 student ready to book directly into it.
 *
 * @return array<string, mixed>
 */
function directBookingScenario(int $durationMinutes = 60): array
{
    $gradeId = gradeId(11);

    $subject = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'education_level_id' => Grade::query()->whereKey($gradeId)->value('education_level_id'),
    ]);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'grade_id' => $gradeId,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 60000,
        'lesson_duration_minutes' => $durationMinutes,
    ]);
    $teacher->subjects()->attach($subject->id, ['status' => ApprovalStatus::Approved, 'grade_levels' => [(string) $gradeId]]);
    $teacher->lessons()->attach($lesson->id);

    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    TeacherAvailabilitySlot::factory()
        ->on($day->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();
    $student->studentProfile->update(['grade_id' => $gradeId]);

    return [
        'subject' => $subject,
        'lesson' => $lesson,
        'teacher' => $teacher,
        'teacherUser' => $teacherUser,
        'student' => $student,
        'day' => $day,
        'slot' => $day->setTime(18, 0),
    ];
}

it('shows the direct booking page with open slots and the lesson price', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee($scenario['teacherUser']->name)
        ->assertSee('600')
        ->assertSee('18:00')
        ->assertSee('Reserve this slot');
});

it('offers only the teacher\'s nearest open days in the time picker', function () {
    $scenario = directBookingScenario();
    $scenario['student']->studentProfile->update(['timezone' => 'UTC']);

    // The scenario already has an evening window two days out; add windows one,
    // three and four days out so only the first three open days survive the cap.
    foreach ([1, 3, 4] as $offset) {
        $day = CarbonImmutable::now('UTC')->addDays($offset)->startOfDay();

        TeacherAvailabilitySlot::factory()
            ->on($day->dayOfWeek, '09:00', '10:00')
            ->create(['teacher_profile_id' => $scenario['teacher']->id]);
    }

    $config = bookingCreateConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.create', $scenario['teacher']))
            ->assertOk()
    );

    $today = CarbonImmutable::now('UTC');

    expect($config['slots'])->toHaveCount(3)
        ->and(array_column($config['slots'], 'date'))->toBe([
            $today->addDays(1)->toDateString(),
            $today->addDays(2)->toDateString(),
            $today->addDays(3)->toDateString(),
        ])
        ->and($config['slots'][1]['slots'][0]['label'])->toBe('18:00')
        ->and($config['slots'][0]['weekday'])->toBe($today->addDays(1)->format('D'))
        ->and($config['slots'][0]['slotCountLabel'])->toBe('1 slot');
});

it('only offers the subjects the admin approved on the booking page', function () {
    $scenario = directBookingScenario();

    $rejected = Subject::factory()->create([
        'name' => 'Rejected History',
        'slug' => 'rejected-history',
        'education_level_id' => $scenario['subject']->education_level_id,
    ]);
    $scenario['teacher']->subjects()->attach($rejected->id, [
        'status' => ApprovalStatus::Rejected,
        'grade_levels' => [(string) gradeId(11)],
    ]);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertDontSee('Rejected History');

    // Posting the rejected subject around the picker is refused too.
    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $rejected->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('subject_id');

    expect(Booking::query()->count())->toBe(0);
});

it('reserves the chosen slot as a pending payment hold with the commission snapshot', function () {
    Notification::fake();

    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $scenario['lesson']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
            'learner_name' => 'Riya Sharma',
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->student_id)->toBe($scenario['student']->id)
        ->and($booking->teacher_profile_id)->toBe($scenario['teacher']->id)
        ->and($booking->subject_id)->toBe($scenario['subject']->id)
        ->and($booking->lesson_id)->toBe($scenario['lesson']->id)
        ->and($booking->price_minor)->toBe(60000)
        ->and($booking->commission_percent)->toBe(15)
        ->and($booking->platform_fee_minor)->toBe(9000)
        ->and($booking->teacher_payout_minor)->toBe(51000)
        ->and($booking->currency)->toBe('LKR')
        ->and($booking->learner_name)->toBe('Riya Sharma')
        ->and($booking->learner_grade_id)->toBe(gradeId(11))
        ->and($booking->starts_at->timestamp)->toBe($scenario['slot']->timestamp)
        ->and($booking->ends_at->timestamp)->toBe($scenario['slot']->addHour()->timestamp)
        ->and($booking->expires_at->lessThanOrEqualTo(now()->addMinutes(31)))->toBeTrue();

    expect(app(SlotService::class)->blockedRanges($scenario['teacher'], $booking->starts_at, $booking->ends_at))
        ->not->toBeEmpty();

    Notification::assertSentTo($scenario['teacherUser'], BookingRequestReceived::class);
});

it('prices shorter lessons pro rata', function () {
    $scenario = directBookingScenario(durationMinutes: 30);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 30,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->price_minor)->toBe(30000)
        ->and($booking->platform_fee_minor)->toBe(4500)
        ->and($booking->teacher_payout_minor)->toBe(25500)
        ->and($booking->durationMinutes())->toBe(30)
        ->and($booking->learner_name)->toBe($scenario['student']->name)
        ->and($booking->learner_grade_id)->toBe($scenario['student']->studentProfile->grade_id);
});

it('uses the per subject rate override when the teacher has one', function () {
    $scenario = directBookingScenario();

    $scenario['teacher']->subjects()->updateExistingPivot($scenario['subject']->id, [
        'rate_per_hour_minor' => 90000,
    ]);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect(Booking::query()->firstOrFail()->price_minor)->toBe(90000);
});

it('rejects a subject the teacher does not offer', function () {
    $scenario = directBookingScenario();
    $other = Subject::factory()->create(['name' => 'Chemistry', 'slug' => 'chemistry']);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $other->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('subject_id');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a lesson that belongs to another subject', function () {
    $scenario = directBookingScenario();
    $foreignLesson = Lesson::factory()->create([
        'subject_id' => Subject::factory()->create(['name' => 'Physics', 'slug' => 'physics'])->id,
        'name' => 'Optics',
        'slug' => 'optics',
    ]);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $foreignLesson->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('lesson_id');

    expect(Booking::query()->count())->toBe(0);
});

it('keeps the booking page pinned to the registered grade even with a grade parameter', function () {
    $scenario = directBookingScenario();

    $otherGrade = Grade::factory()->create(['education_level_id' => $scenario['subject']->education_level_id]);
    $otherLesson = Lesson::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'grade_id' => $otherGrade->id,
        'name' => 'Trigonometry',
        'slug' => 'trigonometry',
    ]);
    $scenario['teacher']->lessons()->attach($otherLesson->id);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee('Algebra')
        ->assertDontSee('Trigonometry')
        ->assertSee('Grade 11')
        ->assertDontSee('name="learner_grade_id"', false);

    // The learner grade always comes from the learning profile.
    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', [
            'teacherProfile' => $scenario['teacher'],
            'learner_grade_id' => $otherGrade->id,
        ]))
        ->assertOk()
        ->assertSee('Algebra')
        ->assertDontSee('Trigonometry');
});

it('rejects a lesson that is not for the learner grade', function () {
    $scenario = directBookingScenario();

    $otherGrade = Grade::factory()->create(['education_level_id' => $scenario['subject']->education_level_id]);
    $otherLesson = Lesson::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'grade_id' => $otherGrade->id,
        'name' => 'Trigonometry',
        'slug' => 'trigonometry',
    ]);
    $scenario['teacher']->lessons()->attach($otherLesson->id);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $otherLesson->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('lesson_id');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a time that is not one of the open slots', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['day']->setTime(23, 0)->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a slot that has already started', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => CarbonImmutable::now('UTC')->subHour()->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a lesson length the teacher does not offer', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 75,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('duration');

    expect(Booking::query()->count())->toBe(0);
});

it('sends a student to the payment page after reserving', function () {
    $scenario = directBookingScenario();

    $response = $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ]);

    $booking = Booking::query()->firstOrFail();

    $response->assertRedirect(route('student.bookings.show', $booking));

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Your slot is held');

    // …and the pay button opens a provider order on the checkout page.
    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect(route('student.payments.checkout', $booking->payments()->firstOrFail()));

    $this->actingAs($scenario['student'])
        ->get(route('student.payments.checkout', $booking->payments()->firstOrFail()))
        ->assertOk()
        ->assertSee('Checkout')
        ->assertSee('Demo gateway');
});

/**
 * The stepper is rendered by resources/js/student-booking-create, so the Blade
 * view only has to hand the Vue app a correct config island (see
 * `bookingCreateConfig()` in tests/Pest.php). These tests pin that contract down:
 * the scoped options, the slots, the learner block and the order summary.
 */
it('hands the stepper island the scoped options, the slots and the order summary', function () {
    $scenario = directBookingScenario();

    $config = bookingCreateConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.create', $scenario['teacher']))
            ->assertOk()
    );

    expect($config['theme']['preset'])->toBe('classic')
        ->and($config['urls']['filter'])->toBe(route('student.bookings.create', $scenario['teacher']))
        ->and($config['urls']['store'])->toBe(route('student.bookings.store', $scenario['teacher']))
        ->and($config['selection']['subjectId'])->toBe((string) $scenario['subject']->id)
        ->and($config['selection']['lessonId'])->toBe('')
        ->and($config['selection']['duration'])->toBe('60')
        ->and($config['learner']['gradeLabel'])->toBe('Grade 11')
        ->and($config['copy']['reserve'])->toBe('Reserve this slot')
        ->and($config['copy']['bookingFee'])->toBe('Booking fee');

    // Only the grade-scoped subject and lesson are on offer.
    expect(collect($config['subjects'])->pluck('label')->all())->toBe(['Mathematics'])
        ->and($config['lessons'])->toHaveCount(1)
        ->and($config['lessons'][0]['label'])->toContain('Algebra');

    // The open slot travels as an instant plus the label the student clicked.
    $slotValues = collect($config['slots'])
        ->flatMap(fn (array $group) => collect($group['slots'])->pluck('value'))
        ->all();

    expect($slotValues)->toContain($scenario['slot']->toIso8601String())
        ->and($config['slots'][0]['label'])->not->toBeEmpty()
        ->and($config['slots'][0]['slots'][0]['label'])->toMatch('/^\d{2}:\d{2}$/')
        ->and($config['slotsNotice'])->toBeNull();

    // 60 minutes of the 600.00/hr rate, plus the booking fee on top.
    $fee = app(BookingFeeService::class)->quote();

    expect($config['summary']['teacher'])->toBe($scenario['teacherUser']->name)
        ->and($config['summary']['subject'])->toBe('Mathematics')
        ->and($config['summary']['rate'])->toBe(platform_settings()->formatMinor(60000).'/hour')
        ->and($config['summary']['lessonPrice'])->toBe(platform_settings()->formatMinor(60000))
        ->and($config['summary']['total'])->toBe(platform_settings()->formatMinor(60000 + $fee['net_minor']))
        ->and($config['summary']['includesFee'])->toBe('includes the '.platform_settings()->formatMinor($fee['net_minor']).' booking fee');
});

it('echoes the chosen lesson and length back into the stepper island', function () {
    $scenario = directBookingScenario();

    $config = bookingCreateConfig(
        $this->actingAs($scenario['student'])
            ->get(route('student.bookings.create', [
                'teacherProfile' => $scenario['teacher'],
                'subject_id' => $scenario['subject']->id,
                'lesson_id' => $scenario['lesson']->id,
                'duration' => 30,
            ]))
            ->assertOk()
    );

    expect($config['selection']['lessonId'])->toBe((string) $scenario['lesson']->id)
        ->and($config['selection']['duration'])->toBe('30')
        ->and($config['summary']['length'])->toBe('30 minutes')
        ->and($config['summary']['lessonPrice'])->toBe(platform_settings()->formatMinor(30000));
});
