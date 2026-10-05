<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\BookingTransitionService;
use Illuminate\Console\Command;

class AutoCompleteLessons extends Command
{
    protected $signature = 'studylikepro:complete-lessons';

    protected $description = 'Complete lessons whose end time plus grace has passed without teacher action';

    public function handle(BookingTransitionService $transitions): int
    {
        $grace = (int) config('studylikepro.booking.auto_complete_grace_minutes');

        $due = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->where('ends_at', '<=', now()->subMinutes($grace))
            ->get();

        foreach ($due as $booking) {
            $transitions->complete($booking, automatic: true);
        }

        $this->info("Completed {$due->count()} lesson(s) automatically.");

        return self::SUCCESS;
    }
}
