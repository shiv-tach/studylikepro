<?php

namespace App\Http\Controllers\Student;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\DisputeMarkingRequest;
use App\Http\Requests\SubmitPaperRequest;
use App\Models\PaperMarkingOrder;
use App\Models\ServiceOffering;
use App\Services\PaperMarkingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaperMarkingController extends Controller
{
    public function __construct(private readonly PaperMarkingService $marking) {}

    /**
     * Submit a paper against a teacher's marking offering.
     */
    public function create(ServiceOffering $offering): View
    {
        Gate::authorize('buy', $offering);

        abort_unless($offering->service_type === ServiceType::PaperMarking, 404);

        $offering->load('teacherProfile.user', 'subject');

        return view('student.marking.create', [
            'offering' => $offering,
            'maxFiles' => (int) config('studylikepro.services.marking_max_files'),
        ]);
    }

    /**
     * Create the pending-payment purchase and the marking order.
     */
    public function store(SubmitPaperRequest $request, ServiceOffering $offering): RedirectResponse
    {
        Gate::authorize('buy', $offering);

        $order = $this->marking->submit(
            $offering,
            $request->user(),
            $request->file('files'),
            $request->validated('page_count') !== null ? (int) $request->validated('page_count') : null,
        );

        return redirect()
            ->route('student.purchases.show', $order->purchase)
            ->with('status', 'marking-submitted');
    }

    /**
     * The order detail: submitted paper, teacher delivery, accept/dispute.
     */
    public function show(PaperMarkingOrder $order): View
    {
        Gate::authorize('view', $order);

        $order->load('purchase', 'purchase.offering', 'teacherProfile.user', 'subject');

        $this->marking->syncDeadline($order);

        return view('student.marking.show', ['order' => $order]);
    }

    public function accept(PaperMarkingOrder $order): RedirectResponse
    {
        Gate::authorize('accept', $order);

        $this->marking->accept($order);

        return redirect()
            ->back()
            ->with('status', 'marking-accepted');
    }

    public function dispute(DisputeMarkingRequest $request, PaperMarkingOrder $order): RedirectResponse
    {
        Gate::authorize('dispute', $order);

        $this->marking->dispute($order, (string) $request->validated('reason'));

        return redirect()
            ->back()
            ->with('status', 'marking-disputed');
    }

    /**
     * Stream an uploaded paper or a marked copy back to the browser.
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
