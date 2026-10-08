<?php

namespace App\Http\Controllers\Student;

use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTutoringRequestRequest;
use App\Http\Requests\UpdateRequestLessonRequest;
use App\Jobs\ClassifyTutoringRequestJob;
use App\Models\Lesson;
use App\Models\TutoringRequest;
use App\Notifications\RequestPublished;
use App\Services\CatalogService;
use App\Services\RequestMatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TutoringRequestController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * The student's requests and their status.
     */
    public function index(Request $request): View
    {
        $requests = $request->user()->tutoringRequests()
            ->with(['subject', 'lesson'])
            ->withCount(['responses' => fn ($query) => $query->where('status', ResponseStatus::Pending->value)])
            ->paginate(10);

        return view('student.requests.index', ['requests' => $requests]);
    }

    /**
     * Compose a new request.
     */
    public function create(Request $request): View
    {
        // The learner grade is locked to the student's registered grade, so the
        // page shows it read-only instead of offering a picker.
        $gradeId = (int) $request->user()->studentProfile?->grade_id ?: null;
        $levels = $this->catalog->levels();

        return view('student.requests.create', [
            'subjects' => $this->catalog->subjects(),
            'learnerGrade' => $gradeId !== null
                ? $levels->flatMap(fn ($level) => $level->grades)->firstWhere('id', $gradeId)
                : null,
            'maxAttachments' => (int) config('studylikepro.requests.max_attachments'),
        ]);
    }

    /**
     * Store the request, upload attachments, and queue AI classification.
     */
    public function store(StoreTutoringRequestRequest $request): RedirectResponse
    {
        $user = $request->user();

        $tutoringRequest = DB::transaction(function () use ($request, $user) {
            $created = $user->tutoringRequests()->create([
                // The grade is locked to the profile; a posted grade_id is ignored.
                'grade_id' => $user->studentProfile?->grade_id,
                'description' => $request->validated('description'),
                'budget_minor' => filled($request->validated('budget'))
                    ? (int) round(((float) $request->validated('budget')) * 100)
                    : null,
                'preferred_windows' => $request->windowPayload(),
                'status' => RequestStatus::Open,
                'classification_status' => ClassificationStatus::Pending,
                'expires_at' => now()->addDays((int) config('studylikepro.requests.open_for_days')),
            ]);

            $imageHash = null;

            foreach ($request->file('attachments') ?? [] as $file) {
                if ($imageHash === null && str_starts_with($file->getMimeType() ?: '', 'image/')) {
                    $imageHash = hash_file('sha256', $file->getPathname()) ?: null;
                }

                $created->attachments()->create([
                    'path' => $file->store("tutoring-requests/{$created->id}", 'local'),
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'size' => (int) $file->getSize(),
                ]);
            }

            if ($imageHash !== null) {
                $created->update(['image_hash' => $imageHash]);
            }

            return $created;
        });

        ClassifyTutoringRequestJob::dispatch($tutoringRequest->id);

        return redirect()
            ->route('student.requests.show', $tutoringRequest)
            ->with('status', 'request-created');
    }

    /**
     * Request detail: AI suggestion, manual override, and teacher proposals.
     */
    public function show(Request $request, TutoringRequest $tutoringRequest): View
    {
        Gate::authorize('view', $tutoringRequest);

        $tutoringRequest->load([
            'subject',
            'grade',
            'lesson',
            'attachments',
            'booking.teacherProfile.user',
            'responses' => fn ($query) => $query->with(['teacherProfile.user', 'teacherProfile.subjects'])->latest('responded_at'),
        ]);

        $suggestedSubjectId = data_get($tutoringRequest->ai_payload, 'subject_id');
        $suggestedLessonId = data_get($tutoringRequest->ai_payload, 'lesson_id');

        // The subject and lesson pickers only offer the request's grade, so
        // the student physically cannot file a Grade 8 question under a
        // Grade 11 lesson.
        $subjects = $this->catalog->subjects();
        $grade = $tutoringRequest->grade;

        if ($grade !== null) {
            $subjects = $subjects
                ->where('education_level_id', $grade->education_level_id)
                ->values();

            $subjects->each(fn ($subject) => $subject->setRelation(
                'lessons',
                $subject->lessons->where('grade_id', $grade->id)->values(),
            ));
        }

        return view('student.requests.show', [
            'tutoringRequest' => $tutoringRequest,
            'suggestedSubjectId' => $suggestedSubjectId,
            'suggestedLessonId' => $suggestedLessonId,
            'grade' => $grade,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Confirm or override the AI suggestion.
     */
    public function updateLesson(UpdateRequestLessonRequest $request, TutoringRequest $tutoringRequest, RequestMatcher $matcher): RedirectResponse
    {
        Gate::authorize('updateLesson', $tutoringRequest);

        $wasMatchable = $tutoringRequest->isMatchable();

        $tutoringRequest->update([
            'subject_id' => $request->validated('subject_id'),
            'lesson_id' => $request->validated('lesson_id'),
            'grade_id' => Lesson::query()->whereKey($request->validated('lesson_id'))->value('grade_id'),
            'classification_status' => ClassificationStatus::Completed,
        ]);

        if (! $wasMatchable) {
            foreach ($matcher->teachersFor($tutoringRequest->fresh()) as $teacher) {
                $teacher->user->notify(new RequestPublished($tutoringRequest));
            }
        }

        return redirect()
            ->route('student.requests.show', $tutoringRequest)
            ->with('status', 'lesson-confirmed');
    }

    /**
     * Cancel an open request.
     */
    public function cancel(TutoringRequest $tutoringRequest): RedirectResponse
    {
        Gate::authorize('cancel', $tutoringRequest);

        $tutoringRequest->update([
            'status' => RequestStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $tutoringRequest->responses()
            ->where('status', ResponseStatus::Pending->value)
            ->update(['status' => ResponseStatus::Expired->value, 'updated_at' => now()]);

        return redirect()
            ->route('student.requests.index')
            ->with('status', 'request-cancelled');
    }
}
