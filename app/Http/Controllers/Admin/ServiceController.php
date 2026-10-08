<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Models\ServiceOffering;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin overview of the teacher service catalog.
 */
class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'in:'.implode(',', array_column(ServiceType::cases(), 'value'))],
            'teacher' => ['nullable', 'integer', 'exists:teacher_profiles,id'],
        ]);

        $offerings = ServiceOffering::query()
            ->with(['teacherProfile.user', 'subject', 'lesson'])
            ->when($filters['type'] ?? null, fn ($query, string $type) => $query->where('service_type', $type))
            ->when($filters['teacher'] ?? null, fn ($query, int $teacher) => $query->where('teacher_profile_id', $teacher))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.services.index', [
            'offerings' => $offerings,
            'types' => ServiceType::cases(),
            'selectedType' => $filters['type'] ?? null,
        ]);
    }

    public function destroy(Request $request, ServiceOffering $offering): RedirectResponse
    {
        if ($offering->purchases()->exists()) {
            return redirect()
                ->route('admin.services.index')
                ->with('status', 'offering-has-purchases');
        }

        $offering->delete();

        return redirect()
            ->route('admin.services.index')
            ->with('status', 'offering-deleted');
    }
}
