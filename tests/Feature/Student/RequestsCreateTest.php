<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * The question form is rendered by resources/js/student-requests, so the Blade
 * view only has to hand the Vue app a correct config island. These tests pin
 * that contract down: the copy, the limits, the old input, the route map and
 * the validation messages the field components render.
 */
function requestFormConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-request-form-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

test('the form island carries the limits, the route map and one blank window', function () {
    $student = User::factory()->student()->onboarded()->create();

    $config = requestFormConfig(
        $this->actingAs($student)->get(route('student.requests.create'))->assertOk()
    );

    expect($config['urls']['store'])->toBe(route('student.requests.store'));
    expect($config['urls']['profile'])->toBe(route('student.profile'));
    expect($config['maxAttachments'])->toBe((int) config('studylikepro.requests.max_attachments'));
    expect($config['maxWindows'])->toBe(5);
    expect($config['minWindowDate'])->toBe(now()->toDateString());
    expect($config['acceptedImageTypes'])->toBe('image/jpeg,image/png,image/webp');
    expect($config['errors'])->toBe([]);
    expect($config['values']['description'])->toBe('');
    expect($config['values']['windows'])->toHaveCount(1);
    expect($config['values']['windows'][0]['from'])->toBe('18:00');
    expect($config['theme']['preset'])->toBe('classic');
});

test('the form island locks the learner grade to the learning profile', function () {
    $student = User::factory()->student()->onboarded()->create();
    $grade = $student->studentProfile->grade;

    $config = requestFormConfig(
        $this->actingAs($student)->get(route('student.requests.create'))->assertOk()
    );

    expect($config['learnerGrade'])->toBe($grade->label);

    // The grade is display-only: nothing on the page posts a grade_id back.
    $this->actingAs($student)
        ->get(route('student.requests.create'))
        ->assertOk()
        ->assertSee($grade->label)
        ->assertDontSee('name="grade_id"', false);
});

test('a bounced submission hands the form its old input, rows and field messages', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->from(route('student.requests.create'))
        ->post(route('student.requests.store'), [
            'description' => 'I keep losing marks on quadratic factorising and my board exam is next month.',
            'budget' => '12.50',
            'windows' => [
                ['date' => now()->addDay()->toDateString(), 'from' => '20:00', 'to' => '18:00'],
                ['date' => now()->addDays(2)->toDateString(), 'from' => '09:00', 'to' => '10:00'],
            ],
        ])
        ->assertRedirect(route('student.requests.create'))
        ->assertSessionHasErrors(['windows.0.to']);

    $config = requestFormConfig(
        $this->actingAs($student)->get(route('student.requests.create'))->assertOk()
    );

    expect($config['values']['description'])->toBe('I keep losing marks on quadratic factorising and my board exam is next month.');
    expect($config['values']['budget'])->toBe('12.50');
    // The two rows the student typed come back, in order, instead of one blank row.
    expect($config['values']['windows'])->toHaveCount(2);
    expect($config['values']['windows'][1]['from'])->toBe('09:00');

    // Messages are keyed by the field that failed so each card can show its own.
    expect($config['errors'])->toHaveKey('windows.0.to');
    expect($config['errors'])->not->toHaveKey('windows.1.to');
    expect($config['errors']['windows.0.to'][0])->toContain('end time');
});

test('the form island names the timezone the windows are typed in', function () {
    $student = User::factory()->student()->onboarded()->create();

    $config = requestFormConfig(
        $this->actingAs($student)->get(route('student.requests.create'))->assertOk()
    );

    expect($config['timezone'])->toBe($student->studentProfile->timezone);
    expect($config['copy']['windowsHint'])->toContain($config['timezone']);
});
