<?php

namespace App\Listeners;

use App\Events\BookingCompleted;
use App\Services\TeacherStatsService;

class RefreshTeacherStatsOnCompletion
{
    public function __construct(private readonly TeacherStatsService $stats) {}

    public function handle(BookingCompleted $event): void
    {
        $this->stats->refresh($event->booking->teacherProfile);
    }
}
