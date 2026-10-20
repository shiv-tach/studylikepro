<?php

namespace App\Support;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\SubjectBasket;
use Illuminate\Support\Str;

/**
 * The JSON islands behind the public catalog: the page island the browser
 * boots from and the smaller state the catalog swaps in when a visitor picks a
 * level or grade. Both are built from the same state array so a grade link and
 * a fetched grade can never disagree.
 */
final class PublicCatalogConfig
{
    /**
     * The full island embedded in the catalog page.
     *
     * @param  array<string, mixed>  $state
     */
    public static function build(array $state): array
    {
        return [
            'appName' => config('app.name', 'Studylikepro'),
            'authenticated' => auth()->check(),
            'copy' => self::copy($state),
            'levels' => self::levelCards($state),
            'grades' => self::gradeCards($state),
            'level' => self::levelContext($state['level']),
            'grade' => self::gradeContext($state['grade']),
            'subjectGroups' => self::subjectGroups($state),
            'groupedByBasket' => $state['groupedByBasket'],
            'urls' => self::urls(),
            'support' => self::support(),
            'images' => self::images(),
        ];
    }

    /**
     * The slice the catalog fetches when the visitor changes level or grade.
     *
     * @param  array<string, mixed>  $state
     */
    public static function state(array $state, string $url, string $documentTitle): array
    {
        $groups = self::subjectGroups($state);
        $levels = self::levelCards($state);
        $grades = self::gradeCards($state);

        return [
            'url' => $url,
            'documentTitle' => $documentTitle,
            'levels' => $levels,
            'level' => self::levelContext($state['level']),
            'grade' => self::gradeContext($state['grade']),
            'grades' => $grades,
            'subjectGroups' => $groups,
            'groupedByBasket' => $state['groupedByBasket'],
            'basketNote' => self::basketNote($state),
            'counts' => self::counts($groups),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    private static function levelCards(array $state): array
    {
        /** @var \Illuminate\Support\Collection<int, EducationLevel> $levels */
        $levels = $state['levels'];
        $selected = $state['level'];

        return $levels->map(fn (EducationLevel $level) => [
            'key' => $level->key,
            'name' => $level->name,
            'icon' => $level->icon ?? '📘',
            'gradeRange' => $level->grade_min !== null ? $level->gradeRangeLabel() : null,
            'gradeCount' => $level->grades->count(),
            'selected' => $selected?->id === $level->id,
            'url' => route('catalog.subjects.index', ['level' => $level->key]),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    private static function gradeCards(array $state): array
    {
        /** @var EducationLevel|null $level */
        $level = $state['level'];
        /** @var Grade|null $grade */
        $grade = $state['grade'];

        if ($level === null) {
            return [];
        }

        return $level->grades->map(fn (Grade $option) => [
            'id' => $option->id,
            'levelKey' => $level->key,
            'label' => $option->label,
            'selected' => $grade?->id === $option->id,
            'url' => route('catalog.subjects.index', ['level' => $level->key, 'grade' => $option->id]),
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $state
     * @return list<array<string, mixed>>
     */
    private static function subjectGroups(array $state): array
    {
        /** @var \Illuminate\Support\Collection<int, array{basket: SubjectBasket|null, subjects: \Illuminate\Support\Collection<int, \App\Models\Subject>}> $groups */
        $groups = $state['subjectGroups'];
        /** @var Grade|null $grade */
        $grade = $state['grade'];
        $groupedByBasket = $state['groupedByBasket'];

        return $groups->map(function (array $group) use ($grade, $groupedByBasket) {
            $basket = $group['basket'];

            return [
                'key' => $basket?->id !== null ? 'basket-'.$basket->id : 'compulsory',
                // Group headers only exist in a basket grade; the flat grid
                // renders its cards without a title or badge.
                'title' => $groupedByBasket
                    ? ($basket === null ? __('Compulsory subjects') : trim(($basket->icon ?? '').' '.$basket->name))
                    : null,
                'badge' => $groupedByBasket
                    ? ($basket === null ? __('all students') : __('pick one'))
                    : null,
                'pickOne' => $groupedByBasket && $basket !== null,
                'description' => $groupedByBasket ? $basket?->description : null,
                'subjects' => $group['subjects']->map(fn ($subject) => [
                    'name' => $subject->name,
                    'icon' => $subject->icon ?? '📘',
                    'description' => $subject->description,
                    'lessonCount' => $subject->lessons->count(),
                    'lessonLabel' => $subject->lessons->count().' '.Str::plural('lesson', $subject->lessons->count()),
                    'url' => route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade->id]),
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function levelContext(?EducationLevel $level): ?array
    {
        return $level === null ? null : [
            'key' => $level->key,
            'name' => $level->name,
            'url' => route('catalog.subjects.index', ['level' => $level->key]),
        ];
    }

    private static function gradeContext(?Grade $grade): ?array
    {
        return $grade === null ? null : [
            'id' => $grade->id,
            'label' => $grade->label,
        ];
    }

    /**
     * The paragraph above the grid only appears when the grade runs baskets:
     * grouped by basket it names all three, flat it says when they begin.
     *
     * @param  array<string, mixed>  $state
     */
    private static function basketNote(array $state): ?string
    {
        /** @var Grade|null $grade */
        $grade = $state['grade'];
        /** @var \Illuminate\Support\Collection<int, SubjectBasket> $baskets */
        $baskets = $state['baskets'];

        if ($grade === null) {
            return null;
        }

        return match (true) {
            $state['groupedByBasket'] => __('For the O/L exam students take the compulsory subjects and pick one subject from each basket (:baskets). Pick a subject to see its lessons and teachers.', [
                'baskets' => $baskets->pluck('name')->implode(', '),
            ]),
            $baskets->isNotEmpty() => __('Every subject below is compulsory in :grade; the optional baskets (:baskets) begin in :grades.', [
                'grade' => $grade->label,
                'baskets' => $baskets->pluck('name')->implode(', '),
                'grades' => $baskets->map(fn (SubjectBasket $basket) => $basket->gradeRangeLabel())->filter()->unique()->implode(', '),
            ]),
            default => null,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $groups
     * @return array{subjects: int, lessons: int}
     */
    private static function counts(array $groups): array
    {
        $subjects = 0;
        $lessons = 0;

        foreach ($groups as $group) {
            foreach ($group['subjects'] as $subject) {
                $subjects++;
                $lessons += (int) $subject['lessonCount'];
            }
        }

        return ['subjects' => $subjects, 'lessons' => $lessons];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function copy(array $state): array
    {
        $copy = [
            'eyebrow' => __('Subjects & grades'),
            'title' => __('Explore subjects'),
            'intro' => __('Browse the curriculum our verified teachers cover — pick your level, then your grade, to see its subjects.'),
            'levelStep' => __('Choose your level'),
            'catalogEmpty' => __('The catalog is being prepared. Check back soon!'),
            'viewLessons' => __('View lessons'),
            'allLevels' => __('View all levels'),
            'levelCta' => __('View grades'),
            'allGrades' => __('All grades'),
            'navSubjects' => __('Subjects'),
            'navTeachers' => __('Teachers'),
            'navContact' => __('Contact'),
            'ctaTitle' => __('Ready to start learning?'),
            'ctaBody' => __('Create a free student account to book live 1-on-1 lessons with verified teachers.'),
            'ctaStudent' => __('Get started free'),
            'ctaTeacher' => __('Apply as a teacher'),
            'signIn' => __('Log in'),
            // Explorer chrome: the sticky bar, the result summary and the
            // in-between states while a grade loads.
            'explorerLabel' => __('Browse by level and grade'),
            'resultsTitle' => __('Subjects'),
            'subjectsWord' => __('subjects'),
            'lessonsWord' => __('lessons'),
            'chooseGrade' => __('Pick a grade above to see its subjects.'),
            'loadingSubjects' => __('Loading subjects…'),
            'loadFailed' => __('We could not load this grade. Please try again.'),
            'retry' => __('Try again'),
            'basketNote' => self::basketNote($state),
        ];

        if ($state['level'] !== null) {
            $copy['gradeStep'] = __('Choose your grade');
            $copy['gradeEmpty'] = __('Grades for :level are being prepared.', ['level' => $state['level']->name]);
        }

        if ($state['grade'] !== null) {
            $copy['subjectStep'] = __('Pick a subject');
            $copy['subjectsEmpty'] = __('No subjects for :level :grade yet — check back soon!', [
                'level' => $state['level']->name,
                'grade' => $state['grade']->label,
            ]);
        }

        return $copy;
    }

    /**
     * @return array<string, string>
     */
    private static function urls(): array
    {
        return [
            'home' => route('home'),
            'subjects' => route('catalog.subjects.index'),
            'teachers' => route('teachers.index'),
            'contact' => route('legal.contact'),
            'login' => route('login'),
            'registerStudent' => route('register', ['role' => 'student']),
            'registerTeacher' => route('register', ['role' => 'teacher']),
            'dashboard' => route('dashboard'),
            'privacy' => route('legal.privacy'),
            'terms' => route('legal.terms'),
            'refunds' => route('legal.refunds'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function support(): array
    {
        return [
            'email' => config('studylikepro.support.email'),
            'phone' => config('studylikepro.support.phone'),
            'hours' => config('studylikepro.support.hours'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function images(): array
    {
        return [
            'logo' => asset('images/brand/logo-mark.png'),
        ];
    }
}
