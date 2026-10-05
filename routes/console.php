<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('studylikepro:expire-requests')->everyTenMinutes();
Schedule::command('studylikepro:expire-holds')->everyMinute();
Schedule::command('studylikepro:complete-lessons')->everyTenMinutes();
Schedule::command('studylikepro:send-lesson-reminders')->everyFiveMinutes();
Schedule::command('studylikepro:send-daily-digest')->dailyAt('07:00');
Schedule::command('payments:reconcile')->everyFifteenMinutes();
