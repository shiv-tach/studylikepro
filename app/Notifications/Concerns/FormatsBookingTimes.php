<?php

namespace App\Notifications\Concerns;

use App\Models\Booking;
use App\Models\User;

trait FormatsBookingTimes
{
    /**
     * The timezone this recipient thinks in.
     */
    protected function timezoneFor(User $notifiable): string
    {
        return $notifiable->studentProfile?->timezone
            ?? $notifiable->teacherProfile?->timezone
            ?? config('studylikepro.default_display_timezone');
    }

    protected function windowLabel(Booking $booking, User $notifiable, bool $withDate = true): string
    {
        $startsAt = $booking->starts_at->copy()->setTimezone($this->timezoneFor($notifiable));
        $endsAt = $booking->ends_at->copy()->setTimezone($this->timezoneFor($notifiable));

        $format = $withDate ? 'D d M Y, H:i' : 'H:i';

        return $startsAt->format($format).'–'.$endsAt->format('H:i');
    }
}
