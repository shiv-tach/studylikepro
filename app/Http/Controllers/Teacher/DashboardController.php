<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Services\Payments\EarningsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, EarningsService $earnings): View
    {
        $profile = $request->user()->teacherProfile;

        return view('teacher.dashboard', [
            'weeklySlotCount' => $profile?->availabilitySlots()->count() ?? 0,
            'lessonDuration' => $profile?->lessonDuration() ?? 60,
            'upcomingLessonsCount' => $profile
                ? $profile->bookings()
                    ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
                    ->where('starts_at', '>=', now())
                    ->count()
                : 0,
            'earnings' => $profile ? $earnings->totalsFor($profile) : null,
        ]);
    }
}
