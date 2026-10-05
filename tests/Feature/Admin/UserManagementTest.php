<?php

use App\Enums\VerificationStatus;
use App\Models\ActivityLog;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\AdminNotice;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

it('lists and filters the people on the platform', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create(['name' => 'Nisha Kapoor']);
    User::factory()->teacher()->create(['name' => 'Vikram Sen']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSee('Nisha Kapoor')
        ->assertSee('Vikram Sen');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['role' => User::ROLE_STUDENT]))
        ->assertOk()
        ->assertSee('Nisha Kapoor')
        ->assertDontSee('Vikram Sen');
});

it('opens a user with their lessons, payments and reviews', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();

    $this->actingAs($admin)
        ->get(route('admin.users.show', $scenario['student']))
        ->assertOk()
        ->assertSee($scenario['student']->email)
        ->assertSee('Recent lessons')
        ->assertSee('Recent payments');

    $this->actingAs($admin)
        ->get(route('admin.users.show', $scenario['teacherUser']))
        ->assertOk()
        ->assertSee('Balances')
        ->assertSee('Verification');
});

it('suspends an account with a reason and blocks the next login', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.suspend', $student), ['reason' => 'Chargeback abuse'])
        ->assertRedirect();

    expect($student->refresh()->isSuspended())->toBeTrue()
        ->and($student->suspension_reason)->toBe('Chargeback abuse');

    Auth::logout();

    $this->post('/login', ['email' => $student->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('kicks a suspended user out of an open session', function () {
    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)->get(route('student.bookings.index'))->assertOk();

    $student->suspend('Fraud review');

    $this->get(route('student.bookings.index'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('reactivates a suspended account', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();
    $student->suspend('Mistake');

    $this->actingAs($admin)
        ->post(route('admin.users.reactivate', $student))
        ->assertRedirect();

    expect($student->refresh()->isSuspended())->toBeFalse();

    Auth::logout();

    $this->post('/login', ['email' => $student->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($student);
});

it('sends a teacher back through verification', function () {
    $admin = User::factory()->admin()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $this->actingAs($admin)
        ->post(route('admin.users.reverify', $teacherUser), ['notes' => 'Degree certificate is unreadable'])
        ->assertRedirect();

    expect($teacher->refresh()->verification_status)->toBe(VerificationStatus::Rejected)
        ->and($teacher->verification_notes)->toBe('Degree certificate is unreadable')
        ->and($teacher->verified_at)->toBeNull();

    expect($teacherUser->notifications()->count())->toBe(1);
});

it('resends the latest notification to a user', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    Notification::send($student, new AdminNotice('Hello', 'Body copy'));

    expect($student->notifications()->count())->toBe(1);

    $this->actingAs($admin)
        ->post(route('admin.users.resend-notification', $student))
        ->assertRedirect();

    expect($student->notifications()->count())->toBe(2);
});

it('audit logs each people action', function () {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    $this->actingAs($admin)->post(route('admin.users.suspend', $student), ['reason' => 'Spam']);
    $this->actingAs($admin)->post(route('admin.users.reactivate', $student));

    $entries = ActivityLog::query()->orderBy('id')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->action)->toBe('admin.users.suspend')
        ->and($entries[0]->description)->toContain('Suspended')
        ->and($entries[0]->user_id)->toBe($admin->id)
        ->and($entries[1]->description)->toContain('Reactivated');
});
