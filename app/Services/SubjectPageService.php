<?php

namespace App\Services;

use App\Enums\ServiceType;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\ServiceOffering;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * The data behind a subject page — the public catalog page and the student
 * workspace page render the same content, so the assembly lives here.
 */
class SubjectPageService
{
    /** Teacher cards a subject page previews before linking to the directory. */
    private const TEACHER_PREVIEW = 6;

    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * The education level the subject belongs to.
     */
    public function levelFor(Subject $subject): ?EducationLevel
    {
        return $this->catalog->levels()->firstWhere('id', $subject->education_level_id);
    }

    /**
     * The grade a visitor asked for in the query string — only when it
     * belongs to the subject's level.
     */
    public function requestedGrade(Request $request, ?EducationLevel $level): ?Grade
    {
        $gradeId = $request->query('grade');

        if ($level === null || ! is_numeric($gradeId)) {
            return null;
        }

        return $level->grades->firstWhere('id', (int) $gradeId);
    }

    /**
     * The grades this subject actually runs in — the ones with at least one
     * active lesson — in curriculum order. The subject page uses them for the
     * grade switcher, so a visitor never has to go back to the catalog.
     *
     * @return Collection<int, Grade>
     */
    public function gradesForSubject(Subject $subject): Collection
    {
        return $this->catalog->lessonsFor($subject)
            ->pluck('grade')
            ->filter()
            ->unique('id')
            ->sortBy(fn (Grade $grade) => [$grade->sort_order, $grade->number])
            ->values();
    }

    /**
     * The student's profile grade when it belongs to the subject's level —
     * the student pages scope everything to the profile grade and never let
     * the query string widen it.
     */
    public function profileGrade(?EducationLevel $level, ?Grade $grade): ?Grade
    {
        if ($level === null || $grade === null) {
            return null;
        }

        return (int) $grade->education_level_id === (int) $level->id
            ? $grade
            : null;
    }

    /**
     * Everything both subject page wrappers render: the scoped lessons, the
     * teacher preview with next availability, and the grouped service
     * offerings, all keyed for the view.
     *
     * @return array{subject: Subject, level: ?EducationLevel, grade: ?Grade, gradeOptions: Collection<int, Grade>, lessons: Collection<int, mixed>, teachers: Collection<int, mixed>, teacherTotal: int, nextSlots: array<int, mixed>, offeringGroups: Collection<int, array{type: ServiceType, offerings: Collection<int, ServiceOffering>}>, gradesById: Collection<int, Grade>}
     */
    public function payload(Subject $subject, ?Grade $grade, TeacherSearch $search, SlotService $slots): array
    {
        $levels = $this->catalog->levels();
        $gradeOptions = $this->gradesForSubject($subject);

        $lessons = $this->catalog->lessonsFor($subject);

        if ($grade !== null) {
            $lessons = $lessons->where('grade_id', $grade->id)->values();
        }

        $filters = array_filter([
            'subject' => $subject->slug,
            'grade' => $grade?->id,
        ], fn ($value) => $value !== null);

        $teachers = $search->query($filters)->limit(self::TEACHER_PREVIEW)->get();

        $nextSlots = [];

        foreach ($teachers as $teacher) {
            $nextSlots[$teacher->id] = $slots->upcoming($teacher, 7, 1, excludeBookings: true)[0] ?? null;
        }

        return [
            'subject' => $subject,
            'level' => $levels->firstWhere('id', $subject->education_level_id),
            'grade' => $grade,
            'gradeOptions' => $gradeOptions,
            'lessons' => $lessons,
            'teachers' => $teachers,
            'teacherTotal' => $search->query($filters)->count(),
            'nextSlots' => $nextSlots,
            'offeringGroups' => $this->offeringGroups($subject, $grade),
            'gradesById' => $levels->flatMap(fn (EducationLevel $option) => $option->grades)->keyBy('id'),
        ];
    }

    /**
     * The service catalog on the subject page: what verified teachers sell for
     * this subject — lesson plans, paper marking, discussions and Q&A —
     * narrowed to the grade the visitor is scoped to. Grouped in service
     * type order so the page reads like the teacher's own services list.
     *
     * @return Collection<int, array{type: ServiceType, offerings: Collection<int, ServiceOffering>}>
     */
    private function offeringGroups(Subject $subject, ?Grade $grade): Collection
    {
        $offerings = ServiceOffering::query()
            ->active()
            ->whereHas('teacherProfile', fn (Builder $query) => $query->approved())
            ->where(function (Builder $query) use ($subject) {
                $query->where('subject_id', $subject->id)
                    ->orWhereHas('lesson', fn (Builder $lesson) => $lesson->where('subject_id', $subject->id));
            })
            ->when($grade !== null, fn (Builder $query) => $query->forGrade($grade->id, [$subject->id]))
            ->ordered()
            ->with(['subject', 'lesson', 'grade', 'teacherProfile.user'])
            ->get();

        return collect(ServiceType::cases())
            ->map(fn (ServiceType $type) => [
                'type' => $type,
                'offerings' => $offerings
                    ->filter(fn (ServiceOffering $offering) => $offering->type() === $type)
                    ->values(),
            ])
            ->filter(fn (array $group) => $group['offerings']->isNotEmpty())
            ->values();
    }
}
