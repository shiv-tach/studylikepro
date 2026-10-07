<?php

namespace App\Services;

use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Public teacher discovery: only approved teachers, filtered, sorted, paginated.
 */
class TeacherSearch
{
    public const PER_PAGE = 9;

    /**
     * @param  array<string, mixed>  $filters  validated TeacherSearchRequest input
     */
    public function paginate(array $filters, int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        return $this->query($filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TeacherProfile>
     */
    public function query(array $filters): Builder
    {
        $query = TeacherProfile::query()
            ->approved()
            ->withCount(['lessons' => fn ($relation) => $relation->where('lessons.is_active', true)])
            ->with([
                'user',
                'subjects' => fn ($relation) => $relation->where('subjects.is_active', true),
                'availabilitySlots',
                'timeOff',
            ]);

        $subject = filled($filters['subject'] ?? null)
            ? Subject::query()->where('slug', $filters['subject'])->first()
            : null;

        $minRate = $this->toMinor($filters['min_rate'] ?? null);
        $maxRate = $this->toMinor($filters['max_rate'] ?? null);
        $gradeId = filled($filters['grade'] ?? null) ? (int) $filters['grade'] : null;

        if ($subject) {
            $rateExpression = $this->subjectRateExpression($gradeId);

            $query->whereHas('subjects', function (Builder $relation) use ($subject, $rateExpression, $minRate, $maxRate) {
                $relation->where('subjects.id', $subject->id);

                if ($minRate !== null) {
                    $relation->whereRaw("{$rateExpression} >= ?", [$minRate]);
                }

                if ($maxRate !== null) {
                    $relation->whereRaw("{$rateExpression} <= ?", [$maxRate]);
                }
            });
        } else {
            if ($minRate !== null) {
                $query->where('hourly_rate_minor', '>=', $minRate);
            }

            if ($maxRate !== null) {
                $query->where('hourly_rate_minor', '<=', $maxRate);
            }
        }

        if (filled($filters['level'] ?? null)) {
            $query->whereHas('subjects', fn (Builder $relation) => $relation
                ->where('subjects.is_active', true)
                ->whereHas('educationLevel', fn (Builder $level) => $level->where('key', $filters['level'])));
        }

        if (filled($filters['lesson'] ?? null)) {
            $query->whereHas('lessons', fn (Builder $relation) => $relation->where('lessons.id', $filters['lesson']));
        }

        if (filled($filters['grade'] ?? null)) {
            // grade_levels is a JSON array of grade ids on the pivot; a quoted
            // LIKE keeps the match exact.
            $query->whereHas('subjects', fn (Builder $relation) => $relation
                ->where('teacher_subjects.grade_levels', 'like', '%"'.$filters['grade'].'"%'));
        }

        if (filled($filters['language'] ?? null)) {
            $query->whereJsonContains('languages', $filters['language']);
        }

        if (filled($filters['weekday'] ?? null)) {
            $from = filled($filters['time_from'] ?? null)
                ? TeacherAvailabilitySlot::toMinute($filters['time_from'])
                : null;
            $to = filled($filters['time_to'] ?? null)
                ? TeacherAvailabilitySlot::toMinute($filters['time_to'])
                : null;

            $query->whereHas('availabilitySlots', function (Builder $relation) use ($filters, $from, $to) {
                $relation->where('day_of_week', (int) $filters['weekday']);

                if ($from !== null) {
                    $relation->where('end_minute', '>', $from);
                }

                if ($to !== null) {
                    $relation->where('start_minute', '<', $to);
                }
            });
        }

        return $this->applySort($query, $filters['sort'] ?? 'rating', $subject, $gradeId);
    }

    /**
     * @param  Builder<TeacherProfile>  $query
     * @return Builder<TeacherProfile>
     */
    private function applySort(Builder $query, string $sort, ?Subject $subject, ?int $gradeId): Builder
    {
        return match ($sort) {
            'price_low' => $this->applyPriceSort($query, 'asc', $subject, $gradeId),
            'price_high' => $this->applyPriceSort($query, 'desc', $subject, $gradeId),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $this->applyRatingSort($query, $gradeId),
        };
    }

    /**
     * Order by the rate the cards actually show. Without a subject filter that
     * is the base rate; with one, the pivot rate (and grade rate) is read
     * through a correlated subquery because the pivot only appears inside the
     * whereHas subquery.
     *
     * @param  Builder<TeacherProfile>  $query
     * @return Builder<TeacherProfile>
     */
    private function applyPriceSort(Builder $query, string $direction, ?Subject $subject, ?int $gradeId): Builder
    {
        if ($subject === null) {
            return $query->orderBy('hourly_rate_minor', $direction)->orderByDesc('id');
        }

        $expression = $this->subjectRateExpression($gradeId);

        return $query
            ->orderByRaw(
                "(select {$expression} from teacher_subjects where teacher_subjects.teacher_profile_id = teacher_profiles.id and teacher_subjects.subject_id = ?) {$direction}",
                [$subject->id],
            )
            ->orderByDesc('id');
    }

    /**
     * A grade rate wins, then the subject override, then the base rate. The
     * grade id is interpolated as an integer JSON path key, so the expression
     * can be reused inside whereHas closures and sort subqueries.
     */
    private function subjectRateExpression(?int $gradeId): string
    {
        if ($gradeId === null) {
            return 'coalesce(teacher_subjects.rate_per_hour_minor, teacher_profiles.hourly_rate_minor)';
        }

        return "coalesce(json_extract(teacher_subjects.grade_rates, '$.\"{$gradeId}\"'), teacher_subjects.rate_per_hour_minor, teacher_profiles.hourly_rate_minor)";
    }

    /**
     * Top rated first; when a grade filter is active, teachers who actually
     * teach that grade float above teachers who only cleared the subject pivot.
     *
     * @param  Builder<TeacherProfile>  $query
     * @return Builder<TeacherProfile>
     */
    private function applyRatingSort(Builder $query, ?int $gradeId): Builder
    {
        if ($gradeId !== null) {
            $query->orderByRaw(
                'exists (select 1 from teacher_lessons inner join lessons on lessons.id = teacher_lessons.lesson_id '
                .'where teacher_lessons.teacher_profile_id = teacher_profiles.id and lessons.grade_id = ?) desc',
                [$gradeId],
            );
        }

        return $query->orderByRaw('rating_avg is null')
            ->orderByDesc('rating_avg')
            ->orderByDesc('rating_count')
            ->orderByDesc('id');
    }

    private function toMinor(mixed $rate): ?int
    {
        return filled($rate) ? (int) round(((float) $rate) * 100) : null;
    }
}
