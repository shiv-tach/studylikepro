<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\Payments\EarningsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    /**
     * What the teacher has earned, what is still pending, and what has been paid out.
     */
    public function index(Request $request, EarningsService $earnings): View
    {
        $profile = $request->user()->teacherProfile()->firstOrFail();

        return view('teacher.earnings.index', [
            'teacher' => $profile,
            'totals' => $earnings->totalsFor($profile),
            'ledger' => $profile->earnings()
                ->with(['booking.subject', 'booking.topic', 'booking.student', 'payout'])
                ->latest()
                ->paginate(15),
            'payouts' => $profile->payouts()->limit(10)->get(),
            'timezone' => $profile->timezone ?: config('studylikepro.default_display_timezone'),
        ]);
    }
}
