<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ServiceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherSurveyAvailabilityRequest;
use App\Http\Requests\TeacherSurveyEducationRequest;
use App\Http\Requests\TeacherSurveyExperienceRequest;
use App\Http\Requests\TeacherSurveyLanguagesRequest;
use App\Http\Requests\TeacherSurveyLevelRequest;
use App\Http\Requests\TeacherSurveyProofRequest;
use App\Http\Requests\TeacherSurveyServicesRequest;
use App\Http\Requests\TeacherSurveySubjectsRequest;
use App\Services\InviteOnboardingService;
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
        return $this->question(TeacherSurvey::STEP_LEVEL);
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

        return $this->question(TeacherSurvey::STEP_SUBJECTS);
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

        return $this->question(TeacherSurvey::STEP_LANGUAGES);
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
     * Question 4: the services the teacher would like to offer, each one priced
     * on the spot.
     */
    public function editServices(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_LANGUAGES)) {
            return redirect()
                ->route('join.languages')
                ->with('status', 'answer-the-language-question');
        }

        return $this->question(TeacherSurvey::STEP_SERVICES);
    }

    public function updateServices(TeacherSurveyServicesRequest $request): RedirectResponse
    {
        $services = array_values(array_map('strval', (array) $request->validated('services')));
        $offersLessons = in_array(ServiceType::Lesson->value, $services, true);

        // Only the services this teacher ticked are kept, so a tampered field
        // cannot price a service they never offered.
        $rates = [];

        foreach (TeacherSurvey::pricedServiceOptions($services) as $option) {
            $rates[$option['value']] = (float) $request->validated('service_rates.'.$option['value']);
        }

        PendingTeacherSignup::putStep(TeacherSurvey::STEP_SERVICES, $services);

        // The prices ride along with the picks: this screen is the only place
        // that asks for them, while the profile (and the signed-in services
        // console) still reads the hourly fee and the service prices apart.
        PendingTeacherSignup::putStep(TeacherSurvey::STEP_RATES, [
            'hourly_rate' => $offersLessons ? (float) $request->validated('hourly_rate') : null,
            'service_rates' => $rates,
        ]);

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

        return $this->question(TeacherSurvey::STEP_EXPERIENCE);
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

        return $this->question(TeacherSurvey::STEP_EDUCATION);
    }

    public function updateEducation(TeacherSurveyEducationRequest $request): RedirectResponse
    {
        PendingTeacherSignup::putStep(TeacherSurvey::STEP_EDUCATION, [
            'value' => (string) $request->validated('education'),
        ]);

        return redirect()->route('join.availability');
    }

    /**
     * Question 7: the days of the week the teacher can teach, starting from one
     * of the suggested weeks so the clock times come with the choice.
     */
    public function editAvailability(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_SERVICES)) {
            return redirect()
                ->route('join.services')
                ->with('status', 'answer-the-service-question');
        }

        return $this->question(TeacherSurvey::STEP_AVAILABILITY);
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
     * Question 8: proof of a government teaching post (optional).
     */
    public function editProof(Request $request): View|RedirectResponse
    {
        if (! PendingTeacherSignup::hasStep(TeacherSurvey::STEP_AVAILABILITY)) {
            return redirect()
                ->route('join.availability')
                ->with('status', 'answer-the-availability-question');
        }

        return $this->question(TeacherSurvey::STEP_PROOF);
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
     * The shared frame of one question. Blade builds the island (and its
     * `<noscript>` twin) from `JoinSurveyConfig`, so the controller only has to
     * pass the step and the answers given so far.
     */
    private function question(string $step): View
    {
        return view('join.survey', [
            'guest' => true,
            'step' => $step,
            'answers' => PendingTeacherSignup::answers(),
        ]);
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
