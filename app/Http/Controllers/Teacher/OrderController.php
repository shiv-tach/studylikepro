<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ServicePurchase;
use App\Services\PurchaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The teacher's view of student orders (service purchases) and their sessions.
 *
 * Blade owns the query, the grouped status counts and the money totals, then
 * hands a formatted payload to resources/js/teacher-orders (the list) and
 * resources/js/teacher-order-show (one order), so neither Vue app has to guess a
 * label, a price or a route.
 */
class OrderController extends Controller
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $profile = $request->user()->teacherProfile;

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(PurchaseStatus::cases(), 'value'))],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $filters['status'] ?? null;
        $search = trim((string) ($filters['search'] ?? ''));

        // The teacher's whole book of orders, which every figure below is read
        // from — the page itself is only one slice of it.
        $orders = ServicePurchase::query()->where('teacher_profile_id', $profile->id);

        $purchases = (clone $orders)
            ->with(['sessions', 'markingOrder', 'student', 'offering', 'subject', 'lesson'])
            ->when($status, fn ($query, string $status) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $like = '%'.$search.'%';

                // The reference number is searched as an exact id, the rest as
                // free text across everything the order is named after.
                $query->whereHas('student', fn ($student) => $student->where('name', 'like', $like))
                    ->orWhereHas('offering', fn ($offering) => $offering->where('title', 'like', $like))
                    ->orWhereHas('subject', fn ($subject) => $subject->where('name', 'like', $like))
                    ->orWhereHas('lesson', fn ($lesson) => $lesson->where('name', 'like', $like))
                    ->orWhere('id', ctype_digit($search) ? (int) $search : 0);
            }))
            ->latest()
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        // One grouped query keeps the tab counts honest even when a page is
        // filtered, so a tab never reads 0 just because another one is active.
        $counts = (clone $orders)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->all();

        $total = array_sum($counts);

        // Money the teacher has earned on orders that were actually paid for;
        // a cancelled or lapsed hold never contributes.
        $earnedMinor = (int) (clone $orders)
            ->whereNotNull('paid_at')
            ->whereNotIn('status', [
                PurchaseStatus::Cancelled->value,
                PurchaseStatus::Expired->value,
            ])
            ->sum('teacher_payout_minor');

        $statusCount = fn (PurchaseStatus $case) => (int) ($counts[$case->value] ?? 0);

        return view('teacher.orders.index', [
            'purchases' => $purchases,
            'statuses' => PurchaseStatus::cases(),
            'selectedStatus' => $status,
            'search' => $search,
            'counts' => [
                'total' => $total,
                'awaiting' => $statusCount(PurchaseStatus::PendingPayment),
                'active' => $statusCount(PurchaseStatus::Active),
                'completed' => $statusCount(PurchaseStatus::Completed),
                'earnedMinor' => $earnedMinor,
            ],
            'statusCounts' => $counts,
        ]);
    }

    public function show(Request $request, ServicePurchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        $purchase->load([
            'student.studentProfile.grade',
            'offering.grade',
            'offering.subject',
            'offering.lesson',
            'subject',
            'lesson',
            'sessions.subject',
            'sessions.lesson',
            'sessions.conversation',
            'markingOrder',
            'earning',
            'payments',
        ]);

        return view('teacher.orders.show', [
            'purchase' => $purchase,
            'capturedPayment' => $this->purchases->capturedFor($purchase),
        ]);
    }
}
