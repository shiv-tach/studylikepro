<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\View\View;

/**
 * People management: find anybody, see everything about them, and act — suspend,
 * reactivate, send a teacher back for verification or resend a notification.
 */
class UserController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'role' => ['nullable', 'string', 'in:'.implode(',', [User::ROLE_STUDENT, User::ROLE_TEACHER])],
            'status' => ['nullable', 'string', 'in:active,suspended'],
            'search' => ['nullable', 'string', 'max:120'],
        ]);

        $users = User::query()
            ->with(['teacherProfile', 'studentProfile'])
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', [User::ROLE_STUDENT, User::ROLE_TEACHER]))
            ->when($filters['role'] ?? null, fn (Builder $query, string $role) => $query->role($role))
            ->when(($filters['status'] ?? null) === 'suspended', fn (Builder $query) => $query->whereNotNull('suspended_at'))
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $query) => $query->whereNull('suspended_at'))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'filters' => $filters,
            'counts' => [
                'students' => User::query()->role(User::ROLE_STUDENT)->count(),
                'teachers' => User::query()->role(User::ROLE_TEACHER)->count(),
                'suspended' => User::query()->whereNotNull('suspended_at')->count(),
            ],
        ]);
    }

    public function show(Request $request, User $user): View
    {
        abort_unless($user->isStudent() || $user->isTeacher(), 404);

        $user->load(['teacherProfile.subjects', 'studentProfile']);

        $bookings = Booking::query()
            ->where(fn (Builder $query) => $user->isTeacher()
                ? $query->where('teacher_profile_id', $user->teacherProfile?->id ?? 0)
                : $query->where('student_id', $user->id))
            ->with(['student', 'teacherProfile.user', 'subject', 'topic'])
            ->latest('starts_at')
            ->limit(15)
            ->get();

        $payments = Payment::query()
            ->where(fn (Builder $query) => $user->isTeacher()
                ? $query->whereHas('booking', fn (Builder $booking) => $booking->where('teacher_profile_id', $user->teacherProfile?->id ?? 0))
                : $query->where('student_id', $user->id))
            ->with('booking')
            ->latest()
            ->limit(15)
            ->get();

        return view('admin.users.show', [
            'user' => $user,
            'bookings' => $bookings,
            'payments' => $payments,
            'reviews' => $user->isTeacher()
                ? Review::query()->forTeacher($user->teacherProfile?->id ?? 0)->with(['student', 'booking'])->latest()->limit(10)->get()
                : Review::query()->where('student_id', $user->id)->with(['booking', 'teacherProfile.user'])->latest()->limit(10)->get(),
            'disputes' => Dispute::query()
                ->where(fn (Builder $query) => $query->where('raised_by', $user->id)->orWhere('against_id', $user->id))
                ->latest()
                ->limit(10)
                ->get(),
            'notifications' => $user->notifications()->limit(5)->get(),
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    public function suspend(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        abort_if($user->isAdmin(), 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $user->suspend($validated['reason']);

        $activity->describe('Suspended '.$user->name.' ('.$user->email.'): '.$validated['reason']);

        return back()->with('status', 'user-suspended');
    }

    public function reactivate(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        abort_if($user->isAdmin(), 404);

        $user->reactivate();

        $activity->describe('Reactivated '.$user->name.' ('.$user->email.')');

        return back()->with('status', 'user-reactivated');
    }

    /**
     * Send an approved teacher back through verification (bad documents, a
     * complaint about credentials…).
     */
    public function reverify(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        $teacher = $user->teacherProfile;

        abort_if($teacher === null, 404);

        $validated = $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $teacher->forceFill([
            'verification_status' => VerificationStatus::Rejected,
            'verification_notes' => $validated['notes'],
            'verified_at' => null,
        ])->save();

        Notification::send($user, new AdminNotice(
            'Your verification needs another look',
            'Support reviewed your teacher profile again: '.$validated['notes'],
            route('teacher.verification'),
        ));

        $activity->describe('Sent '.$user->name.' back for verification: '.$validated['notes']);

        return back()->with('status', 'teacher-reverified');
    }

    /**
     * Post the user's most recent notification to them again — useful when an
     * email bounced or they say they never saw it.
     */
    public function resendNotification(Request $request, User $user, ActivityLogger $activity): RedirectResponse
    {
        $notification = $user->notifications()->first();

        abort_if($notification === null, 404);

        Notification::send($user, new AdminNotice(
            (string) data_get($notification->data, 'title', 'Studylikepro update'),
            (string) data_get($notification->data, 'body', ''),
            (string) data_get($notification->data, 'url', route('dashboard')),
        ));

        $activity->describe('Resent a notification to '.$user->name.': '.data_get($notification->data, 'title'));

        return back()->with('status', 'notification-resent');
    }
}
