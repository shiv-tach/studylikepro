<?php

use App\Enums\BookingStatus;
use Illuminate\Testing\TestResponse;

/**
 * The dashboard is rendered by resources/js/student-dashboard, so the Blade view
 * only has to hand the Vue app a correct config island. These tests pin that
 * contract down: the figures, the lesson cards and the route map.
 */
function dashboardConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-dashboard-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

test('the dashboard island carries the figures and the route map the Vue app renders', function () {
    $scenario = paidBookingScenario();

    $config = dashboardConfig(
        $this->actingAs($scenario['student'])->get(route('student.dashboard'))->assertOk()
    );

    expect($config['urls']['teachers'])->toBe(route('student.teachers.index'));
    expect($config['urls']['ask'])->toBe(route('student.requests.create'));
    expect($config['theme']['preset'])->toBe('classic');
    expect($config['stats']['upcomingLessons'])->toBe(1);
    expect(collect($config['quickActions'])->pluck('urlKey')->all())
        ->toEqualCanonicalizing(['ask', 'requests', 'bookings', 'profile', 'theme', 'subjects', 'teachers']);
});

test('the next lesson card is handed the lesson, the teacher and its links', function () {
    $scenario = paidBookingScenario(hoursAhead: 48);

    $config = dashboardConfig(
        $this->actingAs($scenario['student'])->get(route('student.dashboard'))->assertOk()
    );

    $lesson = $config['nextLesson'];

    expect($lesson)->not->toBeNull();
    expect($lesson['status'])->toBe(BookingStatus::Confirmed->value);
    expect($lesson['teacher'])->toBe($scenario['teacherUser']->name);
    expect($lesson['durationMinutes'])->toBe(60);
    expect($lesson['url'])->toBe(route('student.bookings.show', $scenario['booking']));
    expect($lesson['joinUrl'])->toBeNull();
    expect($config['awaitingReview'])->toBeNull();
});

test('a finished lesson without a review is nudged back to the student', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->update([
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'completed_at' => now()->subDay(),
    ]);

    $config = dashboardConfig(
        $this->actingAs($scenario['student'])->get(route('student.dashboard'))->assertOk()
    );

    expect($config['nextLesson'])->toBeNull();
    expect($config['awaitingReview']['reviewUrl'])->toBe(route('student.reviews.show', $scenario['booking']));
    expect($config['stats']['completedLessons'])->toBe(1);
    expect($config['stats']['reviewedLessons'])->toBe(0);
});
