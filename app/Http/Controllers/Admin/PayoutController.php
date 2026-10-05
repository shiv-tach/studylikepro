<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PayoutStatus;
use App\Http\Controllers\Controller;
use App\Models\Payout;
use App\Models\TeacherProfile;
use App\Services\Payments\EarningsService;
use App\Services\Payments\PayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayoutController extends Controller
{
    public function index(Request $request, EarningsService $earnings): View
    {
        $teachers = TeacherProfile::query()
            ->with('user')
            ->whereHas('earnings')
            ->orderBy('id')
            ->get()
            ->map(fn (TeacherProfile $teacher) => [
                'teacher' => $teacher,
                'totals' => $earnings->totalsFor($teacher),
                'unbatched' => $earnings->unbatchedFor($teacher)->sum(fn ($earning) => $earning->netMinor()),
            ]);

        return view('admin.payouts.index', [
            'teachers' => $teachers,
            'payouts' => Payout::query()->with('teacherProfile.user')->latest()->paginate(15),
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    /**
     * Sweep a teacher's available earnings into one pending payout.
     */
    public function store(Request $request, PayoutService $payouts): RedirectResponse
    {
        $validated = $request->validate([
            'teacher_profile_id' => ['required', 'integer', 'exists:teacher_profiles,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $teacher = TeacherProfile::query()->findOrFail($validated['teacher_profile_id']);
        $payout = $payouts->createFor($teacher, $validated['notes'] ?? null);

        return redirect()
            ->route('admin.payouts.index')
            ->with('status', $payout ? 'payout-created' : 'payout-empty');
    }

    public function markPaid(Request $request, Payout $payout, PayoutService $payouts): RedirectResponse
    {
        $validated = $request->validate([
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($payout->status === PayoutStatus::Paid) {
            return redirect()->route('admin.payouts.index')->with('status', 'payout-already-paid');
        }

        $payouts->markPaid($payout, $validated['reference'] ?? null, $validated['notes'] ?? null);

        return redirect()->route('admin.payouts.index')->with('status', 'payout-paid');
    }
}
