<?php

namespace App\Services;

use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The public catalog (active subjects with their active topics) is read on
 * nearly every page — the directory filters, the interest pickers, the request
 * form and the admin matcher all need it. It changes when an admin edits it, so
 * it is cached until a subject or topic is written.
 */
class CatalogService
{
    public const CACHE_KEY = 'studylikepro:catalog:subjects';

    public const COUNT_CACHE_KEY = 'studylikepro:catalog:subject-counts';

    /**
     * Active subjects, ordered, each with its active topics.
     *
     * @return Collection<int, Subject>
     */
    public function subjects(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Subject::query()
            ->active()
            ->ordered()
            ->with(['topics' => fn ($query) => $query->active()->ordered()])
            ->get());
    }

    /**
     * The same list with a count of active topics, for the catalog overview.
     *
     * @return Collection<int, Subject>
     */
    public function subjectsWithTopicCounts(): Collection
    {
        return Cache::rememberForever(self::COUNT_CACHE_KEY, fn () => Subject::query()
            ->active()
            ->ordered()
            ->withCount(['topics' => fn ($query) => $query->active()])
            ->get());
    }

    /**
     * Active topics of one subject, taken from the cached list when it is there.
     */
    public function topicsFor(Subject $subject): Collection
    {
        $cached = $this->subjects()->firstWhere('id', $subject->id);

        return $cached?->topics ?? $subject->topics()->active()->ordered()->get();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::COUNT_CACHE_KEY);
    }
}
