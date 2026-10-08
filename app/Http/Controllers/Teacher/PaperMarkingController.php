<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\MarkingOrderStatus;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\MarkPaperRequest;
use App\Models\PaperMarkingOrder;
use App\Services\PaperMarkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaperMarkingController extends Controller
{
    public function __construct(private readonly PaperMarkingService $marking) {}

    /**
     * The teacher's marking inbox: paid orders awaiting work and past ones.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(MarkingOrderStatus::class)],
        ]);

        $orders = PaperMarkingOrder::query()
            ->forTeacher($request->user()->teacherProfile->id)
            ->whereHas('purchase', function ($query) {
                $query->whereIn('status', [
                    PurchaseStatus::Active->value,
                    PurchaseStatus::Completed->value,
                ]);
            })
            ->with(['purchase.student', 'subject'])
            ->when(
                $filters['status'] ?? null,
                fn ($query, string $status) => $query->where('status', $status),
            )
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('teacher.marking.index', [
            'orders' => $orders,
            'statuses' => MarkingOrderStatus::cases(),
            'selectedStatus' => $filters['status'] ?? null,
        ]);
    }

    /**
     * One order: the student's paper and the delivery form.
     */
    public function show(PaperMarkingOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load('purchase', 'student', 'subject');

        $this->marking->syncDeadline($order);

        return view('teacher.marking.show', [
            'order' => $order,
            'maxFiles' => (int) config('studylikepro.services.marking_max_files'),
        ]);
    }

    /**
     * Deliver the marked copy, feedback and marks.
     */
    public function mark(MarkPaperRequest $request, PaperMarkingOrder $order): RedirectResponse
    {
        Gate::authorize('mark', $order);

        $this->marking->startMarking($order);

        $this->marking->mark(
            $order,
            $request->file('marked_files'),
            $request->validated('feedback') !== null ? (string) $request->validated('feedback') : null,
            $request->markRows(),
        );

        return redirect()
            ->route('teacher.marking.show', $order)
            ->with('status', 'marking-returned');
    }

    /**
     * Stream an uploaded paper or the marked copy back to the browser.
     */
    public function download(PaperMarkingOrder $order, string $kind, int $index): StreamedResponse
    {
        Gate::authorize('download', $order);

        abort_unless(in_array($kind, ['files', 'marked_files'], true), 404);

        $entry = data_get($order->{$kind} ?? [], $index);

        abort_if(! is_array($entry) || blank($entry['path'] ?? null), 404);

        return Storage::disk('local')->response(
            $entry['path'],
            $entry['original_name'] ?? 'download',
        );
    }
}
