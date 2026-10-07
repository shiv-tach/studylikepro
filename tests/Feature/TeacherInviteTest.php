<?php

use App\Models\TeacherInvite;
use App\Models\User;

test('admin can view the invites page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.invites.index'))
        ->assertOk();
});

test('admin can create an invite and is shown the link once', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->post(route('admin.invites.store'), ['expires_in_days' => 7])
        ->assertRedirect(route('admin.invites.index'));

    $response->assertSessionHas('invite_token');

    expect(TeacherInvite::count())->toBe(1);

    $invite = TeacherInvite::firstOrFail();
    expect($invite->created_by)->toBe($admin->id);
    expect($invite->isUsable())->toBeTrue();
});

test('admin can revoke an unused invite', function () {
    $admin = User::factory()->admin()->create();
    [$invite] = makeTeacherInvite();

    $this->actingAs($admin)
        ->delete(route('admin.invites.revoke', $invite))
        ->assertRedirect(route('admin.invites.index'));

    expect(TeacherInvite::find($invite->id))->toBeNull();
});

test('non-admin cannot manage invites', function () {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('admin.invites.index'))->assertForbidden();
    $this->actingAs($student)->post(route('admin.invites.store'), ['expires_in_days' => 7])->assertForbidden();
});

test('a valid invite link renders the teacher sign-up form', function () {
    [, $token] = makeTeacherInvite();

    $this->get('/register?invite='.$token)
        ->assertOk()
        ->assertSee('been invited to join as a teacher', false);
});

test('an invalid invite link shows an error', function () {
    $this->get('/register?invite=does-not-exist')
        ->assertOk()
        ->assertSee('onboarding link is invalid', false);
});
