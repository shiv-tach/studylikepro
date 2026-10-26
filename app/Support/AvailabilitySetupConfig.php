<?php

namespace App\Support;

use App\Models\TeacherOnboardingDraft;
use App\Models\TeacherProfile;
use Illuminate\Support\MessageBag;

/**
 * The island behind the weekly-availability step of the onboarding wizard.
 *
 * Precedence for the week the editor opens with:
 * 1. a rejected save (`old('days')`) — the teacher must see what they submitted,
 * 2. the autosaved draft — a reload restores exactly what was on screen,
 * 3. the saved ranges,
 * 4. the recommended preset for a teacher who has never set a week.
 */
final class AvailabilitySetupConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function build(
        TeacherProfile $profile,
        MessageBag $errors,
        string $themePreset = 'classic',
        ?TeacherOnboardingDraft $draft = null,
    ): array {
        $weekdays = config('studylikepro.weekdays');
        $presets = AvailabilityPresets::all();
        $lessonDuration = $profile->lessonDuration();
        $timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');

        $savedWeek = collect($weekdays)
            ->map(fn (string $label, int $day) => [
                'day' => $day,
                'ranges' => $profile->availabilitySlots
                    ->where('day_of_week', $day)
                    ->map(fn ($slot) => ['start' => $slot->startLabel(), 'end' => $slot->endLabel()])
                    ->values()
                    ->all(),
            ])
            ->values();

        $oldDays = old('days');
        $draftDays = $draft?->step(TeacherOnboarding::STEP_AVAILABILITY)['days'] ?? null;
        $restored = false;

        if (is_array($oldDays)) {
            $sourceWeek = collect(array_values($oldDays))->map(fn (array $day) => [
                'day' => (int) ($day['value'] ?? 0),
                'ranges' => collect($day['ranges'] ?? [])
                    ->map(fn (array $range) => [
                        'start' => (string) ($range['start_time'] ?? ''),
                        'end' => (string) ($range['end_time'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ]);
        } elseif (is_array($draftDays) && $draftDays !== []) {
            $restored = true;
            $sourceWeek = collect(array_values($draftDays))->map(fn (array $day) => [
                'day' => (int) ($day['value'] ?? 0),
                'ranges' => collect($day['ranges'] ?? [])
                    ->map(fn (array $range) => [
                        'start' => (string) ($range['start'] ?? $range['start_time'] ?? ''),
                        'end' => (string) ($range['end'] ?? $range['end_time'] ?? ''),
                    ])
                    ->values()
                    ->all(),
            ]);
        } elseif ($profile->availabilitySlots->isEmpty()) {
            $recommended = AvailabilityPresets::recommended();

            $sourceWeek = collect($recommended['days'])->map(fn (array $ranges, int $day) => [
                'day' => $day,
                'ranges' => $ranges,
            ]);
        } else {
            $sourceWeek = $savedWeek;
        }

        $byDay = $sourceWeek->keyBy('day');

        $week = collect($weekdays)
            ->map(function (string $label, int $day) use ($byDay) {
                $entry = $byDay->get($day, ['ranges' => []]);

                return [
                    'value' => $day,
                    'label' => $label,
                    'shortLabel' => substr($label, 0, 3),
                    'enabled' => count($entry['ranges']) > 0,
                    'ranges' => array_values($entry['ranges']),
                ];
            })
            ->values()
            ->all();

        // Laravel keys its messages positionally, so the island carries them
        // under the same names the rows submit.
        $rowErrors = [];

        foreach (array_keys($week) as $index) {
            $rowErrors["days.$index.ranges"] = $errors->get("days.$index.ranges");

            foreach (range(0, 3) as $row) {
                $rowErrors["days.$index.ranges.$row.start_time"] = $errors->get("days.$index.ranges.$row.start_time");
                $rowErrors["days.$index.ranges.$row.end_time"] = $errors->get("days.$index.ranges.$row.end_time");
            }
        }

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'theme' => ['preset' => $themePreset],
            'mode' => 'onboarding',
            'stepKey' => TeacherOnboarding::STEP_AVAILABILITY,
            'status' => [
                'profileCompleted' => session('status') === 'profile-completed',
                'preferencesCompleted' => session('status') === 'preferences-saved',
            ],
            'profile' => [
                'isComplete' => $profile->isComplete(),
                'hasAvailability' => $profile->availabilitySlots->isNotEmpty(),
                'lessonDuration' => $lessonDuration,
                'timezone' => $timezone,
            ],
            'week' => $week,
            'presets' => $presets,
            'limits' => [
                'maxRangesPerDay' => 4,
            ],
            'errors' => $rowErrors,
            'errorMessages' => $errors->all(),
            'steps' => TeacherOnboarding::steps($profile, $draft),
            'currentStep' => 4,
            'draft' => [
                'restored' => $restored,
                'savedAt' => $restored ? $draft?->saved_at?->toIso8601String() : null,
                'savedAtLabel' => $restored ? display_datetime($draft?->saved_at) : null,
                'hasAny' => $draft !== null && ! $draft->isEmpty(),
                'step' => TeacherOnboarding::STEP_AVAILABILITY,
            ],
            'urls' => [
                'update' => route('teacher.onboarding.availability.update'),
                'back' => route('teacher.onboarding.preferences'),
                'next' => route('teacher.onboarding.verification'),
                'draft' => route('teacher.onboarding.draft'),
                'draftDelete' => route('teacher.onboarding.draft.destroy'),
                'profile' => route('teacher.onboarding.profile'),
                'availability' => route('teacher.availability.index'),
            ],
            'copy' => self::copy($timezone, $lessonDuration),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function copy(string $timezone, int $lessonDuration): array
    {
        return [
            'profileCompletedTitle' => __('Welcome to your weekly hours'),
            'profileCompletedBody' => __('Your profile is saved. Set the days and hours you teach so students can book you.'),
            'preferencesCompletedTitle' => __('Teaching preferences saved'),
            'preferencesCompletedBody' => __('Almost there — set your weekly hours, then upload your documents.'),
            'errorTitle' => __('Please fix the following:'),
            'stepsTitle' => __('Teacher setup'),
            'stepsCompleted' => __(':completed of :total steps completed'),
            'stepLabel' => __('Step :number'),
            'stepStatusDone' => __('Completed'),
            'stepStatusCurrent' => __('In progress'),
            'stepStatusUpNext' => __('Up next'),
            'stepStatusLocked' => __('Locked'),
            'presetTitle' => __('Suggested weeks'),
            'presetHint' => __('Load a full week, then change anything you like. Nothing is saved until you press save.'),
            'presetRecommended' => __('Recommended'),
            'presetApply' => __('Use this week'),
            'presetApplied' => __('Week loaded into the editor — review the days below.'),
            'clearWeek' => __('Clear week'),
            'editorTitle' => __('Your week'),
            'editorHint' => __('Students can book lessons inside these hours.'),
            'timezoneNote' => __('Times are in :timezone.', ['timezone' => $timezone]),
            'lessonLengthNote' => __('Your lessons are :minutes minutes long, so every range must last at least that long.', ['minutes' => $lessonDuration]),
            'dayAvailable' => __('Available'),
            'dayUnavailable' => __('Not available'),
            // The placeholder stays for the Vue editor to fill per day.
            'dayToggle' => __('Toggle :day availability'),
            'fromLabel' => __('From'),
            'toLabel' => __('To'),
            'addRange' => __('Add time range'),
            'removeRange' => __('Remove this range'),
            'rangesHint' => __('Split a day into more than one block if you teach in the morning and the evening.'),
            'noRanges' => __('Add at least one range'),
            'summaryTitle' => __('Weekly summary'),
            'daysCountLabel' => __('Teaching days'),
            'hoursLabel' => __('Weekly hours'),
            'lessonsLabel' => __('Lesson slots'),
            'daysCount' => __(':count days a week'),
            'weeklyHours' => __(':hours hours a week'),
            'lessonsPreview' => __('About :count lessons a week'),
            'emptySubmit' => __('Add at least one complete range before saving.'),
            'unsaved' => __('You have unsaved changes.'),
            'saved' => __('All changes saved.'),
            'saving' => __('Saving…'),
            'save' => __('Save week & continue'),
            'undo' => __('Undo changes'),
            'confirmPresetTitle' => __('Replace the week?'),
            'confirmPresetBody' => __('Loading this preset replaces the times currently in the editor.'),
            'confirmPresetConfirm' => __('Load preset'),
            'confirmClearTitle' => __('Clear the whole week?'),
            'confirmClearBody' => __('Every time range in the editor is removed. Nothing is saved until you press save.'),
            'confirmClearConfirm' => __('Clear week'),
            'confirmCancel' => __('Cancel'),
            'confirmClose' => __('Close'),
            'back' => __('Back'),
            'draftDirty' => __('Unsaved changes — keeping a draft…'),
            'draftSaving' => __('Saving draft…'),
            'draftSaved' => __('Draft saved just now'),
            'draftOffline' => __('Offline — your answers are kept on this device.'),
            'draftError' => __('Could not save the draft. Your answers are kept on this device.'),
            'draftRestoredTitle' => __('We restored your unsaved week'),
            'draftRestoredBody' => __('Carry on from :time. Save the week to keep it.'),
            'draftDiscard' => __('Start over'),
            'draftDiscardConfirm' => __('Discard the draft and restore your saved week?'),
            'stepTitle' => __('When can you teach?'),
            'stepBody' => __('Students can only book inside the hours you set here.'),
            'stepOf' => __('Step :current of :total'),
        ];
    }
}
