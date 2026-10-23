<script setup>
/**
 * The editable week: one row per day with its own on/off switch and as many
 * time ranges as the teacher needs (the data model has always allowed split
 * days; this is the first screen that lets them set that up in one place).
 *
 * The inputs keep the names Laravel validates (`days[i][ranges][j][start_time]`),
 * so the form posts exactly the shape `WeeklyAvailabilityRequest` reads.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useWeekEditor } from '../composables/useWeekEditor';
import { FIELD } from '../styles';

const copy = useCopy();
const panel = usePanel();

const {
    days,
    maxRangesPerDay,
    lessonDuration,
    toggleDay,
    addRange,
    removeRange,
    rowErrors,
    dayErrors,
} = useWeekEditor();

function fieldError(index, row, field) {
    const messages = rowErrors(index, row, field);

    return messages.length > 0 ? messages : null;
}
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.editorTitle" :description="copy.editorHint" icon="calendar" level="h3" />

        <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400">
            <span>{{ copy.timezoneNote }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ format(copy.lessonLengthNote, { minutes: lessonDuration }) }}</span>
        </div>

        <ul class="mt-5 space-y-3">
            <li
                v-for="(day, index) in days"
                :key="day.value"
                class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800"
            >
                <!-- The weekday travels even when the day is off, so the server
                     always receives the full week. -->
                <input type="hidden" :name="`days[${index}][value]`" :value="day.value">

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex cursor-pointer items-center gap-2.5">
                        <input
                            type="checkbox"
                            :name="`days[${index}][enabled]`"
                            value="1"
                            :checked="day.enabled"
                            :aria-label="format(copy.dayToggle, { day: day.label })"
                            class="h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
                            @change="toggleDay(day)"
                        >
                        <span class="w-24 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ day.label }}</span>
                        <span
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="day.enabled
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
                        >
                            {{ day.enabled ? copy.dayAvailable : copy.dayUnavailable }}
                        </span>
                    </label>

                    <button
                        v-if="day.enabled"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:text-slate-300"
                        :disabled="day.ranges.length >= maxRangesPerDay"
                        @click="addRange(day)"
                    >
                        + {{ copy.addRange }}
                    </button>
                </div>

                <div v-if="day.enabled" class="mt-3 space-y-2">
                    <p v-if="day.ranges.length === 0" class="text-xs font-semibold text-amber-600 dark:text-amber-400">
                        {{ copy.noRanges }}
                    </p>

                    <div
                        v-for="(range, row) in day.ranges"
                        :key="row"
                        class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/40"
                    >
                        <div class="flex flex-wrap items-end gap-3">
                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" :for="`day-${index}-${row}-start`">
                                    {{ copy.fromLabel }}
                                </label>
                                <input
                                    :id="`day-${index}-${row}-start`"
                                    v-model="range.start"
                                    type="time"
                                    :name="`days[${index}][ranges][${row}][start_time]`"
                                    :class="FIELD"
                                    class="mt-1 px-3 py-2"
                                >
                            </div>

                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400" :for="`day-${index}-${row}-end`">
                                    {{ copy.toLabel }}
                                </label>
                                <input
                                    :id="`day-${index}-${row}-end`"
                                    v-model="range.end"
                                    type="time"
                                    :name="`days[${index}][ranges][${row}][end_time]`"
                                    :class="FIELD"
                                    class="mt-1 px-3 py-2"
                                >
                            </div>

                            <button
                                type="button"
                                class="mb-0.5 flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                                :aria-label="copy.removeRange"
                                @click="removeRange(day, row)"
                            >
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <ul v-if="fieldError(index, row, 'start_time') || fieldError(index, row, 'end_time')" class="mt-2 space-y-1" role="alert">
                            <li
                                v-for="(message, key) in [...(fieldError(index, row, 'start_time') ?? []), ...(fieldError(index, row, 'end_time') ?? [])]"
                                :key="key"
                                class="text-xs font-medium text-rose-600 dark:text-rose-400"
                            >
                                {{ message }}
                            </li>
                        </ul>
                    </div>

                    <p class="text-xs text-slate-400">{{ copy.rangesHint }}</p>
                </div>

                <ul v-if="dayErrors(index).length" class="mt-2 space-y-1" role="alert">
                    <li
                        v-for="(message, key) in dayErrors(index)"
                        :key="key"
                        class="text-xs font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ message }}
                    </li>
                </ul>
            </li>
        </ul>
    </section>
</template>
