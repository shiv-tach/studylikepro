<?php

use App\Models\User;

test('guests are redirected from role dashboards to login', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with([
    '/student/dashboard',
    '/teacher/dashboard',
    '/admin/dashboard',
]);

test('students can access the student dashboard', function () {
    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.dashboard'))
        ->assertOk();
});

test('teachers can access the teacher dashboard', function () {
    $this->actingAs(User::factory()->teacher()->onboarded()->create())
        ->get(route('teacher.dashboard'))
        ->assertOk();
});

test('admins can access the admin dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('teachers cannot access the student dashboard', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('student.dashboard'))
        ->assertForbidden();
});

test('admins cannot access the student dashboard', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('student.dashboard'))
        ->assertForbidden();
});

test('students cannot access the teacher dashboard', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('teacher.dashboard'))
        ->assertForbidden();
});

test('students cannot access the admin dashboard', function () {
    $this->actingAs(User::factory()->student()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('teachers cannot access the admin dashboard', function () {
    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('the dashboard route redirects each role to its home', function (string $role, string $routeName) {
    $this->actingAs(User::factory()->{$role}()->create())
        ->get('/dashboard')
        ->assertRedirect(route($routeName));
})->with([
    ['student', 'student.dashboard'],
    ['teacher', 'teacher.dashboard'],
    ['admin', 'admin.dashboard'],
]);

test('users without a role are redirected home from the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertRedirect(route('home'));
});
