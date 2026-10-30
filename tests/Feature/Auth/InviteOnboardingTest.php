<?php

use App\Enums\ApprovalStatus;
use App\Models\EducationLevel;
use App\Models\Subject;
use App\Models\TeacherInvite;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\Sms\FakeSmsGateway;
use App\Support\PendingRegistration;
use App\Support\PendingTeacherSignup;
use App\Support\TeacherSurvey;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A teacher onboarding link opens the invite survey as a guest: nine questions
 * are answered first and the account — carrying those answers — is created only
 * when the SMS code is confirmed.
 */

beforeEach(function () {
    $olId = EducationLevel::query()->where('key', 'ol')->value('id');
    $alId = EducationLevel::query()->where('key', 'al')->value('id');

    $this->olSubject = Subject::factory()->create([
        'education_level_id' => $olId,
        'name' => 'Mathematics',
        'slug' => 'survey-mathematics',
    ]);

    $this->secondOlSubject = Subject::factory()->create([
        'education_level_id' => $olId,
        'name' => 'Science',
        'slug' => 'survey-science',
    ]);

    $this->alSubject = Subject::factory()->create([
        'education_level_id' => $alId,
        'name' => 'Physics',
        'slug' => 'survey-physics',
    ]);
});

test('an invite link opens the teacher survey instead of a sign-up form', function () {
    [, $token] = makeTeacherInvite();

    $this->get('/register?invite='.$token)
        ->assertRedirect(route('join.start'));

    $this->get(route('join.start'))
        ->assertRedirect(route('join.level'));

    expect(User::count())->toBe(0)
        ->and(PendingTeacherSignup::token())->toBe($token);
});

test('an invalid invite link still shows the error on the register page', function () {
    $this->get('/register?invite=does-not-exist')
        ->assertOk()
        ->assertSee('onboarding link is invalid', false);
});

test('a used invite cannot start the survey', function () {
    [$invite, $token] = makeTeacherInvite();
    $invite->forceFill(['consumed_at' => now()])->save();

    $this->get('/register?invite='.$token)
        ->assertOk()
        ->assertSee('onboarding link is invalid', false);
});

test('every survey question needs the invite the visitor arrived with', function () {
    $this->get(route('join.level'))->assertRedirect(route('register'));

    [, $token] = makeTeacherInvite();

    // A token in the session that has since expired sends them back to the
    // entry page with the explanation, not to a bare 403.
    $this->get('/register?invite='.$token);
    TeacherInvite::query()->update(['expires_at' => now()->subDay()]);

    $this->get(route('join.level'))
        ->assertRedirect(route('register', ['invite' => $token]));
});

test('the nine questions are answered before the account exists', function () {
    [, $token] = makeTeacherInvite();

    $this->get('/register?invite='.$token)->assertRedirect(route('join.start'));

    $this->get(route('join.level'))
        ->assertOk()
        ->assertSee('Who are you tutoring for?')
        ->assertSee('name="level"', false);

    $this->put(route('join.level.update'), ['level' => 'ol'])
        ->assertRedirect(route('join.subjects'));

    $this->get(route('join.subjects'))
        ->assertOk()
        ->assertSee('What subjects do you teach?')
        ->assertSee('Mathematics')
        // Only the level that was picked is offered.
        ->assertDontSee('Physics');

    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id, $this->secondOlSubject->id]])
        ->assertRedirect(route('join.languages'));

    $this->put(route('join.languages.update'), ['languages' => ['English', 'Sinhala']])
        ->assertRedirect(route('join.services'));

    $this->put(route('join.services.update'), ['services' => ['lesson', 'paper_marking', 'qna']])
        ->assertRedirect(route('join.experience'));

    $this->get(route('join.experience'))
        ->assertOk()
        ->assertSee('How long have you been teaching?')
        ->assertSee('name="experience_years"', false);

    $this->put(route('join.experience.update'), ['experience_years' => 8])
        ->assertRedirect(route('join.education'));

    $this->get(route('join.education'))
        ->assertOk()
        ->assertSee('Where did you study?')
        ->assertSee('Government university')
        ->assertSee('National College of Education');

    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_PRIVATE_UNIVERSITY])
        ->assertRedirect(route('join.rates'));

    // The rates question only prices the services picked a moment ago.
    $this->get(route('join.rates'))
        ->assertOk()
        ->assertSee('What do you charge?')
        ->assertSee('name="hourly_rate"', false)
        ->assertSee('name="service_rates[paper_marking]"', false)
        ->assertSee('name="service_rates[qna]"', false)
        ->assertDontSee('name="service_rates[lesson]"', false);

    $this->put(route('join.rates.update'), [
        'hourly_rate' => 2500,
        'service_rates' => ['paper_marking' => 1500, 'qna' => 800],
    ])->assertRedirect(route('join.availability'));

    $this->get(route('join.availability'))
        ->assertOk()
        ->assertSee('Which days can you teach?')
        ->assertSee('name="days[]"', false);

    $this->put(route('join.availability.update'), ['days' => [1, 3, 5]])
        ->assertRedirect(route('join.proof'));

    $this->get(route('join.proof'))
        ->assertOk()
        ->assertSee('Are you a government teacher?')
        ->assertSee('name="claims"', false);

    $this->post(route('join.proof.update'), ['claims' => '0'])
        ->assertRedirect(route('join.account'));

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Create your account')
        ->assertSee('name="password"', false)
        ->assertSee('name="agree"', false)
        ->assertSee('Terms and policies accepted');

    // Nothing has been written to `users` yet.
    expect(User::count())->toBe(0)
        ->and(PendingTeacherSignup::isComplete())->toBeTrue()
        ->and(PendingTeacherSignup::step(TeacherSurvey::STEP_SUBJECTS))
        ->toBe([$this->olSubject->id, $this->secondOlSubject->id])
        ->and(PendingTeacherSignup::step(TeacherSurvey::STEP_EXPERIENCE))->toBe(['value' => 8])
        ->and(PendingTeacherSignup::step(TeacherSurvey::STEP_EDUCATION))
        ->toBe(['value' => TeacherSurvey::EDUCATION_PRIVATE_UNIVERSITY])
        ->and(PendingTeacherSignup::step(TeacherSurvey::STEP_AVAILABILITY))->toBe(['days' => [1, 3, 5], 'preset' => null]);
});

test('a question cannot be answered before the one it depends on', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->get(route('join.subjects'))
        ->assertRedirect(route('join.level'))
        ->assertSessionHas('status', 'answer-the-level-question');

    $this->get(route('join.languages'))
        ->assertRedirect(route('join.subjects'));

    $this->get(route('join.experience'))
        ->assertRedirect(route('join.services'));

    $this->get(route('join.education'))
        ->assertRedirect(route('join.experience'));

    $this->get(route('join.rates'))
        ->assertRedirect(route('join.education'));

    $this->get(route('join.availability'))
        ->assertRedirect(route('join.rates'));

    $this->get(route('join.proof'))
        ->assertRedirect(route('join.availability'));

    $this->get(route('join.account'))->assertRedirect(route('join.level'));
});

test('the rates question needs a price for every service that was picked', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson', 'paper_marking']]);
    $this->put(route('join.experience.update'), ['experience_years' => 3]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);

    $this->from(route('join.rates'))
        ->put(route('join.rates.update'), ['hourly_rate' => 2000])
        ->assertSessionHasErrors('service_rates.paper_marking');

    $this->from(route('join.rates'))
        ->put(route('join.rates.update'), ['hourly_rate' => 50])
        ->assertSessionHasErrors('hourly_rate');

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_RATES))->toBeFalse();
});

test('changing the services drops the prices given for the old picks', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson', 'paper_marking']]);
    $this->put(route('join.experience.update'), ['experience_years' => 4]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);
    $this->put(route('join.rates.update'), [
        'hourly_rate' => 2000,
        'service_rates' => ['paper_marking' => 900],
    ]);

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_RATES))->toBeTrue();

    // A different service list cannot keep the price of a service that is gone.
    $this->put(route('join.services.update'), ['services' => ['lesson', 'qna']])
        ->assertRedirect(route('join.experience'));

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_RATES))->toBeFalse();
});

test('the days question needs at least one day', function () {    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson']]);
    $this->put(route('join.experience.update'), ['experience_years' => 0]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_GOVERNMENT_UNIVERSITY]);
    $this->put(route('join.rates.update'), ['hourly_rate' => 2000]);

    $this->from(route('join.availability'))
        ->put(route('join.availability.update'), ['days' => []])
        ->assertSessionHasErrors('days');

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_AVAILABILITY))->toBeFalse();
});

test('the days question offers the same suggested weeks and times as onboarding', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson']]);
    $this->put(route('join.experience.update'), ['experience_years' => 0]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);
    $this->put(route('join.rates.update'), ['hourly_rate' => 2000]);

    $response = $this->get(route('join.availability'));

    $response->assertOk()
        ->assertSee('Suggested weeks')
        ->assertSee('School-friendly')
        ->assertSee('Evenings only')
        ->assertSee('Weekends only')
        // The onboarding presets are shown with the clock times they fill in,
        // not only with their labels.
        ->assertSee('Mon – Fri · 3:00 PM – 9:00 PM')
        ->assertSee('Sat – Sun · 8:00 AM – 8:00 PM')
        ->assertSee('name="preset"', false)
        ->assertSee('name="days[]"', false)
        // The day grid is only for "My own days", and the hint says the answer
        // can be changed later.
        ->assertSee('Days you can teach')
        ->assertSee('You can change your availability any time after you register.');

    // The Vue island carries the rule the grid follows: a picked week replaces
    // the days, and every day card shows the hours that week would use.
    $config = joinSurveyConfig($response);

    expect($config['values']['preset'])->toBeNull()
        ->and($config['options']['presetDays']['school_friendly'])->toBe([1, 2, 3, 4, 5, 6, 0])
        ->and($config['options']['dayTimes'][1])->toBe('3:00 PM – 9:00 PM')
        ->and($config['options']['presetDays'])->toHaveKeys(['school_friendly', 'evenings', 'weekends']);
});

test('the days question only accepts a suggested week it knows', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson']]);
    $this->put(route('join.experience.update'), ['experience_years' => 0]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);
    $this->put(route('join.rates.update'), ['hourly_rate' => 2000]);

    $this->from(route('join.availability'))
        ->put(route('join.availability.update'), ['days' => [1], 'preset' => 'midnight-oil'])
        ->assertSessionHasErrors('preset');

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_AVAILABILITY))->toBeFalse();
});

test('the suggested week picked in the survey becomes the teacher week', function () {
    [, $token] = makeTeacherInvite();

    $account = joinThroughInvite($token, [], [
        'availability' => ['days' => [1, 3], 'preset' => 'evenings'],
    ]);

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()]);

    $slots = User::where('email', $account['email'])->firstOrFail()
        ->teacherProfile
        ->availabilitySlots()
        ->orderBy('day_of_week')
        ->get();

    // "Evenings only" is 5:00 PM – 9:00 PM every day, so the two chosen days
    // open on those hours instead of the recommended school-friendly ones.
    expect($slots->pluck('day_of_week')->all())->toBe([1, 3])
        ->and($slots->pluck('start_minute')->all())->toBe([1020, 1020])
        ->and($slots->pluck('end_minute')->all())->toBe([1260, 1260]);
});

test('the subject question only accepts subjects of the level that was picked', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);

    $this->from(route('join.subjects'))
        ->put(route('join.subjects.update'), ['subjects' => [$this->alSubject->id]])
        ->assertSessionHasErrors('subjects.0');

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_SUBJECTS))->toBeFalse();
});

test('choosing both levels offers the subjects of both', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => TeacherSurvey::LEVEL_BOTH]);

    $this->get(route('join.subjects'))
        ->assertOk()
        ->assertSee('Mathematics')
        ->assertSee('Physics');

    $this->put(route('join.subjects.update'), [
        'subjects' => [$this->olSubject->id, $this->alSubject->id],
    ])->assertRedirect(route('join.languages'));
});

test('changing the level drops subject picks it no longer offers', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);

    // O/L only -> A/L only: the O/L pick cannot survive.
    $this->put(route('join.level.update'), ['level' => 'al'])
        ->assertRedirect(route('join.subjects'));

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_SUBJECTS))->toBeFalse();
});

test('languages and services need at least one answer', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);

    $this->from(route('join.languages'))
        ->put(route('join.languages.update'), ['languages' => []])
        ->assertSessionHasErrors('languages');

    $this->put(route('join.languages.update'), ['languages' => ['English']]);

    $this->from(route('join.services'))
        ->put(route('join.services.update'), ['services' => []])
        ->assertSessionHasErrors('services');

    expect(PendingTeacherSignup::hasStep(TeacherSurvey::STEP_SERVICES))->toBeFalse();
});

test('the proof question is optional but needs a file when a teacher claims a government post', function () {
    Storage::fake('local');

    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson']]);
    $this->put(route('join.experience.update'), ['experience_years' => 5]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);
    $this->put(route('join.rates.update'), ['hourly_rate' => 2500]);
    $this->put(route('join.availability.update'), ['days' => [2, 4]]);

    $this->from(route('join.proof'))
        ->post(route('join.proof.update'), ['claims' => '1'])
        ->assertSessionHasErrors('document');

    $this->post(route('join.proof.update'), [
        'claims' => '1',
        'document' => UploadedFile::fake()->create('appointment-letter.pdf', 120, 'application/pdf'),
    ])->assertRedirect(route('join.account'));

    $proof = PendingTeacherSignup::step(TeacherSurvey::STEP_PROOF);

    expect($proof['claimed'])->toBeTrue()
        ->and($proof['original_name'])->toBe('appointment-letter.pdf')
        ->and($proof['path'])->toStartWith('verification-documents/pending/')
        ->and(Storage::disk('local')->exists($proof['path']))->toBeTrue();
});

test('the account is created only once the texted code is confirmed', function () {
    Storage::fake('local');

    [, $token] = makeTeacherInvite();
    $invite = TeacherInvite::firstOrFail();

    $account = joinThroughInvite($token, [], [
        'level' => ['level' => 'ol'],
        'subjects' => ['subjects' => [$this->olSubject->id]],
        'languages' => ['languages' => ['English', 'Sinhala']],
        'services' => ['services' => ['lesson', 'paper_marking']],
        'proof' => [
            'claims' => '1',
            'document' => UploadedFile::fake()->create('staff-id.pdf', 120, 'application/pdf'),
        ],
    ]);

    // The details wait for the passcode; no account exists yet.
    $this->assertGuest();

    expect(User::count())->toBe(0)
        ->and(PendingRegistration::get())->not->toBeNull()
        ->and(PendingRegistration::get()['onboarding'])->not->toBeNull();

    $messages = FakeSmsGateway::messages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0]['to'])->toBe('0771234567');

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('teacher.approval.status'));

    $this->assertAuthenticated();

    $user = User::where('email', $account['email'])->firstOrFail();
    $profile = $user->teacherProfile;

    expect($user->hasRole(User::ROLE_TEACHER))->toBeTrue()
        ->and($user->mobile)->toBe('0771234567')
        ->and($user->mobile_verified_at)->not->toBeNull()
        // The survey answers are now the profile.
        ->and($profile)->not->toBeNull()
        ->and($profile->languages)->toBe(['English', 'Sinhala'])
        ->and($profile->tutoring_levels)->toBe(['ol'])
        ->and($profile->service_interests)->toBe(['lesson', 'paper_marking'])
        ->and($profile->serviceInterestTypes())->toHaveCount(2)
        // The survey's profile answers are already on the profile.
        ->and($profile->experience_years)->toBe(5)
        ->and($profile->education)->toBe('Government university')
        ->and($profile->hourly_rate_minor)->toBe(250000)
        ->and($profile->service_rates)->toBe(['paper_marking' => 150000])
        ->and($profile->availabilitySlots()->orderBy('day_of_week')->pluck('day_of_week')->all())->toBe([1, 3, 5])
        // The survey is the profile form now, so the profile counts as saved
        // and the application is already with our team.
        ->and($profile->about_completed_at)->not->toBeNull()
        ->and($profile->preferences_completed_at)->not->toBeNull()
        ->and($profile->completed_at)->not->toBeNull()
        ->and($profile->isComplete())->toBeTrue()
        ->and($profile->verification_status->value)->toBe('pending')
        ->and($profile->submitted_at)->not->toBeNull()
        ->and($profile->agreement_accepted_at)->not->toBeNull()
        // Onboarding is finished; only the admin decision is left.
        ->and($user->hasCompletedOnboarding())->toBeTrue();

    // The subject is a pending pivot, approved with the application.
    $subject = $profile->subjects()->firstOrFail();
    expect((int) $subject->id)->toBe($this->olSubject->id)
        ->and($subject->pivot->status)->toBe(ApprovalStatus::Pending);

    // The government proof was moved out of the pending folder.
    $document = $profile->documents()->firstOrFail();
    expect($document->type)->toBe('government_proof')
        ->and($document->original_name)->toBe('staff-id.pdf')
        ->and($document->path)->toBe('verification-documents/'.$profile->id.'/'.basename($document->path))
        ->and(Storage::disk('local')->exists($document->path))->toBeTrue()
        ->and($profile->hasGovernmentProof())->toBeTrue()
        // The claim alone never shows the badge.
        ->and($profile->isGovernmentTeacher())->toBeFalse();

    // The single-use link is spent.
    expect($invite->fresh()->consumed_at)->not->toBeNull()
        ->and(PendingTeacherSignup::get())->toBeNull();
});

test('a teacher who is not a government teacher gets no proof document', function () {
    Storage::fake('local');

    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()]);

    $profile = TeacherProfile::firstOrFail();

    expect($profile->documents()->count())->toBe(0)
        ->and($profile->hasGovernmentProof())->toBeFalse();
});

test('an admin confirms the government teacher badge', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()]);

    $profile = TeacherProfile::firstOrFail();

    expect($profile->isGovernmentTeacher())->toBeFalse();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.verifications.government', $profile), ['government_verified' => 1])
        ->assertRedirect(route('admin.verifications.show', $profile));

    expect($profile->fresh()->isGovernmentTeacher())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.verifications.government', $profile), ['government_verified' => 0]);

    expect($profile->fresh()->isGovernmentTeacher())->toBeFalse();
});

test('the code step can be reopened on the same url', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Confirm your mobile number')
        ->assertSee('077 ••• 45 67')
        ->assertSee('name="code"', false);
});

test('changing the details drops the passcode but keeps the answers', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->post(route('join.account.edit'))
        ->assertRedirect(route('join.account'));

    expect(PendingRegistration::get())->toBeNull()
        ->and(PendingTeacherSignup::isComplete())->toBeTrue();
});

test('an email that is already registered is caught before the code is sent', function () {
    User::factory()->student()->create(['email' => 'taken@example.com']);

    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson']]);
    $this->put(route('join.experience.update'), ['experience_years' => 5]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);
    $this->put(route('join.rates.update'), ['hourly_rate' => 2500]);
    $this->put(route('join.availability.update'), ['days' => [2, 4]]);
    $this->post(route('join.proof.update'), ['claims' => '0']);

    $this->from(route('join.account'))
        ->post(route('join.account.store'), [
            'first_name' => 'Amara',
            'last_name' => 'Silva',
            'email' => 'taken@example.com',
            'mobile' => '077 123 4567',
            'password' => 'password',
            'password_confirmation' => 'password',
            'agree' => '1',
        ])
        ->assertSessionHasErrors('email');

    expect(FakeSmsGateway::messages())->toBeEmpty()
        ->and(User::count())->toBe(1);
});

test('the account is not registered until the terms are accepted', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $sentBefore = count(FakeSmsGateway::messages());

    $this->from(route('join.account'))
        ->post(route('join.account.store'), [
            'first_name' => 'Amara',
            'last_name' => 'Silva',
            'email' => 'amara@example.com',
            'mobile' => '077 123 4567',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('agree');

    // No new passcode is texted while the terms are unticked.
    expect(FakeSmsGateway::messages())->toHaveCount($sentBefore);
});

test('the account is created immediately when mobile verification is switched off', function () {
    platform_settings()->set('sms_otp_enabled', '0');

    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'al']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->alSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['Tamil']]);
    $this->put(route('join.services.update'), ['services' => ['paper_discussion']]);
    $this->put(route('join.experience.update'), ['experience_years' => 12]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_NATIONAL_COLLEGE]);
    $this->put(route('join.rates.update'), [
        'hourly_rate' => 3000,
        'service_rates' => ['paper_discussion' => 1200],
    ]);
    $this->put(route('join.availability.update'), ['days' => [0, 6]]);
    $this->post(route('join.proof.update'), ['claims' => '0']);

    $this->post(route('join.account.store'), [
        'first_name' => 'Amara',
        'last_name' => 'Silva',
        'email' => 'amara@example.com',
        'mobile' => '077 123 4567',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree' => '1',
    ])->assertRedirect(route('teacher.approval.status'));

    $this->assertAuthenticated();

    $user = User::where('email', 'amara@example.com')->firstOrFail();

    expect($user->hasRole(User::ROLE_TEACHER))->toBeTrue()
        ->and($user->teacherProfile?->tutoring_levels)->toBe(['al'])
        ->and($user->teacherProfile?->languages)->toBe(['Tamil'])
        ->and($user->teacherProfile?->service_interests)->toBe(['paper_discussion'])
        ->and($user->teacherProfile?->subjects()->count())->toBe(1);

    // No passcode was needed, but the operations team is told about the new
    // application exactly like a manual submission would tell them.
    $messages = FakeSmsGateway::messages();

    expect($messages)->toHaveCount(1)
        ->and($messages[0]['message'])->toBe('New teacher verification: Amara.');
});

test('the survey shows what has been answered and a sticky way forward', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->get(route('join.level'))
        ->assertOk()
        // The guest header can switch the theme of the invite link.
        ->assertSee('x-data="themeToggle"', false)
        // Phones submit the question through the fixed bottom bar.
        ->assertSee('form="join-question-form"', false)
        // Nothing is answered yet, and the recap says so rather than hiding.
        ->assertSee('Not answered yet');

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);

    $this->get(route('join.languages'))
        ->assertOk()
        ->assertSee('Question 3 of 9')
        // The recap repeats the answers already saved.
        ->assertSee('Exam level')
        ->assertSee('O/L')
        ->assertSee('Mathematics')
        ->assertSee('You can change any answer before you create your account.');
});

test('the last step recaps every answer and submits from the phone bar', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English', 'Sinhala']]);
    $this->put(route('join.services.update'), ['services' => ['lesson', 'paper_marking']]);
    $this->put(route('join.experience.update'), ['experience_years' => 5]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_GOVERNMENT_UNIVERSITY]);
    $this->put(route('join.rates.update'), [
        'hourly_rate' => 2500,
        'service_rates' => ['paper_marking' => 1500],
    ]);
    $this->put(route('join.availability.update'), ['days' => [1, 3, 5]]);
    $this->post(route('join.proof.update'), ['claims' => '0']);

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Last step')
        ->assertSee('form="join-account-form"', false)
        // The recap carries the answers the wizard is about to receive.
        ->assertSee('English, Sinhala')
        ->assertSee(platform_settings()->formatMinor(250000))
        ->assertSee('Monday, Wednesday, Friday');

    // The code state uses its own form, so the phone bar keeps working.
    $this->post(route('join.account.store'), [
        'first_name' => 'Amara',
        'last_name' => 'Silva',
        'email' => 'amara@example.com',
        'mobile' => '077 123 4567',
        'password' => 'password',
        'password_confirmation' => 'password',
        'agree' => '1',
    ]);

    $this->get(route('join.account'))
        ->assertOk()
        ->assertSee('Almost done')
        ->assertSee('form="join-verify-form"', false);
});

test('finishing the survey opens the approval status page with a celebration and the saved answers', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    $this->post(route('join.account.verify'), ['code' => FakeSmsGateway::lastCode()])
        ->assertRedirect(route('teacher.approval.status'));

    $this->get(route('teacher.approval.status'))
        ->assertOk()
        ->assertSee('Your account is ready')
        ->assertSee("You're in")
        ->assertSee('English, Sinhala')
        ->assertSee('in review');

    // The celebration is a one-time hand-off, not a permanent banner.
    $this->get(route('teacher.approval.status'))
        ->assertOk()
        ->assertDontSee('Your account is ready');
});

test('every survey screen ships the island its Vue app renders', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    // Question 1: the three level cards come from the seeded catalog.
    $level = joinSurveyConfig($this->get(route('join.level')));

    expect($level['step'])->toBe('level')
        ->and($level['index'])->toBe(1)
        ->and($level['total'])->toBe(9)
        ->and($level['answered'])->toBe(0)
        ->and(array_column($level['options']['levels'], 'value'))->toBe(['ol', 'al', 'both'])
        ->and($level['urls']['submit'])->toBe(route('join.level.update'))
        ->and($level['urls']['back'])->toBeNull()
        // Six rail segments: nine questions, the account step on its own.
        ->and($level['rail'])->toHaveCount(6);

    $this->put(route('join.level.update'), ['level' => 'ol']);

    // Question 2: only the O/L subjects, and the recap says nothing yet.
    $subjects = joinSurveyConfig($this->get(route('join.subjects')));

    expect(collect($subjects['options']['subjectGroups'])->pluck('key')->all())->toBe(['ol'])
        ->and($subjects['options']['subjectGroups'][0]['subjects'])->not->toBeEmpty()
        ->and($subjects['urls']['back'])->toBe(route('join.level'))
        ->and($subjects['summary'][1]['items'][0]['value'])->toBeNull();

    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);

    // The answer comes back on a revisit, and the recap repeats it.
    $replayed = joinSurveyConfig($this->get(route('join.subjects')));

    expect($replayed['values']['subjects'])->toBe([$this->olSubject->id])
        ->and($replayed['answered'])->toBe(2)
        ->and($replayed['summary'][0]['items'][1]['value'])->toBe('Mathematics');
});

test('the subjects question separates both levels and pairs same-named subjects', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $olIct = Subject::factory()->create([
        'education_level_id' => EducationLevel::query()->where('key', 'ol')->value('id'),
        'name' => 'ICT',
        'slug' => 'survey-ol-ict',
    ]);

    $alIct = Subject::factory()->create([
        'education_level_id' => EducationLevel::query()->where('key', 'al')->value('id'),
        'name' => 'ICT',
        'slug' => 'survey-al-ict',
    ]);

    $this->put(route('join.level.update'), ['level' => 'both']);

    $this->get(route('join.subjects'))
        ->assertOk()
        ->assertSee('name="subjects[]"', false);

    $config = joinSurveyConfig($this->get(route('join.subjects')));

    $groups = collect($config['options']['subjectGroups']);

    expect($groups->pluck('key')->all())->toBe(['ol', 'al'])
        // A compact label the tabs and the tile badges can carry.
        ->and(array_column($groups->all(), 'shortName'))->toBe(['O/L', 'A/L'])
        // The picker's copy travels in the island; Vue hardcodes no label.
        ->and($config['questionCopy']['subjectSearch'])->not->toBeEmpty()
        ->and($config['questionCopy']['subjectMissingLevel'])->toContain(':level')
        ->and($config['questionCopy']['subjectTwinHint'])->toContain(':name');

    $olRows = collect($groups[0]['subjects']);
    $alRows = collect($groups[1]['subjects']);

    $olIctRow = $olRows->firstWhere('id', $olIct->id);
    $alIctRow = $alRows->firstWhere('id', $alIct->id);

    expect(array_column($olIctRow['twins'], 'id'))->toBe([$alIct->id])
        ->and(array_column($alIctRow['twins'], 'id'))->toBe([$olIct->id])
        // A name that only exists once has no twin to suggest.
        ->and($olRows->firstWhere('id', $this->olSubject->id)['twins'])->toBe([])
        // Every row knows its own level, so a search across both can badge it.
        ->and($olIctRow['levelName'])->toBe('O/L')
        ->and($alIctRow['levelName'])->toBe('A/L');
});

test('the recap groups subjects by level when both levels are covered', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'both']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id, $this->alSubject->id]]);

    $summary = joinSurveyConfig($this->get(route('join.subjects')))['summary'];

    expect($summary[0]['items'][1]['value'])->toBe('O/L: Mathematics · A/L: Physics');
});

test('the rates island prices only the services the teacher picked', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson', 'paper_marking']]);
    $this->put(route('join.experience.update'), ['experience_years' => 3]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);

    $config = joinSurveyConfig($this->get(route('join.rates')));

    expect(array_column($config['options']['pricedServices'], 'value'))->toBe(['paper_marking'])
        // Lessons are priced by the hourly fee itself.
        ->and($config['limits'])->toMatchArray(['rateMin' => 100, 'rateMax' => 100000])
        ->and($config['currency']['symbol'])->not->toBeEmpty()
        ->and($config['csrfToken'])->not->toBeEmpty();
});

test('a rejected answer reaches Vue as field errors and old input', function () {
    [, $token] = makeTeacherInvite();
    $this->get('/register?invite='.$token);

    $this->put(route('join.level.update'), ['level' => 'ol']);
    $this->put(route('join.subjects.update'), ['subjects' => [$this->olSubject->id]]);
    $this->put(route('join.languages.update'), ['languages' => ['English']]);
    $this->put(route('join.services.update'), ['services' => ['lesson', 'paper_marking']]);
    $this->put(route('join.experience.update'), ['experience_years' => 3]);
    $this->put(route('join.education.update'), ['education' => TeacherSurvey::EDUCATION_OTHER]);

    $this->from(route('join.rates'))
        ->put(route('join.rates.update'), ['hourly_rate' => 2000])
        ->assertSessionHasErrors(['service_rates.paper_marking']);

    $config = joinSurveyConfig($this->get(route('join.rates')));

    expect($config['errorList'])->not->toBeEmpty()
        ->and($config['fieldErrors']['service_rates.paper_marking'])->not->toBeEmpty()
        // What was typed survives the bounce.
        ->and($config['values']['hourly_rate'])->toEqual(2000);
});

test('the account step ships both of its states to Vue', function () {
    [, $token] = makeTeacherInvite();
    joinThroughInvite($token);

    // State 1: the code is out, so the SMS form is the one on screen.
    $pending = joinAccountConfig($this->get(route('join.account')));

    expect($pending['pending'])->toBeTrue()
        ->and($pending['formId'])->toBe('join-verify-form')
        ->and($pending['urls']['verify'])->toBe(route('join.account.verify'))
        ->and($pending['mobile'])->toBe('077 ••• 45 67')
        ->and($pending['length'])->toBeGreaterThan(0)
        ->and($pending['summary'])->toHaveCount(5);

    // "Change my details" drops the passcode and brings the identity form back.
    $this->post(route('join.account.edit'));

    $identity = joinAccountConfig($this->get(route('join.account')));

    expect($identity['pending'])->toBeFalse()
        ->and($identity['formId'])->toBe('join-account-form')
        ->and($identity['urls']['store'])->toBe(route('join.account.store'))
        ->and($identity['otpEnabled'])->toBeTrue();
});
