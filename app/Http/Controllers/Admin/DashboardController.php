<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\MeetingStatus;
use App\Enums\PaymentStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Subject;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $since = now()->subDays(29)->startOfDay();

        return view('admin.dashboard', [
            'pendingVerifications' => TeacherProfile::query()
                ->where('verification_status', VerificationStatus::Pending)
                ->count(),
            'activeSubjects' => Subject::query()->where('is_active', true)->count(),
            'openDisputes' => Dispute::query()->open()->count(),
            'flaggedReviews' => Review::query()->flagged()->whereNull('hidden_at')->count(),
            'suspendedUsers' => User::query()->whereNotNull('suspended_at')->count(),
            'stuckPayments' => Payment::query()->stuck()->count(),
            'meetingFailures' => Booking::query()->where('meeting_status', MeetingStatus::Failed->value)->count(),
            'recentDisputes' => Dispute::query()
                ->with(['booking.student', 'booking.teacherProfile.user', 'raisedBy', 'against'])
                ->latest()
                ->limit(5)
                ->get(),
            'recentFlags' => Review::query()
                ->flagged()
                ->with(['student', 'teacherProfile.user'])
                ->latest('flagged_at')
                ->limit(5)
                ->get(),
            'money' => [
                'gross' => (int) Payment::query()
                    ->where('status', PaymentStatus::Captured->value)
                    ->where('captured_at', '>=', $since)
                    ->sum('amount_minor'),
                'commission' => (int) Booking::query()
                    ->whereHas('payments', fn ($query) => $query
                        ->where('status', PaymentStatus::Captured->value)
                        ->where('captured_at', '>=', $since))
                    ->sum('platform_fee_minor'),
                'refunded' => (int) Refund::query()->where('created_at', '>=', $since)->sum('amount_minor'),
                'payouts_due' => (int) TeacherEarning::query()
                    ->eligible()
                    ->whereNull('payout_id')
                    ->sum(DB::raw('amount_minor - reversed_minor')),
                'lessons' => Booking::query()
                    ->where('status', BookingStatus::Completed->value)
                    ->where('starts_at', '>=', $since)
                    ->count(),
            ],
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }
}
