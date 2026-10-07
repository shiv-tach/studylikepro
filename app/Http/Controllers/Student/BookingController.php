<?php

namespace App\Http\Controllers\Student;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Services\BookingFeeService;
use App\Services\BookingService;
use App\Services\BookingTransitionService;
use App\Services\CatalogService;
use App\Services\Payments\PaymentService;
use App\Support\BookingDraft;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly BookingTransitionService $transitions,
        private readonly CatalogService $catalog,
    ) {}

    /**
     * My Lessons — upcoming and past tabs with history filters.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'tab' => ['nullable', 'string', 'in:upcoming,past'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'subject' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher' => ['nullable', 'integer', 'exists:teacher_profiles,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $tab = ($filters['tab'] ?? null) === 'past' ? 'past' : 'upcoming';

        $bookings = $request->user()->bookings()
            ->with(['teacherProfile.user', 'subject', 'lesson', 'review'])
            ->when(
                $tab === 'past',
                fn ($query) => $query->past(),
                fn ($query) => $query->upcoming(),
            )
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($filters['subject'] ?? null, fn ($query, int $subject) => $query->where('subject_id', $subject))
            ->when($filters['teacher'] ?? null, fn ($query, int $teacher) => $query->where('teacher_profile_id', $teacher))
            ->when($filters['from'] ?? null, fn ($query, string $from) => $query->where('starts_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($query, string $to) => $query->where('starts_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($tab === 'past', fn ($query) => $query->latest('starts_at'), fn ($query) => $query->oldest('starts_at'))
            ->paginate(8)
            ->withQueryString();

        $historySubjectIds = $request->user()->bookings()->whereNotNull('subject_id')->distinct()->pluck('subject_id');
        $historyTeacherIds = $request->user()->bookings()->distinct()->pluck('teacher_profile_id');

        return view('student.bookings.index', [
            'bookings' => $bookings,
            'tab' => $tab,
            'filters' => $filters,
            'counts' => [
                'upcoming' => $request->user()->bookings()->upcoming()->count(),
                'past' => $request->user()->bookings()->past()->count(),
            ],
            'subjects' => Subject::query()->whereIn('id', $historySubjectIds)->orderBy('name')->get(),
            'teachers' => TeacherProfile::query()->whereIn('id', $historyTeacherIds)->with('user')->orderBy('id')->get(),
            'statuses' => BookingStatus::cases(),
        ]);
    }

    /**
     * Pick subject, length and slot for a direct booking.
     */
    public function create(Request $request, TeacherProfile $teacherProfile, BookingFeeService $fees): View
    {
        Gate::authorize('create', [Booking::class, $teacherProfile]);

        $teacherProfile->load(['user', 'subjects', 'lessons.grade', 'availabilitySlots', 'timeOff']);

        $timezone = $this->timezoneFor($request);
        $subjects = $teacherProfile->subjects->sortBy('name')->values();

        abort_if($subjects->isEmpty(), 404);

        $levels = $this->catalog->levels();

        // The lessons on offer are the teacher's lessons for the learner's
        // grade (defaults to the student's own profile grade).
        $learnerGradeId = (int) ($request->integer('learner_grade_id') ?: $request->user()->studentProfile?->grade_id) ?: null;
        $learnerGrade = $learnerGradeId !== null
            ? $levels->flatMap(fn ($level) => $level->grades)->firstWhere('id', $learnerGradeId)
            : null;

        $subject = $subjects->firstWhere('id', (int) $request->integer('subject_id')) ?? $subjects->first();

        $durations = config('studylikepro.lesson_durations');
        $defaultDuration = in_array($teacherProfile->lessonDuration(), $durations, true)
            ? $teacherProfile->lessonDuration()
            : (in_array(60, $durations, true) ? 60 : $durations[0]);
        $duration = in_array($request->integer('duration'), $durations, true)
            ? $request->integer('duration')
            : $defaultDuration;

        $lessons = $teacherProfile->lessons
            ->where('subject_id', $subject->id)
            ->when($learnerGradeId !== null, fn ($lessons) => $lessons->where('grade_id', $learnerGradeId))
            ->sortBy('name')
            ->values();

        $selectedLessonId = $request->integer('lesson_id') ?: null;

        return view('student.bookings.create', [
            'teacher' => $teacherProfile,
            'subjects' => $subjects,
            'lessons' => $lessons,
            'levels' => $levels,
            'learnerGradeId' => $learnerGradeId,
            'learnerGrade' => $learnerGrade,
            'subject' => $subject,
            'subjectId' => $subject->id,
            'selectedLessonId' => $lessons->contains('id', $selectedLessonId) ? $selectedLessonId : null,
            'durations' => $durations,
            'duration' => $duration,
            'slotsByDate' => $this->bookings->availableSlots($teacherProfile, $duration, $timezone),
            'priceMinor' => $this->bookings->priceMinor($teacherProfile, $subject, $duration, $learnerGradeId),
            'bookingFee' => $fees->quote(),
            'timezone' => $timezone,
        ]);
    }

    /**
     * Reserve the chosen slot as a pending-payment hold.
     */
    public function store(StoreBookingRequest $request, TeacherProfile $teacherProfile): RedirectResponse
    {
        Gate::authorize('create', [Booking::class, $teacherProfile]);

        $teacherProfile->load(['subjects', 'lessons.grade', 'availabilitySlots', 'timeOff']);

        $subject = $teacherProfile->subjects->firstWhere('id', (int) $request->validated('subject_id'));

        if (! $subject) {
            throw ValidationException::withMessages([
                'subject_id' => __('This teacher does not offer that subject.'),
            ]);
        }

        $student = $request->user();
        $learnerGradeId = (int) ($request->validated('learner_grade_id') ?: $student->studentProfile?->grade_id) ?: null;

        $lessonId = $request->integer('lesson_id') ?: null;
        $lesson = null;

        if ($lessonId !== null) {
            $lesson = $teacherProfile->lessons
                ->where('subject_id', $subject->id)
                ->firstWhere('id', $lessonId);

            if (! $lesson) {
                throw ValidationException::withMessages([
                    'lesson_id' => __('Pick a lesson this teacher covers for :subject.', ['subject' => $subject->name]),
                ]);
            }

            // The picker only offers the learner's grade; enforce it for
            // anything posted around it.
            if ($learnerGradeId !== null && (int) $lesson->grade_id !== $learnerGradeId) {
                throw ValidationException::withMessages([
                    'lesson_id' => __('That lesson is for another grade — pick a lesson for the learner\'s grade.'),
                ]);
            }
        }

        $duration = $request->integer('duration');
        $startsAt = CarbonImmutable::parse($request->validated('starts_at'))->utc();
        $slot = $this->resolveSlot($teacherProfile, $duration, $startsAt);

        if ($slot === null) {
            throw ValidationException::withMessages([
                'starts_at' => __('That time is no longer open — pick another slot.'),
            ]);
        }

        $booking = $this->bookings->reserve(new BookingDraft(
            student: $student,
            teacher: $teacherProfile,
            startsAt: $slot['starts_at'],
            endsAt: $slot['ends_at'],
            subject: $subject,
            lesson: $lesson,
            learnerName: $request->validated('learner_name') ?: $student->name,
            learnerGradeId: $learnerGradeId,
        ));

        return redirect()
            ->route('student.bookings.show', $booking)
            ->with('status', 'hold-created');
    }

    /**
     * Booking detail with the status timeline and payment/cancellation actions.
     */
    public function show(Request $request, Booking $booking, PaymentService $payments): View
    {
        Gate::authorize('view', $booking);

        $booking->load(['teacherProfile.user', 'subject', 'lesson', 'tutoringRequest', 'review', 'conversation', 'bookingFeePromotion']);

        return view('student.bookings.show', [
            'booking' => $booking,
            'timezone' => $this->timezoneFor($request),
            'canCancel' => Gate::allows('cancel', $booking),
            'payment' => $payments->capturedFor($booking),
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $this->transitions->cancel($booking, Booking::CANCELLED_BY_STUDENT, $request->validated('reason'));

        return redirect()
            ->route('student.bookings.show', $booking)
            ->with('status', 'booking-cancelled');
    }

    /**
     * Reschedule in V1: release the slot and pick a new one.
     */
    public function reschedule(Booking $booking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $this->transitions->cancel($booking, Booking::CANCELLED_BY_STUDENT, 'Rescheduling this lesson.');

        return redirect()
            ->route('student.bookings.create', $booking->teacher_profile_id)
            ->with('status', 'booking-reschedule');
    }

    /**
     * Match a submitted start time against the teacher's open slots.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}|null
     */
    private function resolveSlot(TeacherProfile $teacher, int $durationMinutes, CarbonImmutable $startsAt): ?array
    {
        $days = (int) config('studylikepro.booking.max_advance_days');

        foreach ($this->bookings->availableSlots($teacher, $durationMinutes, 'UTC', $days, 500) as $slots) {
            foreach ($slots as $slot) {
                if ($slot['starts_at']->equalTo($startsAt)) {
                    return $slot;
                }
            }
        }

        return null;
    }

    private function timezoneFor(Request $request): string
    {
        return $request->user()->studentProfile?->timezone
            ?? config('studylikepro.default_display_timezone');
    }
}
