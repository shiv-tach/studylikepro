<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\EarningStatus;
use App\Http\Controllers\Controller;
use App\Services\Payments\EarningsService;
use App\Support\TeacherEarningsConfig;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EarningsController extends Controller
{
    /**
     * What the teacher has earned, what is still pending, and what has been paid out.
     *
     * Blade owns the queries, the grouped status counts, the six-month trend and
     * every formatted amount, then hands a single payload to
     * resources/js/teacher-earnings. The ledger stays paginated here: the
     * paginator's links are plain URLs and are rendered below the Vue mount.
     */
    public function index(Request $request, EarningsService $earnings): View
    {
        $profile = $request->user()->teacherProfile()->firstOrFail();

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(EarningStatus::cases(), 'value'))],
        ]);

        $status = $filters['status'] ?? null;

        $ledger = $profile->earnings()
            ->with([
                'booking.subject', 'booking.lesson', 'booking.student',
                'servicePurchase.subject', 'servicePurchase.lesson', 'servicePurchase.offering', 'servicePurchase.student',
                'payout',
            ])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $totals = $earnings->totalsFor($profile);

        return view('teacher.earnings.index', [
            // The paginator is rendered by Blade under the Vue mount.
            'ledger' => $ledger,
            'config' => TeacherEarningsConfig::build(
                $totals,
                $ledger,
                $profile->payouts()->limit(10)->get(),
                $earnings->statusCountsFor($profile),
                $status,
                $earnings->monthlyFor($profile, 6),
                $profile->timezone ?: config('studylikepro.default_display_timezone'),
                $request->user()->theme['preset'] ?? 'classic',
            ),
        ]);
    }
}
