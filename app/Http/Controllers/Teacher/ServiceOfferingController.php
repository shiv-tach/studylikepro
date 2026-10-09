<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertServiceOfferingRequest;
use App\Models\Grade;
use App\Models\ServiceOffering;
use App\Models\Subject;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceOfferingController extends Controller
{
    /**
     * Manage the teacher's service catalog: lesson plans, summaries,
     * paper marking, paper discussions and Q&A.
     */
    public function index(Request $request): View
    {
        $profile = $request->user()->teacherProfile;

        $subjects = $profile->subjects()->with('educationLevel')->get();

        // Reuse this loaded collection inside gradeIdsFor() instead of
        // re-querying per subject.
        $profile->setRelation('subjects', $subjects);

        $lessons = $profile->lessons()
            ->active()
            ->ordered()
            ->with('grade')
            ->get();

        // The grades this teacher covers, pooled from their subject scopes and
        // their lesson picks, ordered for the cascade dropdown.
        $gradeIds = collect();

        foreach ($subjects as $subject) {
            $gradeIds = $gradeIds->merge($profile->gradeIdsFor($subject));
        }

        $gradeIds = $gradeIds->merge($lessons->pluck('grade_id'))->filter()->unique();

        $grades = Grade::query()->whereIn('id', $gradeIds)->ordered()->get();

        $subjectOptions = $subjects
            ->map(fn (Subject $subject) => [
                'id' => (int) $subject->id,
                'name' => $subject->name,
                'level_name' => $subject->educationLevel?->name,
                'grades' => array_map('intval', $profile->gradeIdsFor($subject)),
            ])
            ->values()
            ->all();

        $gradeOptions = $grades
            ->map(fn (Grade $grade) => [
                'id' => (int) $grade->id,
                'label' => $grade->label,
            ])
            ->values()
            ->all();

        $lessonOptions = $lessons
            ->map(fn ($lesson) => [
                'id' => (int) $lesson->id,
                'subject_id' => (int) $lesson->subject_id,
                'grade_id' => (int) $lesson->grade_id,
                'name' => $lesson->grade ? $lesson->name.' ('.$lesson->grade->label.')' : $lesson->name,
            ])
            ->values()
            ->all();

        $offerings = ServiceOffering::query()
            ->where('teacher_profile_id', $profile->id)
            ->ordered()
            ->with(['subject', 'lesson', 'grade'])
            ->get()
            ->groupBy(fn (ServiceOffering $offering) => $offering->service_type->value);

        return view('teacher.services', [
            'profile' => $profile,
            'subjects' => $subjects,
            'lessons' => $lessons,
            'grades' => $grades,
            'gradeOptions' => $gradeOptions,
            'subjectOptions' => $subjectOptions,
            'lessonOptions' => $lessonOptions,
            'offerings' => $offerings,
            'types' => ServiceType::cases(),
            'durations' => config('studylikepro.lesson_durations'),
        ]);
    }

    public function store(UpsertServiceOfferingRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        $offering = new ServiceOffering($request->validated());
        $offering->teacher_profile_id = $profile->id;
        $offering->currency = (string) config('studylikepro.currency');

        app(PurchaseService::class)->validateOfferingAttributes($offering);

        $this->assertUniqueScope($offering);

        $offering->save();

        return redirect()
            ->route('teacher.services.index')
            ->with('status', 'offering-created');
    }

    public function update(UpsertServiceOfferingRequest $request, ServiceOffering $offering): RedirectResponse
    {
        Gate::authorize('manage', $offering);

        $updated = new ServiceOffering($request->validated());
        $updated->teacher_profile_id = $offering->teacher_profile_id;
        $updated->currency = $offering->currency;

        app(PurchaseService::class)->validateOfferingAttributes($updated);

        $this->assertUniqueScope($updated, $offering->id);

        $offering->update($request->validated());

        return redirect()
            ->route('teacher.services.index')
            ->with('status', 'offering-updated');
    }

    public function destroy(ServiceOffering $offering): RedirectResponse
    {
        Gate::authorize('manage', $offering);

        if ($offering->purchases()->exists()) {
            return redirect()
                ->route('teacher.services.index')
                ->with('status', 'offering-has-purchases');
        }

        $offering->delete();

        return redirect()
            ->route('teacher.services.index')
            ->with('status', 'offering-deleted');
    }

    public function toggle(ServiceOffering $offering): RedirectResponse
    {
        Gate::authorize('manage', $offering);

        $offering->update(['is_active' => ! $offering->is_active]);

        return redirect()
            ->route('teacher.services.index')
            ->with('status', 'offering-toggled');
    }

    /**
     * A teacher offers each service type only once per lesson/subject/grade scope.
     */
    private function assertUniqueScope(ServiceOffering $offering, ?int $ignoreId = null): void
    {
        $duplicate = ServiceOffering::query()
            ->where('teacher_profile_id', $offering->teacher_profile_id)
            ->where('service_type', $offering->service_type->value)
            ->when(
                $offering->subject_id === null,
                fn ($query) => $query->whereNull('subject_id'),
                fn ($query) => $query->where('subject_id', $offering->subject_id),
            )
            ->when(
                $offering->lesson_id === null,
                fn ($query) => $query->whereNull('lesson_id'),
                fn ($query) => $query->where('lesson_id', $offering->lesson_id),
            )
            ->when(
                $offering->grade_id === null,
                fn ($query) => $query->whereNull('grade_id'),
                fn ($query) => $query->where('grade_id', $offering->grade_id),
            )
            ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'service_type' => __('You already offer this service for this scope.'),
            ]);
        }
    }
}
