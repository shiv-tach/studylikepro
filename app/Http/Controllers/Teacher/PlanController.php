<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\ServicePurchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The teacher's view of purchased plans and their sessions.
 */
class PlanController extends Controller
{
    public function index(Request $request): View
    {
        $profile = $request->user()->teacherProfile;

        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', array_column(PurchaseStatus::cases(), 'value'))],
        ]);

        $purchases = ServicePurchase::query()
            ->where('teacher_profile_id', $profile->id)
            ->with(['student', 'offering', 'subject', 'lesson'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('teacher.plans.index', [
            'purchases' => $purchases,
            'statuses' => PurchaseStatus::cases(),
            'selectedStatus' => $filters['status'] ?? null,
        ]);
    }

    public function show(Request $request, ServicePurchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        $purchase->load(['student', 'offering', 'subject', 'lesson', 'sessions']);

        return view('teacher.plans.show', [
            'purchase' => $purchase,
        ]);
    }
}
