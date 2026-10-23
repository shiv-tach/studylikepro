<?php

namespace App\Support;

use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The JSON island behind /teacher/availability.
 *
 * The page answers two questions — when do I teach, and what do I charge — so
 * Blade formats every time, price and label here and the Vue app only lays the
 * answer out. The week is the same shape the onboarding editor posts
 * (`days[0][ranges][1][start_time]`), which lets the manager reuse
 * WeeklyAvailabilityRequest and WeeklyAvailabilityService::replace(): saving
 * the week is one atomic replace, never a half-applied set of ranges.
 */
final class AvailabilityManagerConfig
{
    /**
     * @param  array<string, list<array{local_time: string}>>  $preview  Open slots grouped by the teacher's local date.
     */
    public static function build(
        TeacherProfile $profile,
        string $preset,
        array $preview,
        int $commissionPercent,
    ): array {
        $timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');
        $lessonDuration = $profile->lessonDuration();

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'timezone' => $timezone,
            'theme' => ['preset' => $preset],
            'flash' => self::flash(),
            'errors' => self::errors(),
            'errorMessages' => array_values(session('errors')?->all() ?? []),
            'week' => self::week($profile),
            'presets' => AvailabilityPresets::all(),
            'limits' => ['maxRangesPerDay' => 4],
            'pricing' => self::pricing($profile, $commissionPercent),
            'preview' => self::preview($preview),
            'timeOff' => self::timeOff($profile->timeOff),
            // A rejected save replays the date range the teacher picked.
            'timeOffForm' => [
                'starts_on' => old('starts_on'),
                'ends_on' => old('ends_on'),
                'reason' => old('reason'),
            ],
            'copy' => self::copy($timezone, $lessonDuration),
            'urls' => [
                'week' => route('teacher.availability.week.update'),
                'pricing' => route('teacher.availability.pricing.update'),
                'settings' => route('teacher.availability.settings.update'),
                'timeOff' => route('teacher.availability.time-off.store'),
                'subjects' => route('teacher.subjects.index'),
                'schedule' => route('teacher.schedule.index'),
            ],
        ];
    }

    /**
     * The seven editable days, in the same order WeeklyAvailabilityRequest
     * validates. A rejected save wins over the stored ranges so the teacher can
     * see and fix exactly what they submitted.
     *
     * @return list<array{value: int, label: string, shortLabel: string, enabled: bool, ranges: list<array{start: string, end: string}>}>
     */
    private static function week(TeacherProfile $profile): array
    {
        $weekdays = config('studylikepro.weekdays');
        $saved = $profile->availabilitySlots->groupBy('day_of_week');
        $replay = self::replayedWeek();

        $week = [];

        foreach ($weekdays as $day => $label) {
            $ranges = $replay !== null
                ? ($replay[(int) $day] ?? [])
                : $saved->get($day, collect())
                    ->map(fn ($slot) => ['start' => $slot->startLabel(), 'end' => $slot->endLabel()])
                    ->values()
                    ->all();

            $week[] = [
                'value' => (int) $day,
                'label' => $label,
                'shortLabel' => mb_substr($label, 0, 3),
                'enabled' => count($ranges) > 0,
                'ranges' => $ranges,
            ];
        }

        return $week;
    }

    /**
     * @return array<int, list<array{start: string, end: string}>>|null
     */
    private static function replayedWeek(): ?array
    {
        $days = old('days');

        if (! is_array($days)) {
            return null;
        }

        $between = [];

        foreach (array_values($days) as $day) {
            $between[(int) ($day['value'] ?? -1)] = collect($day['ranges'] ?? [])
                ->map(fn (array $range) => [
                    'start' => (string) ($range['start_time'] ?? ''),
                    'end' => (string) ($range['end_time'] ?? ''),
                ])
                ->values()
                ->all();
        }

        return $between;
    }

    /**
     * Laravel keys its messages positionally, exactly as the rows submit them,
     * so the editor only has to read `days.3.ranges.0.start_time`.
     *
     * @return array<string, list<string>>
     */
    private static function errors(): array
    {
        $bag = session('errors');

        if ($bag === null) {
            return [];
        }

        $keys = [
            'days',
            'hourly_rate',
            'lesson_duration_minutes',
            'starts_on',
            'ends_on',
            'reason',
        ];

        foreach (array_keys(config('studylikepro.weekdays')) as $index) {
            $keys[] = "days.$index.ranges";

            foreach (range(0, 3) as $row) {
                $keys[] = "days.$index.ranges.$row.start_time";
                $keys[] = "days.$index.ranges.$row.end_time";
            }
        }

        $errors = [];

        foreach ($keys as $key) {
            $messages = $bag->get($key);

            if ($messages !== []) {
                $errors[$key] = $messages;
            }
        }

        return $errors;
    }

    /**
     * The default price (base hourly rate), the platform fee that applies to it
     * and the per-subject overrides the teacher edits on /teacher/subjects.
     *
     * @return array<string, mixed>
     */
    private static function pricing(TeacherProfile $profile, int $commissionPercent): array
    {
        $settings = platform_settings();
        // A rejected save replays what the teacher typed, not what was stored.
        $rate = (float) old('hourly_rate', $profile->hourlyRate());
        $rateMinor = (int) round($rate * 100);

        return [
            'rate' => $rate,
            'rateMinor' => $rateMinor,
            'rateLabel' => $settings->formatMinor($rateMinor),
            'commissionPercent' => $commissionPercent,
            'earningsMinor' => (int) round($rateMinor * (100 - $commissionPercent) / 100),
            'earningsLabel' => $settings->formatMinor((int) round($rateMinor * (100 - $commissionPercent) / 100)),
            'currency' => [
                'symbol' => $settings->currencySymbol(),
                'code' => (string) config('studylikepro.currency'),
            ],
            'limits' => [
                'min' => 100,
                'max' => 100000,
            ],
            'lessonDuration' => $profile->lessonDuration(),
            'durations' => collect(config('studylikepro.lesson_durations'))
                ->map(fn (int $minutes) => [
                    'value' => $minutes,
                    'label' => __(':minutes minutes', ['minutes' => $minutes]),
                ])
                ->values()
                ->all(),
            'subjects' => self::subjectRates($profile),
        ];
    }

    /**
     * Only the subjects that actually override the base rate (a subject rate or
     * per-grade rates) get a tile — the rest simply inherit the default price.
     *
     * @return list<array{name: string, icon: string, rateLabel: string|null, gradeRates: list<array{label: string, rateLabel: string}>, editUrl: string}>
     */
    private static function subjectRates(TeacherProfile $profile): array
    {
        $settings = platform_settings();

        return $profile->subjects
            ->map(function (Subject $subject) use ($settings) {
                $gradeRates = collect($subject->pivot->grade_rates ?? [])
                    ->mapWithKeys(fn ($rate, $gradeId) => [(int) $gradeId => (int) $rate]);
                $subjectRate = (int) ($subject->pivot->rate_per_hour_minor ?? 0);

                if ($subjectRate === 0 && $gradeRates->isEmpty()) {
                    return null;
                }

                $grades = ($subject->educationLevel?->grades ?? collect())->keyBy('id');

                return [
                    'name' => (string) $subject->name,
                    'icon' => (string) ($subject->icon ?: '📘'),
                    'rateLabel' => $subjectRate > 0 ? $settings->formatMinor($subjectRate) : null,
                    'gradeRates' => $gradeRates
                        ->map(fn (int $rate, int $gradeId) => [
                            'label' => $grades->get($gradeId)?->label ?? __('Grade'),
                            'rateLabel' => $settings->formatMinor($rate),
                        ])
                        ->values()
                        ->all(),
                    'editUrl' => route('teacher.subjects.index'),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, list<array{local_time: string}>>  $preview
     * @return list<array{date: string, dateLabel: string, slots: list<array{time: string}>}>
     */
    private static function preview(array $preview): array
    {
        return collect($preview)
            ->map(fn (array $slots, string $date) => [
                'date' => $date,
                'dateLabel' => CarbonImmutable::parse($date)->format('D, d M'),
                'slots' => collect($slots)
                    ->map(fn (array $slot) => ['time' => display_clock($slot['local_time'])])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, TeacherTimeOff>  $entries
     * @return list<array{id: int, rangeLabel: string, reason: string, dayCountLabel: string, deleteUrl: string}>
     */
    private static function timeOff(Collection $entries): array
    {
        return $entries
            ->map(function (TeacherTimeOff $entry) {
                $sameDay = $entry->starts_on->isSameDay($entry->ends_on);
                $days = $entry->dayCount();

                return [
                    'id' => (int) $entry->id,
                    'rangeLabel' => $sameDay
                        ? $entry->starts_on->format('d M Y')
                        : $entry->starts_on->format('d M Y').' – '.$entry->ends_on->format('d M Y'),
                    'reason' => (string) ($entry->reason ?: __('Time off')),
                    'dayCountLabel' => trans_choice(':count day|:count days', $days, ['count' => $days]),
                    'deleteUrl' => route('teacher.availability.time-off.destroy', $entry),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The banner a redirect left behind, if any.
     *
     * @return array{message: string, tone: string, icon: string|null}|null
     */
    private static function flash(): ?array
    {
        $status = session('status');

        return match ($status) {
            'availability-week-saved' => [
                'message' => __('Weekly availability updated. Students can book inside these hours.'),
                'tone' => 'emerald',
                'icon' => 'check',
            ],
            'availability-pricing-updated' => [
                'message' => __('Default price updated. New bookings use the new rate.'),
                'tone' => 'emerald',
                'icon' => 'wallet',
            ],
            'availability-settings-updated' => [
                'message' => __('Lesson length updated.'),
                'tone' => 'emerald',
                'icon' => 'check',
            ],
            'time-off-added' => [
                'message' => __('Time off saved — those days are now blocked.'),
                'tone' => 'emerald',
                'icon' => 'calendar',
            ],
            'time-off-removed' => [
                'message' => __('Time off removed.'),
                'tone' => 'slate',
                'icon' => 'close',
            ],
            // The per-range endpoints stay for older links and the API-ish
            // fallbacks, so their flashes still have to resolve.
            'availability-slot-added' => [
                'message' => __('Weekly range added.'),
                'tone' => 'emerald',
                'icon' => 'calendar',
            ],
            'availability-slot-removed' => [
                'message' => __('Weekly range removed.'),
                'tone' => 'slate',
                'icon' => 'close',
            ],
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(string $timezone, int $lessonDuration): array
    {
        return [
            'hero' => [
                'eyebrow' => __('Availability and pricing'),
                'title' => __('Set your teaching hours and your price'),
                'intro' => __('Students can only book inside the hours you save here, and every price in the marketplace starts from your default hourly rate.'),
                'timezone' => __('Times shown in :timezone.', ['timezone' => $timezone]),
                'schedule' => __('View my schedule'),
                'chips' => [
                    'timezone' => $timezone,
                    'lessonLength' => __(':minutes-minute lessons', ['minutes' => $lessonDuration]),
                ],
            ],
            'errorTitle' => __('Please fix the following:'),
            'toastLabel' => __('Notifications'),
            'toastDismiss' => __('Dismiss'),

            // ---- The week editor (shared with onboarding step 2) -------------
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
            'save' => __('Save weekly availability'),
            'undo' => __('Undo changes'),
            'confirmPresetTitle' => __('Replace the week?'),
            'confirmPresetBody' => __('Loading this preset replaces the times currently in the editor.'),
            'confirmPresetConfirm' => __('Load preset'),
            'confirmClearTitle' => __('Clear the whole week?'),
            'confirmClearBody' => __('Every time range in the editor is removed. Nothing is saved until you press save.'),
            'confirmClearConfirm' => __('Clear week'),
            'confirmCancel' => __('Cancel'),
            'confirmClose' => __('Close'),
            'weekNote' => __('Removing a range does not cancel lessons that are already booked — it only stops new slots from appearing.'),

            // ---- Pricing -----------------------------------------------------
            'pricing' => [
                'title' => __('Lesson length and default price'),
                'description' => __('Every lesson starts from this hourly rate; subjects can override it below.'),
                'timezoneChip' => $timezone,
                'rateLabel' => __('Default hourly rate'),
                'rateHint' => __('Between :min and :max per hour.', [
                    'min' => platform_settings()->formatMinor(100 * 100),
                    'max' => platform_settings()->formatMinor(100000 * 100),
                ]),
                'ratePreview' => __('You earn about :amount per hour after the :percent% platform fee.', [
                    'amount' => ':amount',
                    'percent' => ':percent',
                ]),
                'rateSave' => __('Save default price'),
                'feeLabel' => __('Platform fee'),
                'lessonLengthLabel' => __('Lesson length'),
                'lessonLengthHint' => __('How long one lesson lasts. Every weekly range must be at least this long.'),
                'lessonLengthSave' => __('Save lesson length'),
                'subjectTitle' => __('Subject rates'),
                'subjectDescription' => __('Subjects with their own rate or per-grade prices. Everything else uses the default price.'),
                'subjectEmpty' => __('No subject overrides yet — every subject uses your default hour rate.'),
                'subjectDefault' => __('Default for this subject'),
                'editRates' => __('Edit rates'),
                'perHour' => __('/ hour'),
            ],

            // ---- Next bookable slots ----------------------------------------
            'preview' => [
                'title' => __('Next bookable slots'),
                'description' => __('A preview of what students see in the next seven days, in your local time.'),
                'emptyTitle' => __('Nothing bookable in the next 7 days'),
                'emptyBody' => __('Add a weekly range above, or remove some time off, and students can start booking those times.'),
            ],

            // ---- Time off ----------------------------------------------------
            'timeOff' => [
                'title' => __('Time off'),
                'description' => __('Blocked days disappear from your bookable slots. Lessons already booked on them stay booked.'),
                'emptyTitle' => __('No time off booked'),
                'emptyBody' => __('Add a date range when you are away and students stop seeing those slots.'),
                'fromLabel' => __('From'),
                'toLabel' => __('To'),
                'reasonLabel' => __('Reason (optional)'),
                'reasonPlaceholder' => __('Family function'),
                'add' => __('Add time off'),
                'remove' => __('Remove'),
                'removeTitle' => __('Remove this time off?'),
                'removeBody' => __('The :range block is deleted and those days become bookable again if your weekly ranges cover them.', ['range' => ':range']),
                'removeConfirm' => __('Remove time off'),
                'removeLabel' => __('Remove time off: :range', ['range' => ':range']),
            ],
        ];
    }
}
