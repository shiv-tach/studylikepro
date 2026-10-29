<?php

namespace App\Support;

use App\Enums\ServiceType;
use App\Models\EducationLevel;
use App\Models\Subject;

/**
 * The invite survey: the questions a guest answers before the account exists
 * (see `PendingTeacherSignup` and `TeacherSurveyController`).
 *
 * Defined once so the rail, the step gates, the review page and the service that
 * writes the answers to the profile cannot describe the flow differently.
 *
 * Order: 1 tutoring level -> 2 subjects -> 3 teaching languages -> 4 services
 * -> 5 years of experience -> 6 education -> 7 rates -> 8 teaching days
 * -> 9 government-teacher proof -> 10 your account (identity, password, SMS code).
 */
final class TeacherSurvey
{
    public const STEP_LEVEL = 'level';

    public const STEP_SUBJECTS = 'subjects';

    public const STEP_LANGUAGES = 'languages';

    public const STEP_SERVICES = 'services';

    public const STEP_EXPERIENCE = 'experience';

    public const STEP_EDUCATION = 'education';

    public const STEP_RATES = 'rates';

    public const STEP_AVAILABILITY = 'availability';

    public const STEP_PROOF = 'proof';

    /** "I tutor both O/L and A/L" — the third card on the first question. */
    public const LEVEL_BOTH = 'both';

    /** The answers of the "where did you study" question. */
    public const EDUCATION_GOVERNMENT_UNIVERSITY = 'government_university';

    public const EDUCATION_PRIVATE_UNIVERSITY = 'private_university';

    public const EDUCATION_NATIONAL_COLLEGE = 'national_college_of_education';

    public const EDUCATION_OTHER = 'other';

    /** The questions, in order. The account step is not an "answer". */
    public const ANSWER_STEPS = [
        self::STEP_LEVEL,
        self::STEP_SUBJECTS,
        self::STEP_LANGUAGES,
        self::STEP_SERVICES,
        self::STEP_EXPERIENCE,
        self::STEP_EDUCATION,
        self::STEP_RATES,
        self::STEP_AVAILABILITY,
        self::STEP_PROOF,
    ];

    /** Rail group numbers, so two questions can share one segment. */
    public const GROUP_TEACHING = 1;

    public const GROUP_PREFERENCES = 2;

    public const GROUP_BACKGROUND = 3;

    public const GROUP_SCHEDULE = 4;

    public const GROUP_PROOF = 5;

    public const GROUP_ACCOUNT = 6;

    /**
     * The progress rail. The questions are grouped into segments: a phone
     * cannot read nine labels, and pairs such as (level, subjects) are one
     * idea anyway.
     *
     * @param  array<string, mixed>  $answers
     * @return list<array{key: string, number: int, label: string, hint: string, url: string, done: bool, reachable: bool}>
     */
    public static function steps(array $answers = []): array
    {
        $levelDone = self::answered($answers, self::STEP_LEVEL);
        $subjectsDone = self::answered($answers, self::STEP_SUBJECTS);
        $languagesDone = self::answered($answers, self::STEP_LANGUAGES);
        $servicesDone = self::answered($answers, self::STEP_SERVICES);
        $experienceDone = self::answered($answers, self::STEP_EXPERIENCE);
        $educationDone = self::answered($answers, self::STEP_EDUCATION);
        $ratesDone = self::answered($answers, self::STEP_RATES);
        $availabilityDone = self::answered($answers, self::STEP_AVAILABILITY);
        $proofDone = self::answered($answers, self::STEP_PROOF);

        $teachingDone = $levelDone && $subjectsDone;
        $preferencesDone = $languagesDone && $servicesDone;
        $backgroundDone = $experienceDone && $educationDone;
        $scheduleDone = $ratesDone && $availabilityDone;

        return self::withProgress([
            [
                'key' => 'teaching',
                'number' => self::GROUP_TEACHING,
                'label' => __('What you teach'),
                'hint' => __('Your level — O/L, A/L or both — and the subjects you tutor.'),
                'url' => route($levelDone ? 'join.subjects' : 'join.level'),
                'done' => $teachingDone,
                'reachable' => true,
            ],
            [
                'key' => 'preferences',
                'number' => self::GROUP_PREFERENCES,
                'label' => __('How you teach'),
                'hint' => __('The languages you teach in and the services you want to offer.'),
                'url' => route($languagesDone ? 'join.services' : 'join.languages'),
                'done' => $preferencesDone,
                'reachable' => $teachingDone,
            ],
            [
                'key' => 'background',
                'number' => self::GROUP_BACKGROUND,
                'label' => __('Your background'),
                'hint' => __('Your years of teaching and where you studied.'),
                'url' => route($experienceDone ? 'join.education' : 'join.experience'),
                'done' => $backgroundDone,
                'reachable' => $preferencesDone,
            ],
            [
                'key' => 'schedule',
                'number' => self::GROUP_SCHEDULE,
                'label' => __('Rates & days'),
                'hint' => __('What you charge and the days you can teach.'),
                'url' => route($ratesDone ? 'join.availability' : 'join.rates'),
                'done' => $scheduleDone,
                'reachable' => $backgroundDone,
            ],
            [
                'key' => 'proof',
                'number' => self::GROUP_PROOF,
                'label' => __('Government proof'),
                'hint' => __('Optional: prove you are a government teacher and earn a profile badge.'),
                'url' => route('join.proof'),
                'done' => $proofDone,
                'reachable' => $scheduleDone,
            ],
            [
                'key' => 'account',
                'number' => self::GROUP_ACCOUNT,
                'label' => __('Your account'),
                'hint' => __('Your name, email, mobile number and password — confirmed with an SMS code.'),
                'url' => route('join.account'),
                'done' => false,
                'reachable' => self::isComplete($answers),
            ],
        ], $answers);
    }

    /**
     * The questions that make up each rail segment, so the rail can fill a
     * segment half-way while the account step still gets one honest segment.
     *
     * @var array<string, list<string>>
     */
    private const GROUP_QUESTIONS = [
        'teaching' => [self::STEP_LEVEL, self::STEP_SUBJECTS],
        'preferences' => [self::STEP_LANGUAGES, self::STEP_SERVICES],
        'background' => [self::STEP_EXPERIENCE, self::STEP_EDUCATION],
        'schedule' => [self::STEP_RATES, self::STEP_AVAILABILITY],
        'proof' => [self::STEP_PROOF],
        'account' => [],
    ];

    /**
     * Add `count`, `answered` and `progress` to every rail segment.
     *
     * @param  list<array<string, mixed>>  $steps
     * @param  array<string, mixed>  $answers
     * @return list<array<string, mixed>>
     */
    private static function withProgress(array $steps, array $answers): array
    {
        return array_map(function (array $step) use ($answers): array {
            $keys = self::GROUP_QUESTIONS[$step['key']] ?? [];
            $count = count($keys);
            $answered = count(array_filter($keys, fn (string $key) => self::answered($answers, $key)));

            $step['count'] = max($count, 1);
            $step['answered'] = $count > 0 ? $answered : (self::isComplete($answers) ? 1 : 0);
            $step['progress'] = $step['answered'] / $step['count'];

            return $step;
        }, $steps);
    }

    /**
     * How many of the nine questions have an answer.
     *
     * @param  array<string, mixed>  $answers
     */
    public static function answeredCount(array $answers): int
    {
        return count(array_filter(
            self::ANSWER_STEPS,
            fn (string $step) => self::answered($answers, $step),
        ));
    }

    /**
     * The one-based position of a question in the survey.
     */
    public static function questionNumber(string $step): int
    {
        $index = array_search($step, self::ANSWER_STEPS, true);

        return $index === false ? count(self::ANSWER_STEPS) : $index + 1;
    }

    /**
     * The readable recap of every answer given so far, grouped like the rail.
     * The survey aside and the "you're in" celebration both read this, so a
     * teacher never sees the same answer written two different ways.
     *
     * @param  array<string, mixed>  $answers
     * @return list<array{key: string, label: string, icon: string, done: bool, items: list<array{key: string, label: string, value: string|null, meta: string|null, url: string|null}>}>
     */
    public static function summary(array $answers = []): array
    {
        $level = self::stored($answers, self::STEP_LEVEL);
        $subjects = self::stored($answers, self::STEP_SUBJECTS);
        $languages = self::stored($answers, self::STEP_LANGUAGES);
        $services = self::stored($answers, self::STEP_SERVICES);
        $experience = self::stored($answers, self::STEP_EXPERIENCE);
        $education = self::stored($answers, self::STEP_EDUCATION);
        $rates = self::stored($answers, self::STEP_RATES);
        $availability = self::stored($answers, self::STEP_AVAILABILITY);
        $proof = self::stored($answers, self::STEP_PROOF);

        $levelValue = (string) ($level['value'] ?? '');
        $subjectIds = array_values(array_unique(array_map('intval', $subjects)));

        $subjectNames = $subjectIds === []
            ? []
            : Subject::query()
                ->whereIn('id', $subjectIds)
                ->orderBy('name')
                ->pluck('name')
                ->all();

        $serviceLabels = collect(self::serviceOptions())->pluck('label', 'value');
        $serviceNames = array_values(array_filter(array_map(
            fn ($value) => $serviceLabels[(string) $value] ?? null,
            $services,
        )));

        $languageNames = array_values(array_filter(array_map('strval', $languages)));

        $years = $experience['value'] ?? null;
        $experienceLabel = $years === null
            ? null
            : ($years === 1 ? __('1 year') : __(':count years', ['count' => $years]));

        $educationLabel = self::educationLabel(
            is_string($education['value'] ?? null) ? $education['value'] : null,
        );

        $hourlyRate = $rates['hourly_rate'] ?? null;
        $serviceRates = is_array($rates['service_rates'] ?? null) ? $rates['service_rates'] : [];

        $hasRates = $hourlyRate !== null || $serviceRates !== [];
        $extraRateCount = count($serviceRates);

        $rateValue = $hourlyRate === null
            ? null
            : platform_settings()->formatMinor((int) round(((float) $hourlyRate) * 100)).' / '.__('hour');

        $rateMeta = $extraRateCount === 0
            ? null
            : __(':count service price(s) set', ['count' => $extraRateCount]);

        $days = array_values(array_unique(array_map('intval', (array) ($availability['days'] ?? []))));
        $dayLabels = collect(self::availabilityDays())->pluck('label', 'value');
        $dayNames = array_values(array_filter(array_map(
            fn (int $day) => $dayLabels[$day] ?? null,
            $days,
        )));

        $presetKey = $availability['preset'] ?? null;
        $preset = is_string($presetKey) && $presetKey !== ''
            ? collect(AvailabilityPresets::options())->firstWhere('key', $presetKey)
            : null;

        $availabilityValue = $preset['label'] ?? ($dayNames === [] ? null : implode(', ', $dayNames));
        $availabilityMeta = $preset !== null && $dayNames !== [] ? implode(', ', $dayNames) : null;

        $claimed = (bool) ($proof['claimed'] ?? false);
        $proofName = is_string($proof['original_name'] ?? null) ? $proof['original_name'] : null;

        $definition = [
            [
                'key' => 'teaching',
                'label' => __('What you teach'),
                'icon' => '🎯',
                'items' => [
                    ['key' => self::STEP_LEVEL, 'label' => __('Exam level'), 'value' => $levelValue === '' ? null : self::levelLabel($levelValue), 'meta' => null, 'url' => route('join.level')],
                    ['key' => self::STEP_SUBJECTS, 'label' => __('Subjects'), 'value' => $subjectNames === [] ? null : implode(', ', $subjectNames), 'meta' => null, 'url' => route('join.subjects')],
                ],
            ],
            [
                'key' => 'preferences',
                'label' => __('How you teach'),
                'icon' => '🗣️',
                'items' => [
                    ['key' => self::STEP_LANGUAGES, 'label' => __('Languages'), 'value' => $languageNames === [] ? null : implode(', ', $languageNames), 'meta' => null, 'url' => route('join.languages')],
                    ['key' => self::STEP_SERVICES, 'label' => __('Services'), 'value' => $serviceNames === [] ? null : implode(', ', $serviceNames), 'meta' => null, 'url' => route('join.services')],
                ],
            ],
            [
                'key' => 'background',
                'label' => __('Your background'),
                'icon' => '🎓',
                'items' => [
                    ['key' => self::STEP_EXPERIENCE, 'label' => __('Experience'), 'value' => $experienceLabel, 'meta' => null, 'url' => route('join.experience')],
                    ['key' => self::STEP_EDUCATION, 'label' => __('Education'), 'value' => $educationLabel, 'meta' => null, 'url' => route('join.education')],
                ],
            ],
            [
                'key' => 'schedule',
                'label' => __('Rates & days'),
                'icon' => '🕒',
                'items' => [
                    ['key' => self::STEP_RATES, 'label' => __('Rates'), 'value' => $hasRates ? $rateValue : null, 'meta' => $rateMeta, 'url' => route('join.rates')],
                    ['key' => self::STEP_AVAILABILITY, 'label' => __('Teaching days'), 'value' => $availabilityValue, 'meta' => $availabilityMeta, 'url' => route('join.availability')],
                ],
            ],
            [
                'key' => 'proof',
                'label' => __('Government proof'),
                'icon' => '🪪',
                'items' => [
                    ['key' => self::STEP_PROOF, 'label' => __('Government teacher'), 'value' => $proof === [] ? null : ($claimed ? __('Yes, with proof') : __('No, I teach privately')), 'meta' => $claimed ? $proofName : null, 'url' => route('join.proof')],
                ],
            ],
        ];

        // A question can only be revisited once every question before it has an
        // answer — the same gate the controllers apply.
        $unlocked = true;

        return array_map(function (array $group) use (&$unlocked): array {
            $group['items'] = array_map(function (array $item) use (&$unlocked): array {
                $reachable = $unlocked;

                if ($item['value'] === null) {
                    $unlocked = false;
                }

                $item['url'] = $reachable ? $item['url'] : null;
                $item['done'] = $item['value'] !== null;

                return $item;
            }, $group['items']);

            $group['done'] = collect($group['items'])->every(fn (array $item) => $item['done']);

            return $group;
        }, $definition);
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<int|string, mixed>
     */
    private static function stored(array $answers, string $step): array
    {
        $value = $answers[$step] ?? null;

        return is_array($value) ? $value : [];
    }

    /**
     * The rail segment a question belongs to.
     */
    public static function groupFor(string $step): int
    {
        return match ($step) {
            self::STEP_LEVEL, self::STEP_SUBJECTS => self::GROUP_TEACHING,
            self::STEP_LANGUAGES, self::STEP_SERVICES => self::GROUP_PREFERENCES,
            self::STEP_EXPERIENCE, self::STEP_EDUCATION => self::GROUP_BACKGROUND,
            self::STEP_RATES, self::STEP_AVAILABILITY => self::GROUP_SCHEDULE,
            self::STEP_PROOF => self::GROUP_PROOF,
            default => self::GROUP_ACCOUNT,
        };
    }

    /**
     * The first question still missing an answer, as a route name.
     *
     * @param  array<string, mixed>  $answers
     */
    public static function nextRouteName(array $answers): string
    {
        foreach (self::ANSWER_STEPS as $step) {
            if (! self::answered($answers, $step)) {
                return match ($step) {
                    self::STEP_LEVEL => 'join.level',
                    self::STEP_SUBJECTS => 'join.subjects',
                    self::STEP_LANGUAGES => 'join.languages',
                    self::STEP_SERVICES => 'join.services',
                    self::STEP_EXPERIENCE => 'join.experience',
                    self::STEP_EDUCATION => 'join.education',
                    self::STEP_RATES => 'join.rates',
                    self::STEP_AVAILABILITY => 'join.availability',
                    default => 'join.proof',
                };
            }
        }

        return 'join.account';
    }

    /**
     * True once every question has an answer (the proof question can be
     * answered with "not a government teacher").
     *
     * @param  array<string, mixed>  $answers
     */
    public static function isComplete(array $answers): bool
    {
        foreach (self::ANSWER_STEPS as $step) {
            if (! self::answered($answers, $step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The education levels a level answer unlocks: `ol`, `al`, or both.
     *
     * @return list<string>
     */
    public static function allowedLevelKeys(?string $answer): array
    {
        return match ($answer) {
            'ol' => ['ol'],
            'al' => ['al'],
            self::LEVEL_BOTH => ['ol', 'al'],
            default => [],
        };
    }

    /**
     * The three cards of the first question. The level names and grade spans are
     * read from the seeded catalog so they cannot drift from the picker.
     *
     * @return list<array{value: string, label: string, hint: string, icon: string}>
     */
    public static function levels(): array
    {
        $levels = EducationLevel::query()->active()->ordered()->get()->keyBy('key');

        $cards = [];

        foreach (['ol', 'al'] as $key) {
            $level = $levels->get($key);

            if ($level === null) {
                continue;
            }

            $cards[] = [
                'value' => $key,
                'label' => self::levelTitle($level),
                'hint' => self::levelHint($level),
                'icon' => (string) ($level->icon ?: '📘'),
            ];
        }

        $cards[] = [
            'value' => self::LEVEL_BOTH,
            'label' => __('Both'),
            'hint' => __('O/L and A/L — every grade'),
            'icon' => '🎯',
        ];

        return $cards;
    }

    /**
     * The four services a teacher can say they want to offer.
     *
     * @return list<array{value: string, label: string, hint: string, icon: string}>
     */
    public static function serviceOptions(): array
    {
        $hints = [
            ServiceType::Lesson->value => __('Live 1-on-1 lessons on a topic or paper.'),
            ServiceType::PaperMarking->value => __('Mark a student’s paper and return it with feedback.'),
            ServiceType::PaperDiscussion->value => __('Walk through a past paper together, live.'),
            ServiceType::Qna->value => __('Answer a student’s questions in a live session.'),
        ];

        $labels = [
            ServiceType::Lesson->value => __('Lessons and tutoring'),
        ];

        return array_map(fn (ServiceType $type) => [
            'value' => $type->value,
            'label' => $labels[$type->value] ?? $type->pluralLabel(),
            'hint' => $hints[$type->value],
            'icon' => $type->icon(),
        ], ServiceType::cases());
    }

    /**
     * The four answers of the "where did you study" question. The value is
     * stored on the profile; the label is what the teacher reads.
     *
     * @return list<array{value: string, label: string, hint: string, icon: string}>
     */
    public static function educationOptions(): array
    {
        return [
            [
                'value' => self::EDUCATION_GOVERNMENT_UNIVERSITY,
                'label' => __('Government university'),
                'hint' => __('A state university such as Colombo, Peradeniya or Kelaniya.'),
                'icon' => '🏛️',
            ],
            [
                'value' => self::EDUCATION_PRIVATE_UNIVERSITY,
                'label' => __('Private university'),
                'hint' => __('A privately run university or degree programme.'),
                'icon' => '🎓',
            ],
            [
                'value' => self::EDUCATION_NATIONAL_COLLEGE,
                'label' => __('National College of Education'),
                'hint' => __('A government teacher-training college.'),
                'icon' => '🏫',
            ],
            [
                'value' => self::EDUCATION_OTHER,
                'label' => __('Other'),
                'hint' => __('Another institute or qualification.'),
                'icon' => '📜',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function educationValues(): array
    {
        return array_map(fn (array $option) => $option['value'], self::educationOptions());
    }

    /**
     * The readable answer, which is what the profile's `education` column holds.
     */
    public static function educationLabel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        foreach (self::educationOptions() as $option) {
            if ($option['value'] === $value) {
                return (string) $option['label'];
            }
        }

        return $value;
    }

    /**
     * The services the rates question has to price: everything the teacher
     * picked except lessons, whose price is the hourly rate itself.
     *
     * @param  array<int, mixed>  $selected
     * @return list<array{value: string, label: string, hint: string, icon: string}>
     */
    public static function pricedServiceOptions(array $selected): array
    {
        $values = array_map('strval', $selected);

        return array_values(array_filter(
            self::serviceOptions(),
            fn (array $option) => $option['value'] !== ServiceType::Lesson->value
                && in_array($option['value'], $values, true),
        ));
    }

    /**
     * The seven day cards, Monday first like the availability manager. The value
     * is the `Carbon` day number the availability slots store.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function availabilityDays(): array
    {
        return [
            ['value' => 1, 'label' => __('Monday')],
            ['value' => 2, 'label' => __('Tuesday')],
            ['value' => 3, 'label' => __('Wednesday')],
            ['value' => 4, 'label' => __('Thursday')],
            ['value' => 5, 'label' => __('Friday')],
            ['value' => 6, 'label' => __('Saturday')],
            ['value' => 0, 'label' => __('Sunday')],
        ];
    }

    public static function levelLabel(string $value): string
    {
        return match ($value) {
            'ol' => __('O/L'),
            'al' => __('A/L'),
            self::LEVEL_BOTH => __('O/L and A/L'),
            default => $value,
        };
    }

    private static function levelTitle(EducationLevel $level): string
    {
        return match ((string) $level->key) {
            'ol' => __('O/L'),
            'al' => __('A/L'),
            default => (string) $level->name,
        };
    }

    private static function levelHint(EducationLevel $level): string
    {
        if ($level->grade_min === null || $level->grade_max === null) {
            return (string) $level->name;
        }

        return __('Grades :min–:max', ['min' => $level->grade_min, 'max' => $level->grade_max]);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private static function answered(array $answers, string $step): bool
    {
        return array_key_exists($step, $answers) && $answers[$step] !== null && $answers[$step] !== [];
    }
}
