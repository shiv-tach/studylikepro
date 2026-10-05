<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\BookingTransitionService;

it('filters lesson history by status, subject, teacher and dates', function () {
    $student = User::factory()->student()->onboarded()->create();
    $maths = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $chemistry = Subject::factory()->create(['name' => 'Chemistry', 'slug' => 'chemistry']);
    $teacherA = TeacherProfile::factory()->approved()->create();
    $teacherB = TeacherProfile::factory()->approved()->create();

    $old = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacherA->id,
        'subject_id' => $maths->id,
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDays(30),
        'ends_at' => now()->subDays(30)->addHour(),
    ]);

    $recent = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacherB->id,
        'subject_id' => $chemistry->id,
        'status' => BookingStatus::Cancelled,
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDays(2)->addHour(),
    ]);

    $this->actingAs($student)
        ->get(route('student.bookings.index', ['tab' => 'past', 'status' => 'completed']))
        ->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1 && $bookings->first()->id === $old->id);

    $this->actingAs($student)
        ->get(route('student.bookings.index', ['tab' => 'past', 'subject' => $chemistry->id]))
        ->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1 && $bookings->first()->id === $recent->id);

    $this->actingAs($student)
        ->get(route('student.bookings.index', ['tab' => 'past', 'teacher' => $teacherA->id]))
        ->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1 && $bookings->first()->id === $old->id);

    $this->actingAs($student)
        ->get(route('student.bookings.index', ['tab' => 'past', 'from' => now()->subDays(7)->toDateString()]))
        ->assertOk()
        ->assertViewHas('bookings', fn ($bookings) => $bookings->total() === 1 && $bookings->first()->id === $recent->id);

    // The filter form offers the history's own subjects and teachers.
    $this->actingAs($student)
        ->get(route('student.bookings.index', ['tab' => 'past']))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('Chemistry');
});

it('rejects a date range that runs backwards', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->get(route('student.bookings.index', [
            'tab' => 'past',
            'from' => now()->toDateString(),
            'to' => now()->subDays(3)->toDateString(),
        ]))
        ->assertSessionHasErrors('to');
});

it('prompts for a review on the lesson page after delivery', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    // Before delivery there is no review block at all.
    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertDontSee('Leave a review');

    $this->travelTo($booking->starts_at->addMinutes(10));
    app(BookingTransitionService::class)->complete($booking);
    $booking = $booking->refresh();

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Leave a review')
        ->assertSee(route('student.reviews.show', $booking), false);

    // Once reviewed, the lesson page shows it with an edit link.
    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $booking), ['rating' => 4, 'comment' => 'Clear explanations.']);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Clear explanations.')
        ->assertSee('Edit review')
        ->assertDontSee('Leave a review');

    // The history card flags lessons still waiting for a review.
    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.index', ['tab' => 'past']))
        ->assertOk()
        ->assertDontSee('Review this lesson');

    $other = classroomScenario()['booking'];
    $other->forceFill([
        'student_id' => $scenario['student']->id,
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDays(3),
        'ends_at' => now()->subDays(3)->addHour(),
        'completed_at' => now()->subDays(3),
    ])->save();

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.index', ['tab' => 'past']))
        ->assertOk()
        ->assertSee('Review this lesson');
});

it('counts delivered lessons towards the teacher profile', function () {
    $scenario = paidBookingScenario();

    expect($scenario['teacher']->refresh()->lessons_completed_count)->toBe(0);

    $this->travelTo($scenario['booking']->starts_at->addMinutes(5));
    app(BookingTransitionService::class)->complete($scenario['booking']);

    expect($scenario['teacher']->refresh()->lessons_completed_count)->toBe(1);
});
