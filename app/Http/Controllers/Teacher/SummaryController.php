<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertSummaryRequest;
use App\Models\LessonSummary;
use App\Services\SummaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The teacher's lesson-summary console: the write-up queue, the editor and
 * delivery, which completes the purchase and unlocks the earning.
 */
class SummaryController extends Controller
{
    public function __construct(
        private readonly SummaryService $summaries,
    ) {}

    public function index(Request $request): View
    {
        $teacher = $request->user()->teacherProfile()->firstOrFail();

        $summaries = $this->summaries
            ->pendingFor($teacher)
            ->map(fn ($purchase) => $this->summaries
                ->ensureForPurchase($purchase)
                ->setRelation('purchase', $purchase));

        return view('teacher.summaries.index', [
            'teacher' => $teacher,
            'summaries' => $summaries,
        ]);
    }

    public function edit(LessonSummary $summary): View
    {
        Gate::authorize('write', $summary);

        $summary->loadMissing([
            'purchase.student',
            'purchase.lesson',
            'purchase.subject',
        ]);

        return view('teacher.summaries.edit', [
            'summary' => $summary,
        ]);
    }

    public function update(UpsertSummaryRequest $request, LessonSummary $summary): RedirectResponse
    {
        Gate::authorize('write', $summary);

        $this->summaries->save($summary, $request->validated('content'));

        return redirect()
            ->back()
            ->with('status', 'summary-saved');
    }

    public function deliver(LessonSummary $summary): RedirectResponse
    {
        Gate::authorize('deliver', $summary);

        $this->summaries->deliver($summary);

        return redirect()
            ->route('teacher.summaries.index')
            ->with('status', 'summary-delivered');
    }
}
