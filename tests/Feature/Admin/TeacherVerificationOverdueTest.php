<?php

use App\Models\TeacherProfile;
use App\Models\User;

test('the admin queue flags applications past the review promise', function () {
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 12:00:00'));

    $late = TeacherProfile::factory()->pending()->create([
        'submitted_at' => now()->subWeekdays(4),
    ]);
    TeacherProfile::factory()->pending()->create([
        'submitted_at' => now()->subHour(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.verifications.index'))
        ->assertOk()
        ->assertSee('Overdue')
        ->assertSee('1 application is past review promise')
        ->assertSee($late->user->name);
});

test('nothing is flagged while every application is inside the promise', function () {
    TeacherProfile::factory()->pending()->create(['submitted_at' => now()->subHour()]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.verifications.index'))
        ->assertOk()
        ->assertDontSee('past review promise');
});
