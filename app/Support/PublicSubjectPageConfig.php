<?php

namespace App\Support;

use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The JSON island behind the public subject page: the landing design's Vue app
 * renders it, and the catalog fetches the very same shape when a visitor opens
 * a subject by click — so the page swaps in without a reload.
 */
final class PublicSubjectPageConfig
{
    /**
     * The subject page payload, built from {@see SubjectPageService::payload()}.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function build(Subject $subject, array $payload): array
    {
        $level = $payload['level'] ?? null;
        $grade = $payload['grade'] ?? null;
        $lessons = $payload['lessons'];
        $teachers = $payload['teachers'];
        $teacherTotal = (int) $payload['teacherTotal'];
        $nextSlots = $payload['nextSlots'];
        $offeringGroups = $payload['offeringGroups'];
        $gradesById = $payload['gradesById'];
        $gradeOptions = $payload['gradeOptions'] ?? collect();

        $user = auth()->user();
        $isStudent = $user?->isStudent() ?? false;

        $money = static fn (int $minor) => platform_settings()->formatMinor($minor);

        $viewerTz = $user?->studentProfile?->timezone
            ?? $user?->teacherProfile?->timezone
            ?? config('studylikepro.default_display_timezone');

        $teacherUrl = static fn ($teacher) => $isStudent
            ? route('student.teachers.show', $teacher)
            : route('teachers.show', $teacher);

        $directoryUrl = $isStudent
            ? route('student.teachers.index', array_filter(['subject' => $subject->slug]))
            : route('teachers.index', array_filter(['subject' => $subject->slug, 'grade' => $grade?->id]));

        $allGradesUrl = $isStudent
            ? route('student.subjects.show', $subject)
            : route('catalog.subjects.show', $subject);

        // Where "back to Grade X" lands: the catalog with both filters set, so
        // the explorer reopens on the exact grid the visitor came from.
        $catalogUrl = $level !== null
            ? route('catalog.subjects.index', array_filter([
                'level' => $level->key,
                'grade' => $grade?->id,
            ]))
            : route('catalog.subjects.index');

        $serviceGroups = $offeringGroups->map(fn (array $group) => [
            'key' => $group['type']->value,
            'icon' => $group['type']->icon(),
            'label' => $group['type']->pluralLabel(),
            'items' => $group['offerings']->map(fn ($offering) => [
                'id' => $offering->id,
                'title' => $offering->title,
                'price' => $money($offering->price_minor),
                'teacher' => [
                    'name' => $offering->teacherProfile->user->name,
                    'initials' => (string) Str::of($offering->teacherProfile->user->name)->substr(0, 2),
                    'avatarUrl' => $offering->teacherProfile->user->avatarUrl(),
                    'url' => $teacherUrl($offering->teacherProfile),
                ],
                'chips' => array_values(array_filter([
                    $offering->grade ? '🎓 '.$offering->grade->label : null,
                    $offering->lesson?->name,
                    $group['type'] === ServiceType::Lesson && $offering->allocation_minutes
                        ? $offering->allocation_minutes.' '.__('min total')
                        : null,
                    $group['type']->isLiveSession() && $offering->default_session_minutes
                        ? $offering->default_session_minutes.' '.__('min sessions')
                        : null,
                ])),
                'action' => self::serviceAction($offering, $group['type'], $teacherUrl, $user),
            ])->values()->all(),
        ])->values()->all();

        $teacherCards = $teachers->map(function ($teacher) use ($subject, $grade, $gradesById, $nextSlots, $viewerTz, $money, $teacherUrl, $user) {
            $ratingCount = (int) $teacher->rating_count;
            $gradeScope = $teacher->gradeScopeLabelFor($subject, $gradesById);
            $lessonCount = (int) ($teacher->lessons_count ?? 0);

            $cardRate = $grade !== null
                ? $teacher->effectiveRateFor($subject, $grade->id)
                : $teacher->cheapestRateFor($subject);

            $nextSlot = $nextSlots[$teacher->id] ?? null;

            $meta = array_values(array_filter([
                $gradeScope,
                $lessonCount > 0 ? $lessonCount.' '.Str::plural('lesson', $lessonCount) : null,
            ]));

            return [
                'id' => $teacher->id,
                'name' => $teacher->user->name,
                'initials' => (string) Str::of($teacher->user->name)->substr(0, 2),
                'avatarUrl' => $teacher->user->avatarUrl(),
                'url' => $teacherUrl($teacher),
                'headline' => $teacher->headline,
                'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
                'ratingCount' => $ratingCount,
                'metaLabel' => $meta === [] ? null : implode(' · ', $meta),
                'rate' => $money($cardRate),
                'rateFrom' => $grade === null && $cardRate < $teacher->effectiveRateFor($subject),
                'nextSlot' => $nextSlot
                    ? $nextSlot['starts_at']->setTimezone($viewerTz)->format('D d M · '.TimeFormat::TIME)
                    : null,
                // Guests are pointed at sign-up, a signed-in teacher or admin at
                // the profile itself, and only a student who may book gets the
                // booking CTA.
                'cta' => match (true) {
                    $user === null => [
                        'label' => __('Book now'),
                        'url' => route('register', ['role' => 'student']),
                    ],
                    $user->can('create', [Booking::class, $teacher]) => [
                        'label' => __('Book now'),
                        'url' => route('student.bookings.create', [
                            'teacherProfile' => $teacher,
                            'subject_id' => $subject->id,
                        ]),
                    ],
                    default => [
                        'label' => __('View profile'),
                        'url' => null,
                    ],
                },
            ];
        })->values()->all();

        $lessonGroups = $lessons
            ->groupBy(fn ($lesson) => $lesson->grade?->label ?? __('Other'))
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'countLabel' => $group->count().' '.Str::plural('lesson', $group->count()),
                'items' => $group->map(fn ($lesson) => [
                    'id' => $lesson->id,
                    'name' => $lesson->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $copy = [
            'navSubjects' => __('Explore subjects'),
            'navTeachers' => __('Teachers'),
            'navContact' => __('Contact'),
            'home' => __('Home'),
            'pickOne' => __('pick one'),
            'viewAllGrades' => __('View all grades'),
            'gradeSwitcherLabel' => __('Switch grade'),
            'backToGrade' => $grade ? __('Back to :grade', ['grade' => $grade->label]) : null,
            'sectionsLabel' => __('On this page'),
            'servicesTab' => __('Services'),
            'teachersTab' => __('Teachers'),
            'lessonsTab' => __('Lessons'),
            'servicesTitle' => $grade
                ? __('Services for :subject in :grade', ['subject' => $subject->name, 'grade' => $grade->label])
                : __('Services for :subject', ['subject' => $subject->name]),
            'servicesIntro' => __('Ready-made packages from verified teachers — buy one, or open a teacher to book a live lesson.'),
            'servicesEmpty' => __('No ready-made services for this subject yet — book a verified teacher for a live lesson instead.'),
            'browseTeachers' => __('Browse teachers'),
            'teachersTitle' => $grade
                ? __('Teachers who teach :subject in :grade', ['subject' => $subject->name, 'grade' => $grade->label])
                : __('Teachers who teach :subject', ['subject' => $subject->name]),
            'teachersAvailable' => __(':count verified teachers available', ['count' => $teacherTotal]),
            'seeAllTeachers' => __('See all :count teachers', ['count' => $teacherTotal]),
            'teachersEmpty' => $grade
                ? __('No verified teachers yet for :subject in :grade — we are onboarding more every week.', ['subject' => $subject->name, 'grade' => $grade->label])
                : __('No verified teachers yet for :subject — we are onboarding more every week.', ['subject' => $subject->name]),
            'browseAllTeachers' => __('Browse all teachers'),
            'newTeacher' => __('New teacher'),
            'verified' => __('Verified'),
            'rateFrom' => __('from'),
            'perHour' => __('/hr'),
            'nextLabel' => __('Next:'),
            'noSlots' => __('No slots in the next 7 days'),
            'lessonsTitle' => __('Lessons'),
            'lessonsEmptyForGrade' => __('No :grade lessons for this subject yet.', ['grade' => $grade?->label]),
            'lessonsEmpty' => __('Lessons for this subject are being prepared.'),
            'ctaTitle' => __('Want help with :subject?', ['subject' => $subject->name]),
            'ctaBody' => __('Upload a question and we will match you with a verified teacher for a live lesson.'),
            'ctaGuest' => __('Start learning free'),
            'ctaDashboard' => __('Go to my dashboard'),
            'loading' => __('Loading…'),
        ];

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'authenticated' => $user !== null,
            'csrfToken' => csrf_token(),
            'documentTitle' => config('app.name', 'Studylikepro').' — '.$subject->name,
            'subject' => [
                'name' => $subject->name,
                'icon' => $subject->icon ?? '📘',
                'description' => $subject->description,
                'lessonLabel' => $lessons->count().' '.Str::plural('lesson', $lessons->count()),
                'basketLabel' => $subject->basket
                    ? trim(($subject->basket->icon ?? '').' '.$subject->basket->name)
                    : null,
                'grade' => $grade ? [
                    'label' => $grade->label,
                    'chip' => $level?->name ? $level->name.' · '.$grade->label : $grade->label,
                    'allGradesUrl' => $allGradesUrl,
                    'catalogUrl' => $catalogUrl,
                ] : null,
                // Every grade the subject runs in, for the in-hero switcher.
                'grades' => $gradeOptions->map(fn (Grade $option) => [
                    'id' => $option->id,
                    'label' => $option->label,
                    'selected' => $grade?->id === $option->id,
                    'url' => $isStudent
                        ? route('student.subjects.show', ['subject' => $subject, 'grade' => $option->id])
                        : route('catalog.subjects.show', ['subject' => $subject, 'grade' => $option->id]),
                ])->values()->all(),
            ],
            'level' => $level ? [
                'key' => $level->key,
                'name' => $level->name,
                'url' => route('catalog.subjects.index', ['level' => $level->key]),
            ] : null,
            'breadcrumbs' => [
                ['label' => __('Home'), 'url' => route('home')],
                ['label' => __('Explore subjects'), 'url' => route('catalog.subjects.index')],
            ],
            'copy' => $copy,
            'serviceGroups' => $serviceGroups,
            'teachers' => [
                'items' => $teacherCards,
                'total' => $teacherTotal,
                'directoryUrl' => $directoryUrl,
                'directoryRootUrl' => route('teachers.index'),
            ],
            'lessonGroups' => $lessonGroups,
            'urls' => [
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
            ],
            'support' => [
                'email' => config('studylikepro.support.email'),
                'phone' => config('studylikepro.support.phone'),
                'hours' => config('studylikepro.support.hours'),
            ],
            'images' => [
                'logo' => asset('images/brand/logo-mark.png'),
            ],
        ];
    }

    /**
     * What the card's button does: buy the service, start a marking order, open
     * the teacher, or invite the guest to sign up first.
     */
    private static function serviceAction($offering, ServiceType $type, callable $teacherUrl, $user): array
    {
        return match (true) {
            $user === null => [
                'kind' => 'link',
                'variant' => 'primary',
                'label' => __('Sign up to buy'),
                'url' => route('register', ['role' => 'student']),
            ],
            ! $user->can('buy', $offering) => [
                'kind' => 'link',
                'variant' => 'outline',
                'label' => __('View teacher'),
                'url' => $teacherUrl($offering->teacherProfile),
            ],
            $type === ServiceType::PaperMarking => [
                'kind' => 'link',
                'variant' => 'primary',
                'label' => __('Submit a paper'),
                'url' => route('student.marking.create', $offering),
            ],
            default => [
                'kind' => 'form',
                'variant' => 'primary',
                'label' => __('Buy now'),
                'url' => route('student.purchases.store', $offering),
            ],
        };
    }
}
