import { computed, inject, reactive, ref } from 'vue';

export const WEEK_KEY = Symbol('teacher-availability-setup.week');

/**
 * The availability step is one form that edits the whole week, so the days,
 * the presets, the dirty state and the weekly summary live here. Blade sends
 * the starting week (saved ranges, a replayed rejected save, or the recommended
 * preset) and both the editor and the summary card inject this one object.
 */
export function createWeekEditor(config) {
    const limits = config.limits ?? {};
    const maxRangesPerDay = limits.maxRangesPerDay ?? 4;
    const lessonDuration = config.profile?.lessonDuration ?? 60;
    const presets = config.presets ?? [];
    const errors = config.errors ?? {};

    const clone = (source) => (source ?? []).map((day) => ({
        value: day.value,
        label: day.label ?? '',
        shortLabel: day.shortLabel ?? '',
        enabled: Boolean(day.enabled),
        ranges: (day.ranges ?? []).map((range) => ({
            start: range.start ?? '',
            end: range.end ?? '',
        })),
    }));

    const initial = clone(config.week);
    const days = reactive(clone(config.week));

    const submitting = ref(false);

    // A stable snapshot makes "unsaved changes" work for rows added, removed and
    // retyped, not just for the inputs that exist at load time.
    const snapshot = computed(() => JSON.stringify(days));
    const dirty = computed(() => snapshot.value !== JSON.stringify(initial));

    function toggleDay(day) {
        day.enabled = !day.enabled;

        if (day.enabled && day.ranges.length === 0) {
            day.ranges.push({ start: '', end: '' });
        }

        if (!day.enabled) {
            day.ranges = [];
        }
    }

    function addRange(day) {
        if (day.ranges.length >= maxRangesPerDay) {
            return;
        }

        day.enabled = true;
        day.ranges.push({ start: '', end: '' });
    }

    function removeRange(day, index) {
        day.ranges.splice(index, 1);

        if (day.ranges.length === 0) {
            day.enabled = false;
        }
    }

    function applyPreset(preset) {
        days.forEach((day) => {
            const ranges = preset.days?.[String(day.value)] ?? preset.days?.[day.value] ?? [];

            day.ranges = ranges.map((range) => ({ start: range.start, end: range.end }));
            day.enabled = day.ranges.length > 0;
        });
    }

    function clearWeek() {
        days.forEach((day) => {
            day.enabled = false;
            day.ranges = [];
        });
    }

    function undo() {
        days.forEach((day, index) => {
            const source = initial[index] ?? { enabled: false, ranges: [] };

            day.enabled = Boolean(source.enabled);
            day.ranges = source.ranges.map((range) => ({ ...range }));
        });
    }

    function minutesOf(range) {
        if (!range.start || !range.end) {
            return 0;
        }

        const [startHours, startMinutes] = range.start.split(':').map(Number);
        const [endHours, endMinutes] = range.end.split(':').map(Number);
        const start = (startHours * 60) + startMinutes;
        const end = (endHours * 60) + endMinutes;

        return Math.max(0, end - start);
    }

    const enabledDays = computed(() => days.filter((day) => day.enabled));

    const hasAnyRange = computed(() => enabledDays.value.some((day) => day.ranges.length > 0));

    const hasIncompleteRange = computed(() => enabledDays.value.some(
        (day) => day.ranges.some((range) => !range.start || !range.end),
    ));

    const totalMinutes = computed(() => enabledDays.value.reduce(
        (total, day) => total + day.ranges.reduce((sum, range) => sum + minutesOf(range), 0),
        0,
    ));

    const daysPerWeek = computed(() => enabledDays.value.filter((day) => day.ranges.length > 0).length);

    const hoursPerWeek = computed(() => totalMinutes.value / 60);

    const hoursLabel = computed(() => (
        Number.isInteger(hoursPerWeek.value)
            ? String(hoursPerWeek.value)
            : hoursPerWeek.value.toFixed(1)
    ));

    const lessonsPerWeek = computed(() => Math.floor(totalMinutes.value / lessonDuration));

    const canSave = computed(() => hasAnyRange.value && !hasIncompleteRange.value);

    /**
     * Laravel keys its messages positionally, exactly as the rows submit them.
     */
    function rowErrors(index, row, field) {
        return errors[`days.${index}.ranges.${row}.${field}`] ?? [];
    }

    function dayErrors(index) {
        return errors[`days.${index}.ranges`] ?? [];
    }

    return {
        days,
        presets,
        errors,
        maxRangesPerDay,
        lessonDuration,
        submitting,
        dirty,
        canSave,
        hasAnyRange,
        hasIncompleteRange,
        daysPerWeek,
        hoursPerWeek,
        hoursLabel,
        lessonsPerWeek,
        toggleDay,
        addRange,
        removeRange,
        applyPreset,
        clearWeek,
        undo,
        rowErrors,
        dayErrors,
    };
}

export function useWeekEditor() {
    return inject(WEEK_KEY, null);
}
