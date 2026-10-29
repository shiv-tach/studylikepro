<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherSurveyAvailabilityRequest;
use App\Http\Requests\TeacherSurveyEducationRequest;
use App\Http\Requests\TeacherSurveyExperienceRequest;
use App\Http\Requests\TeacherSurveyLanguagesRequest;
use App\Http\Requests\TeacherSurveyLevelRequest;
use App\Http\Requests\TeacherSurveyProofRequest;
use App\Http\Requests\TeacherSurveyRatesRequest;
use App\Http\Requests\TeacherSurveyServicesRequest;
use App\Http\Requests\TeacherSurveySubjectsRequest;
use App\Services\InviteOnboardingService;
use App\Support\AvailabilityPresets;
use App\Support\PendingTeacherSignup;
use App\Support\SubjectCatalog;
use App\Support\TeacherSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * The invite survey: one question per screen, asked before the account exists.
 *
 * Every answer is stored in the session (see `PendingTeacherSignup`) and only the
 * last step — identity, password and the SMS code — writes the `users` row, which
 * then receives these answers through `InviteOnboardingService`.
 *
 * The long-form profile (headline, bio, photo) is deliberately NOT asked here:
 * the teacher finishes it in the signed-in wizard right after signing up, with
 * the answers given here already filled in.
 */
class TeacherSurveyController extends Controller
{
    /**
     * The entry point: there is no intro screen, so the invite opens question 1 —
     * or the first question still unanswered when a visitor comes back.
     */
    public function start(): RedirectResponse
    {
        return redirect()->route(TeacherSurvey::nextRouteName(PendingTeacherSignup::answers()));
    }

    /**
     * Question 1: O/L, A/L or both.
     */
    public function editLevel(Request $request): View
    {
        return $this->question(TeacherSurvey::STEP_LEVEL, $request);
    }

    public function updateLevel(TeacherSurveyLevelRequest $request): RedirectResponse
    {
        $level = (string) $request->validated('level');

        PendingTeacherSignup::putStep(TeacherSurvey::STEP_LEVEL, ['value' => $level]);

        $this->pruneSubjects($level);

        return redirect()->route('join.subjects');
    }

    /**
     * Question 2: the subjects that belong to the level answered first.
     */
    public function editSubjects(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_LEVEL)) {
            return redirect()
                ->route('join.level')
                ->with('status', 'answer-the-level-question');
        }

        return $this->question(TeacherSurvey::STEP_SUBJECTS, $request);
    }

    public function updateSubjects(TeacherSurveySubjectsRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(
            TeacherSurvey::STEP_SUBJECTS,
            array_values(array_map('intval', (array) $request->validated('subjects'))),
        );

        return redirect()->route('join.languages');
    }

    /**
     * Question 3: the languages the teacher teaches in.
     */
    public function editLanguages(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_SUBJECTS)) {
            return redirect()
                ->route('join.subjects')
                ->with('status', 'answer-the-subject-question');
        }

        return $this->question(TeacherSurvey::STEP_LANGUAGES, $request);
    }

    public function updateLanguages(TeacherSurveyLanguagesRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(
            TeacherSurvey::STEP_LANGUAGES,
            array_values(array_map('strval', (array) $request->validated('languages'))),
        );

        return redirect()->route('join.services');
    }

    /**
     * Question 4: the services the teacher would like to offer.
     */
    public function editServices(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_LANGUAGES)) {
            return redirect()
                ->route('join.languages')
                ->with('status', 'answer-the-language-question');
        }

        return $this->question(TeacherSurvey::STEP_SERVICES, $request);
    }

    public function updateServices(TeacherSurveyServicesRequest $request): RedirectResponse
    {
        $services = array_values(array_map('strval', (array) $request->validated('services')));
        $previous = array_values(array_map('strval', PendingTeacherSignup::step(TeacherSurvey::STEP_SERVICES)));

        PendingTeacherSignup::putStep(TeacherSurvey::STEP_SERVICES, $services);

        // The rates question priced the services picked earlier by name, so a
        // changed list has to be priced again instead of keeping stale amounts.
        if ((array_diff($services, $previous) !== [] || array_diff($previous, $services) !== [])
            && PendingTeacherSignup::hasStep(TeacherSurvey::STEP_RATES)) {
            PendingTeacherSignup::forgetStep(TeacherSurvey::STEP_RATES);
        }

        return redirect()->route('join.experience');
    }

    /**
     * Question 5: how many years the teacher has been teaching.
     */
    public function editExperience(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_SERVICES)) {
            return redirect()
                ->route('join.services')
                ->with('status', 'answer-the-service-question');
        }

        return $this->question(TeacherSurvey::STEP_EXPERIENCE, $request);
    }

    public function updateExperience(TeacherSurveyExperienceRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(TeacherSurvey::STEP_EXPERIENCE, [
            'value' => (int) $request->validated('experience_years'),
        ]);

        return redirect()->route('join.education');
    }

    /**
     * Question 6: the institute the teacher studied at.
     */
    public function editEducation(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_EXPERIENCE)) {
            return redirect()
                ->route('join.experience')
                ->with('status', 'answer-the-experience-question');
        }

        return $this->question(TeacherSurvey::STEP_EDUCATION, $request);
    }

    public function updateEducation(TeacherSurveyEducationRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(TeacherSurvey::STEP_EDUCATION, [
            'value' => (string) $request->validated('education'),
        ]);

        return redirect()->route('join.rates');
    }

    /**
     * Question 7: the hourly fee, plus a price for every service picked in
     * question 4.
     */
    public function editRates(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_EDUCATION)) {
            return redirect()
                ->route('join.education')
                ->with('status', 'answer-the-education-question');
        }

        return $this->question(TeacherSurvey::STEP_RATES, $request);
    }

    public function updateRates(TeacherSurveyRatesRequest $request): RedirectResponse
    {
        $priced = TeacherSurvey::pricedServiceOptions(PendingTeacherSignup::step(TeacherSurvey::STEP_SERVICES));

        // Only the services this teacher picked are kept, so a tampered field
        // cannot price a service they never offered.
        $rates = [];

        foreach ($priced as $option) {
            $rates[$option['value']] = (float) $request->validated('service_rates.'.$option['value']);
        }

        PendingTeacherSignup::putStep(TeacherSurvey::STEP_RATES, [
            'hourly_rate' => (float) $request->validated('hourly_rate'),
            'service_rates' => $rates,
        ]);

        return redirect()->route('join.availability');
    }

    /**
     * Question 8: the days of the week the teacher can teach, starting from one
     * of the suggested weeks so the clock times come with the choice.
     */
    public function editAvailability(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_RATES)) {
            return redirect()
                ->route('join.rates')
                ->with('status', 'answer-the-rates-question');
        }

        return $this->question(TeacherSurvey::STEP_AVAILABILITY, $request);
    }

    public function updateAvailability(TeacherSurveyAvailabilityRequest $request): RedirectResponse
    {
        $days = array_values(array_map('intval', (array) $request->validated('days')));
        sort($days);

        $preset = $request->validated('preset');

        PendingTeacherSignup::putStep(TeacherSurvey::STEP_AVAILABILITY, [
            'days' => $days,
            'preset' => is_string($preset) && $preset !== '' ? $preset : null,
        ]);

        return redirect()->route('join.proof');
    }

    /**
     * Question 9: proof of a government teaching post (optional).
     */
    public function editProof(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_AVAILABILITY)) {
            return redirect()
                ->route('join.availability')
                ->with('status', 'answer-the-availability-question');
        }

        return $this->question(TeacherSurvey::STEP_PROOF, $request);
    }

    /**
     * Store the answer. A guest has no profile yet, so the file is parked under
     * `verification-documents/pending` and `InviteOnboardingService` files it
     * properly once the account exists.
     */
    public function updateProof(TeacherSurveyProofRequest $request): RedirectResponse
    {
        $previous = PendingTeacherSignup::proofPath();

        if ($previous !== null) {
            Storage::disk('local')->delete($previous);
        }

        $file = $request->file('document');

        if ((bool) $request->validated('claims') && $file !== null) {
            PendingTeacherSignup::putStep(TeacherSurvey::STEP_PROOF, [
                'claimed' => true,
                'path' => $file->store(InviteOnboardingService::PENDING_PROOF_DIRECTORY, 'local'),
                'original_name' => $file->getClientOriginalName(),
            ]);
        } else {
            PendingTeacherSignup::putStep(TeacherSurvey::STEP_PROOF, [
                'claimed' => false,
                'path' => null,
                'original_name' => null,
            ]);
        }

        return redirect()->route('join.account');
    }

    /**
     * The shared frame of one question: the rail, the copy, the answer values and
     * the options that question actually needs.
     */
    private function question(string $step, Request $request): View
    {
        $answers = PendingTeacherSignup::answers();
        $index = (int) array_search($step, TeacherSurvey::ANSWER_STEPS, true);
        $presets = [];
        $dayTimes = [];

        if ($step === TeacherSurvey::STEP_AVAILABILITY) {
            $presets = AvailabilityPresets::options();

            // With no suggested week chosen, every picked day opens on the
            // recommended hours, so the day cards can say which hours those are.
            $defaultWeek = AvailabilityPresets::defaultWeek();

            foreach (TeacherSurvey::availabilityDays() as $day) {
                $dayTimes[$day['value']] = AvailabilityPresets::timeLabel($defaultWeek[$day['value']]);
            }
        }

        return view('join.survey', [
            'guest' => true,
            'step' => $step,
            'title' => $this->titleFor($step),
            'body' => $this->bodyFor($step, $answers),
            'index' => $index + 1,
            'total' => count(TeacherSurvey::ANSWER_STEPS),
            'answered' => TeacherSurvey::answeredCount($answers),
            'steps' => TeacherSurvey::steps($answers),
            'summary' => TeacherSurvey::summary($answers),
            'currentGroup' => TeacherSurvey::groupFor($step),
            'submitUrl' => route(match ($step) {
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
            'backUrl' => $this->backUrl($step),
            'values' => $this->values($step, $answers),
            'levels' => TeacherSurvey::levels(),
            'subjectGroups' => $step === TeacherSurvey::STEP_SUBJECTS
                ? SubjectCatalog::groups(TeacherSurvey::allowedLevelKeys($answers[TeacherSurvey::STEP_LEVEL]['value'] ?? null))
                : [],
            'languages' => array_values((array) config('studylikepro.languages')),
            'services' => TeacherSurvey::serviceOptions(),
            'educationOptions' => TeacherSurvey::educationOptions(),
            'pricedServices' => $step === TeacherSurvey::STEP_RATES
                ? TeacherSurvey::pricedServiceOptions($answers[TeacherSurvey::STEP_SERVICES] ?? [])
                : [],
            'days' => TeacherSurvey::availabilityDays(),
            'availabilityPresets' => $presets,
            'presetDays' => collect($presets)
                ->mapWithKeys(fn (array $preset) => [$preset['key'] => array_keys($preset['days'])])
                ->all(),
            'dayTimes' => $dayTimes,
            'proofName' => $step === TeacherSurvey::STEP_PROOF
                ? ($answers[TeacherSurvey::STEP_PROOF]['original_name'] ?? null)
                : null,
        ]);
    }

    private function titleFor(string $step): string
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
    private function bodyFor(string $step, array $answers): string
    {
        $level = $answers[TeacherSurvey::STEP_LEVEL]['value'] ?? null;

        return match ($step) {
            TeacherSurvey::STEP_LEVEL => __('Pick the exam level your students are preparing for. You can change this later.'),
            TeacherSurvey::STEP_SUBJECTS => __('Only the :level subjects are listed. Pick every subject you would take students for.', [
                'level' => TeacherSurvey::levelLabel(is_string($level) ? $level : ''),
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

    private function backUrl(string $step): ?string
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
    private function values(string $step, array $answers): array
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
                'preset' => $this->presetValue($stored),
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
    private function presetValue(array $stored): ?string
    {
        $value = old('preset', $stored['preset'] ?? null);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Changing the level can drop the subjects it no longer offers: "A/L only"
     * after picking O/L subjects must not silently keep the O/L picks.
     */
    private function pruneSubjects(string $level): void
    {
        $picked = array_map('intval', PendingTeacherSignup::step(TeacherSurvey::STEP_SUBJECTS));

        if ($picked === []) {
            return;
        }

        $allowed = SubjectCatalog::subjectIdsForLevels(TeacherSurvey::allowedLevelKeys($level));
        $kept = array_values(array_intersect($picked, $allowed));

        if ($kept === []) {
            PendingTeacherSignup::forgetStep(TeacherSurvey::STEP_SUBJECTS);

            return;
        }

        if ($kept !== $picked) {
            PendingTeacherSignup::putStep(TeacherSurvey::STEP_SUBJECTS, $kept);
        }
    }
}
