<?php

namespace App\Support;

use Illuminate\Support\ViewErrorBag;

/**
 * The JSON island for one screen of the invite survey (`/join/*`).
 *
 * The survey renders in Vue now (`resources/js/join`), so this class owns
 * everything the screen needs: the question copy, the answers to replay, the
 * server's validation messages, the rail, the recap and — per question — only
 * the options that question can actually offer (the subject list is narrowed to
 * the level answered before it, the rates question prices only the services
 * that were picked). Blade keeps rendering the same data inside `<noscript>`,
 * so a browser without JavaScript still posts the identical field names.
 */
final class JoinSurveyConfig
{
    /**
     * @param  array<string, mixed>  $answers
     */
    public static function build(string $step, array $answers, ViewErrorBag $errors): array
    {
        $isProof = $step === TeacherSurvey::STEP_PROOF;
        $presets = [];
        $presetDays = [];
        $dayTimes = [];

        if ($step === TeacherSurvey::STEP_AVAILABILITY) {
            $presets = AvailabilityPresets::options();

            foreach ($presets as $preset) {
                $presetDays[$preset['key']] = array_values(array_map('intval', array_keys($preset['days'])));
            }

            // With no suggested week chosen, every picked day opens on the
            // recommended hours, so the day cards can say which hours those are.
            $defaultWeek = AvailabilityPresets::defaultWeek();

            foreach (TeacherSurvey::availabilityDays() as $day) {
                $dayTimes[$day['value']] = AvailabilityPresets::timeLabel($defaultWeek[$day['value']] ?? []);
            }
        }

        [$plainErrors, $fieldErrors] = self::splitErrors($errors);

        $answered = TeacherSurvey::answeredCount($answers);
        $total = count(TeacherSurvey::ANSWER_STEPS);

        return [
            'step' => $step,
            'isProof' => $isProof,
            'index' => self::index($step),
            'total' => $total,
            'answered' => $answered,
            'percent' => $total > 0 ? (int) round(($answered / $total) * 100) : 0,
            'title' => self::title($step),
            'body' => self::body($step, $answers),
            'currentGroup' => TeacherSurvey::groupFor($step),
            'values' => self::values($step, $answers),
            'errors' => $plainErrors,
            'fieldErrors' => $fieldErrors,
            'errorList' => $errors->any() ? array_values($errors->all()) : [],
            'rail' => TeacherSurvey::steps($answers),
            'summary' => TeacherSurvey::summary($answers),
            'options' => [
                'levels' => TeacherSurvey::levels(),
                'subjectGroups' => $step === TeacherSurvey::STEP_SUBJECTS
                    ? self::subjectGroups(TeacherSurvey::allowedLevelKeys(self::levelAnswer($answers)))
                    : [],
                'languages' => array_values((array) config('studylikepro.languages')),
                'services' => TeacherSurvey::serviceOptions(),
                'educationOptions' => TeacherSurvey::educationOptions(),
                'pricedServices' => $step === TeacherSurvey::STEP_RATES
                    ? TeacherSurvey::pricedServiceOptions((array) ($answers[TeacherSurvey::STEP_SERVICES] ?? []))
                    : [],
                'days' => TeacherSurvey::availabilityDays(),
                'presets' => $presets,
                'presetDays' => $presetDays,
                'dayTimes' => $dayTimes,
            ],
            'limits' => [
                'experienceMin' => 0,
                'experienceMax' => 60,
                'rateMin' => 100,
                'rateMax' => 100000,
                'proofKb' => 5120,
            ],
            'currency' => [
                'symbol' => (string) config('studylikepro.currency_symbol'),
                'code' => (string) config('studylikepro.currency'),
            ],
            'csrfToken' => csrf_token(),
            'urls' => [
                'submit' => route(match ($step) {
                    TeacherSurvey::STEP_LEVEL => 'join.level.update',
                    TeacherSurvey::STEP_SUBJECTS => 'join.subjects.update',
                    TeacherSurvey::STEP_LANGUAGES => 'join.languages.update',
                    TeacherSurvey::STEP_SERVICES => 'join.services.update',
                    TeacherSurvey::STEP_EXPERIENCE => 'join.experience.update',
                    TeacherSurvey::STEP_EDUCATION => 'join.education.update',
                    TeacherSurvey::STEP_RATES => 'join.rates.update',
                    TeacherSurvey::STEP_AVAILABILITY => 'join.availability.update',
                    default => 'join.proof.update',
                }),
                'back' => self::backUrl($step),
                'start' => route('join.start'),
            ],
            'proofName' => $isProof
                ? ($answers[TeacherSurvey::STEP_PROOF]['original_name'] ?? null)
                : null,
            'copy' => self::copy(),
            'questionCopy' => self::questionCopy(),
        ];
    }

    /**
     * `subjects.0` style keys cannot be read per card, so every message keeps
     * its key: the plain keys travel in `errors`, the dotted ones in
     * `fieldErrors` where a card can look up its own id.
     *
     * @return array{0: array<string, list<string>>, 1: array<string, list<string>>}
     */
    public static function splitErrors(ViewErrorBag $errors): array
    {
        $plain = [];
        $dotted = [];

        foreach ($errors->getBag('default')->getMessages() as $key => $messages) {
            if (str_contains((string) $key, '.')) {
                $dotted[(string) $key] = array_values($messages);
            } else {
                $plain[(string) $key] = array_values($messages);
            }
        }

        return [$plain, $dotted];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private static function levelAnswer(array $answers): ?string
    {
        $value = $answers[TeacherSurvey::STEP_LEVEL]['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * The subject catalog for the levels the teacher picked, with every row
     * knowing its own level and which same-named subject sits in the other
     * level. That twin is what stops "both" teachers from ticking O/L ICT and
     * never noticing the A/L ICT row further down the page.
     *
     * @param  list<string>  $levelKeys
     * @return list<array{key: string, name: string, shortName: string, icon: ?string, subjects: list<array{id: int, name: string, icon: string, levelKey: string, levelName: string, twins: list<array{id: int, levelKey: string, levelName: string}>}>}>
     */
    public static function subjectGroups(array $levelKeys): array
    {
        $groups = SubjectCatalog::groups($levelKeys);

        $byName = [];

        foreach ($groups as $group) {
            foreach ($group['subjects'] as $subject) {
                $byName[self::subjectNameKey($subject['name'])][] = [
                    'id' => (int) $subject['id'],
                    'levelKey' => (string) $group['key'],
                    'levelName' => (string) ($group['shortName'] ?? $group['name']),
                ];
            }
        }

        foreach ($groups as $groupIndex => $group) {
            foreach ($group['subjects'] as $subjectIndex => $subject) {
                $twins = array_values(array_filter(
                    $byName[self::subjectNameKey($subject['name'])] ?? [],
                    fn (array $twin) => $twin['id'] !== (int) $subject['id'],
                ));

                $groups[$groupIndex]['subjects'][$subjectIndex] = [
                    ...$subject,
                    'levelKey' => (string) $group['key'],
                    'levelName' => (string) ($group['shortName'] ?? $group['name']),
                    'twins' => $twins,
                ];
            }
        }

        return $groups;
    }

    /**
     * Same-name matching ignores case and repeated whitespace, so "ICT " and
     * "ict" are the same subject for the twin suggestion.
     */
    private static function subjectNameKey(string $name): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($name)) ?? $name;

        return mb_strtolower($collapsed);
    }

    private static function index(string $step): int
    {
        return (int) array_search($step, TeacherSurvey::ANSWER_STEPS, true) + 1;
    }

    private static function title(string $step): string
    {
        return match ($step) {
            TeacherSurvey::STEP_LEVEL => __('Who are you tutoring for?'),
            TeacherSurvey::STEP_SUBJECTS => __('What subjects do you teach?'),
            TeacherSurvey::STEP_LANGUAGES => __('What language do you teach in?'),
            TeacherSurvey::STEP_SERVICES => __('What would you like to do on :app?', [
                'app' => config('app.name', 'Studylikepro'),
            ]),
            TeacherSurvey::STEP_EXPERIENCE => __('How long have you been teaching?'),
            TeacherSurvey::STEP_EDUCATION => __('Where did you study?'),
            TeacherSurvey::STEP_RATES => __('What do you charge?'),
            TeacherSurvey::STEP_AVAILABILITY => __('Which days can you teach?'),
            default => __('Are you a government teacher?'),
        };
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private static function body(string $step, array $answers): string
    {
        $level = self::levelAnswer($answers);

        return match ($step) {
            TeacherSurvey::STEP_LEVEL => __('Pick the exam level your students are preparing for. You can change this later.'),
            TeacherSurvey::STEP_SUBJECTS => $level === TeacherSurvey::LEVEL_BOTH
                ? __('You picked O/L and A/L, so the subjects are split into two tabs. Search for a subject and add it from each level you teach it for.')
                : __('Only the :level subjects are listed. Pick every subject you would take students for.', [
                    'level' => TeacherSurvey::levelLabel($level ?? ''),
                ]),
            TeacherSurvey::STEP_LANGUAGES => __('Pick every language you can teach a lesson in — more than one is fine.'),
            TeacherSurvey::STEP_SERVICES => __('Pick everything you would like to offer. You will set prices in a moment.'),
            TeacherSurvey::STEP_EXPERIENCE => __('Students see this on your profile card. Count the years you have taught, including school and tuition classes.'),
            TeacherSurvey::STEP_EDUCATION => __('Pick the institute where you earned your highest qualification.'),
            TeacherSurvey::STEP_RATES => __('Set your hourly fee for a lesson, then a price for each service you picked. You can change these later.'),
            TeacherSurvey::STEP_AVAILABILITY => __('Pick the days you can teach, starting from one of the suggested weeks. You will set the exact hours in the next step after signing up.'),
            default => __('Upload any document that proves your government teaching post. Verified government teachers get a badge on their profile card.'),
        };
    }

    private static function backUrl(string $step): ?string
    {
        return match ($step) {
            TeacherSurvey::STEP_LEVEL => null,
            TeacherSurvey::STEP_SUBJECTS => route('join.level'),
            TeacherSurvey::STEP_LANGUAGES => route('join.subjects'),
            TeacherSurvey::STEP_SERVICES => route('join.languages'),
            TeacherSurvey::STEP_EXPERIENCE => route('join.services'),
            TeacherSurvey::STEP_EDUCATION => route('join.experience'),
            TeacherSurvey::STEP_RATES => route('join.education'),
            TeacherSurvey::STEP_AVAILABILITY => route('join.rates'),
            default => route('join.availability'),
        };
    }

    /**
     * The answers to replay: old input first, then what the survey already holds.
     *
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    public static function values(string $step, array $answers): array
    {
        $stored = $answers[$step] ?? [];

        return match ($step) {
            TeacherSurvey::STEP_LEVEL => [
                'level' => old('level', is_array($stored) ? ($stored['value'] ?? null) : null),
            ],
            TeacherSurvey::STEP_SUBJECTS => [
                'subjects' => array_values(array_map('intval', (array) old('subjects', is_array($stored) ? $stored : []))),
            ],
            TeacherSurvey::STEP_LANGUAGES => [
                'languages' => array_values(array_map('strval', (array) old('languages', is_array($stored) ? $stored : []))),
            ],
            TeacherSurvey::STEP_SERVICES => [
                'services' => array_values(array_map('strval', (array) old('services', is_array($stored) ? $stored : []))),
            ],
            TeacherSurvey::STEP_EXPERIENCE => [
                'experience_years' => old('experience_years', is_array($stored) ? ($stored['value'] ?? null) : null),
            ],
            TeacherSurvey::STEP_EDUCATION => [
                'education' => old('education', is_array($stored) ? ($stored['value'] ?? null) : null),
            ],
            TeacherSurvey::STEP_RATES => [
                'hourly_rate' => old('hourly_rate', is_array($stored) ? ($stored['hourly_rate'] ?? null) : null),
                'service_rates' => (array) old('service_rates', is_array($stored) ? ($stored['service_rates'] ?? []) : []),
            ],
            TeacherSurvey::STEP_AVAILABILITY => [
                'days' => array_values(array_map('intval', (array) old('days', is_array($stored) ? ($stored['days'] ?? []) : []))),
                'preset' => self::presetValue($stored),
            ],
            default => [
                'claims' => old('claims', is_array($stored) && isset($stored['claimed'])
                    ? ($stored['claimed'] ? '1' : '0')
                    : null),
            ],
        };
    }

    /**
     * The suggested week to re-select: a rejected save replays its own choice,
     * otherwise the answer the survey already holds. "No preset" is null so the
     * radio and the day picker agree on the empty value.
     *
     * @param  array<string, mixed>  $stored
     */
    private static function presetValue(array $stored): ?string
    {
        $value = old('preset', $stored['preset'] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Every string the survey draws, so no translated copy is hardcoded in Vue.
     *
     * @return array<string, string>
     */
    public static function copy(): array
    {
        return [
            'questionOf' => __('Question :number of :total'),
            'answered' => __('answered'),
            'errorTitle' => __('Please check your answer:'),
            'back' => __('Back'),
            'continue' => __('Continue'),
            'continueToAccount' => __('Continue to your account'),
            'saving' => __('Saving…'),
            // The recap and the rail travel with every survey screen.
            'yourAnswers' => __('Your answers'),
            'changeNote' => __('You can change any answer before you create your account.'),
            'notAnswered' => __('Not answered yet'),
            'progressLabel' => __('Your progress'),
            'stepOf' => __('Step :number of :total'),
            'lastStep' => __('Last step'),
            'railLabel' => __('Teacher setup steps'),
        ];
    }

    /**
     * The extra copy each question draws. Kept apart from `copy()` so the recap,
     * the rail and the account step do not carry question-only strings.
     *
     * @return array<string, string>
     */
    public static function questionCopy(): array
    {
        return [
            // Question 2: the searchable, level-aware subject picker.
            'subjectSearch' => __('Search subjects'),
            'subjectSearchClear' => __('Clear search'),
            'subjectSearchResults' => __('Matches from every level you picked'),
            'subjectNoMatches' => __('No subject matches :query. Try a shorter word or another spelling.'),
            'subjectSelectAll' => __('Select all'),
            'subjectClear' => __('Clear'),
            'subjectCountOne' => __('1 subject'),
            'subjectCount' => __(':count subjects'),
            'subjectSelectedOne' => __('1 selected'),
            'subjectSelected' => __(':count selected'),
            'subjectSelectedTitle' => __('Selected subjects'),
            'subjectSelectionsEmpty' => __('Nothing picked yet — tick the subjects you teach.'),
            'subjectMissingLevel' => __('No :level subject picked yet — add one if you teach :level too.'),
            'subjectTwinHint' => __('You also teach :level :name?'),
            'subjectTwinCta' => __('Add it'),
            'subjectRemove' => __('Remove :name'),
            'subjectTabsLabel' => __('Subject levels'),
            'subjectBrowseHint' => __('Pick every subject you would take students for.'),
            'subjectNoscriptHint' => __('Every subject is listed level by level below.'),
            'years' => __('Years of experience'),
            'yearsUnit' => __('years'),
            'yearsHint' => __('Whole years. Enter 0 if you have not taught yet.'),
            'hourlyRate' => __('Hourly fee for a lesson (rupees)'),
            'hourlyRateHint' => __('What an hour of one-to-one teaching costs.'),
            'hourUnit' => __('hour'),
            'serviceRate' => __('Price for :service (rupees)'),
            'lessonsOnly' => __('You picked lessons only, so the hourly fee covers everything for now.'),
            'presetsTitle' => __('Suggested weeks'),
            'presetsHint' => __('The weeks we suggest when you set your hours. Pick one to use its days and times, or pick My own days to choose the days yourself.'),
            'recommended' => __('Recommended'),
            'ownDaysTitle' => __('My own days'),
            'ownDaysHint' => __('Tick the days yourself. Each one starts at the recommended hours.'),
            'daysTitle' => __('Days you can teach'),
            'availabilityNote' => __('You can change your availability any time after you register.'),
            'claimsYes' => __('Yes, I am a government teacher'),
            'claimsYesHint' => __('Upload proof and we will confirm it. Verified government teachers get a badge on their profile card.'),
            'claimsNo' => __('No, I teach privately'),
            'claimsNoHint' => __('You can add proof later from your verification page.'),
            'proofLabel' => __('Proof document'),
            'proofHint' => __('PDF, JPG, PNG or WebP — up to 5 MB. A school letter, appointment letter or staff ID works.'),
            'proofUploaded' => __('Uploaded: :name — attach a different file only if you want to replace it.'),
            'pickFile' => __('Choose a file'),
            'noFilePicked' => __('No file chosen'),
            'proofNoScript' => __('Choose “Yes” to attach a proof document, or “No” to skip this question.'),
        ];
    }
}
