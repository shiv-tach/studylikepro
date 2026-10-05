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

        if ($subject) {
            $query->whereHas('subjects', function (Builder $relation) use ($subject, $minRate, $maxRate) {
                $relation->where('subjects.id', $subject->id);

                if ($minRate !== null) {
                    $relation->whereRaw('coalesce(teacher_subjects.rate_per_hour_minor, teacher_profiles.hourly_rate_minor) >= ?', [$minRate]);
                }

                if ($maxRate !== null) {
                    $relation->whereRaw('coalesce(teacher_subjects.rate_per_hour_minor, teacher_profiles.hourly_rate_minor) <= ?', [$maxRate]);
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

        if (filled($filters['topic'] ?? null)) {
            $query->whereHas('topics', fn (Builder $relation) => $relation->where('topics.slug', $filters['topic']));
        }

        if (filled($filters['grade_level'] ?? null)) {
            // grade_levels is a JSON array on the pivot; a quoted LIKE keeps the match exact.
            $query->whereHas('subjects', fn (Builder $relation) => $relation
                ->where('teacher_subjects.grade_levels', 'like', '%"'.$filters['grade_level'].'"%'));
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

        return $this->applySort($query, $filters['sort'] ?? 'rating');
    }

    /**
     * @param  Builder<TeacherProfile>  $query
     * @return Builder<TeacherProfile>
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'price_low' => $query->orderBy('hourly_rate_minor')->orderByDesc('id'),
            'price_high' => $query->orderByDesc('hourly_rate_minor')->orderByDesc('id'),
            'newest' => $query->orderByDesc('created_at')->orderByDesc('id'),
            default => $query->orderByRaw('rating_avg is null')
                ->orderByDesc('rating_avg')
                ->orderByDesc('rating_count')
                ->orderByDesc('id'),
        };
    }

    private function toMinor(mixed $rate): ?int
    {
        return filled($rate) ? (int) round(((float) $rate) * 100) : null;
    }
}
