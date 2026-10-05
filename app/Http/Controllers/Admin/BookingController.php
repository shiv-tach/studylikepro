<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\MeetingStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Services\ActivityLogger;
use App\Services\BookingTransitionService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(BookingStatus::cases(), 'value'))],
            'teacher' => ['nullable', 'integer', 'exists:teacher_profiles,id'],
            'student' => ['nullable', 'integer', 'exists:users,id'],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $bookings = Booking::query()
            ->with(['student', 'teacherProfile.user', 'subject', 'topic'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['teacher'] ?? null, fn (Builder $query, int $teacher) => $query->where('teacher_profile_id', $teacher))
            ->when($filters['student'] ?? null, fn (Builder $query, int $student) => $query->where('student_id', $student))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('starts_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('starts_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->whereHas('student', fn (Builder $student) => $student
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhere('learner_name', 'like', "%{$search}%");
                });
            })
            ->latest('starts_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.bookings.index', [
            'bookings' => $bookings,
            'statuses' => BookingStatus::cases(),
            'teachers' => TeacherProfile::query()->with('user')->orderBy('id')->get(),
            'filters' => $filters,
            'timezone' => config('studylikepro.default_display_timezone'),
            'stats' => [
                'live' => Booking::query()->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])->count(),
                'holds' => Booking::query()->where('status', BookingStatus::PendingPayment->value)->where('expires_at', '>', now())->count(),
                'completed' => Booking::query()->where('status', BookingStatus::Completed->value)->count(),
                'cancelled' => Booking::query()->whereIn('status', [BookingStatus::Cancelled->value, BookingStatus::Expired->value])->count(),
                'meeting_failed' => Booking::query()->where('meeting_status', MeetingStatus::Failed->value)->count(),
            ],
        ]);
    }

    /**
     * The full picture of one lesson: parties, timeline, money, classroom and a
     * read-only view of the chat support uses as evidence.
     */
    public function show(Request $request, Booking $booking): View
    {
        $booking->load([
            'student',
            'teacherProfile.user',
            'subject',
            'topic',
            'tutoringRequest',
            'payments.refunds',
            'refunds',
            'earning',
            'review',
            'conversation.messages.sender',
            'disputes',
        ]);

        return view('admin.bookings.show', [
            'booking' => $booking,
            'payment' => $booking->payments->firstWhere('status', PaymentStatus::Captured)
                ?? $booking->payments->sortByDesc('id')->first(),
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    public function cancel(CancelBookingRequest $request, Booking $booking, BookingTransitionService $transitions, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $transitions->cancel($booking, Booking::CANCELLED_BY_ADMIN, $request->validated('reason'));

        $activity->describe('Cancelled booking #'.$booking->id.($request->validated('reason') ? ': '.$request->validated('reason') : ''));

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'booking-cancelled');
    }

    /**
     * Close a lesson support has confirmed was delivered. Support is not bound
     * by the teacher's start window, so this goes straight to completion.
     */
    public function forceComplete(Request $request, Booking $booking, BookingTransitionService $transitions, ActivityLogger $activity): RedirectResponse
    {
        $transitions->complete($booking, automatic: true);

        $activity->describe('Force-completed booking #'.$booking->id);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('status', 'booking-completed');
    }
}
