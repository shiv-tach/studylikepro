<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\RespondToRequestRequest;
use App\Models\TutoringRequest;
use App\Services\RequestMatcher;
use App\Services\RequestResponseService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class RequestInboxController extends Controller
{
    /**
     * Open requests that match the teacher's topics and availability.
     */
    public function index(Request $request, RequestMatcher $matcher): View
    {
        $profile = $request->user()->teacherProfile;
        $page = max(1, (int) $request->query('page', 1));

        $requests = $matcher->inboxFor($profile, $page);

        $suggestedSlots = [];

        foreach ($requests as $tutoringRequest) {
            $suggestedSlots[$tutoringRequest->id] = $matcher->slotsFor($profile, $tutoringRequest, 6);
        }

        return view('teacher.requests.index', [
            'requests' => $requests,
            'suggestedSlots' => $suggestedSlots,
            'myResponses' => $profile->requestResponses()
                ->with(['tutoringRequest.subject', 'tutoringRequest.topic'])
                ->latest('responded_at')
                ->limit(10)
                ->get(),
        ]);
    }

    /**
     * Accept (creating the payment hold) or decline a request.
     */
    public function respond(
        RespondToRequestRequest $request,
        TutoringRequest $tutoringRequest,
        RequestResponseService $service,
    ): RedirectResponse {
        Gate::authorize('respond', $tutoringRequest);

        $teacher = $request->user()->teacherProfile;

        if ($request->validated('action') === 'accept') {
            $startsAt = CarbonImmutable::parse($request->validated('starts_at'));

            $service->accept(
                $tutoringRequest,
                $teacher,
                $startsAt,
                $startsAt->addMinutes($teacher->lessonDuration()),
                $request->validated('message'),
            );

            return redirect()
                ->route('teacher.requests.index')
                ->with('status', 'request-accepted');
        }

        $service->decline($tutoringRequest, $teacher, $request->validated('message'));

        return redirect()
            ->route('teacher.requests.index')
            ->with('status', 'request-declined');
    }
}
