<?php

namespace App\Listeners;

use App\Events\ReviewChanged;
use App\Services\TeacherStatsService;

class RefreshTeacherStatsOnReviewChange
{
    public function __construct(private readonly TeacherStatsService $stats) {}

    public function handle(ReviewChanged $event): void
    {
        $this->stats->refresh($event->review->teacherProfile);
    }
}
