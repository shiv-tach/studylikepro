<script setup>
/**
 * The fields that only exist for one kind of service: the plan's total time and
 * session cap, the live session length, and paper marking's page limit and
 * turnaround promise. Only the groups the selected type needs are rendered.
 */
import { computed } from 'vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy, usePage, useWorkspace } from '../composables/usePageContext';
import { FIELD } from '../styles';

const copy = useCopy();
const page = usePage();
const workspace = useWorkspace();

const {
    values,
    errors,
    currentType,
    hoursMax,
    allocationHours,
    allocationMinutesPart,
} = workspace;

const showAllocation = computed(() => currentType.value?.value === 'lesson');
const showSession = computed(() => Boolean(currentType.value?.isLiveSession));
const showCap = computed(() => currentType.value?.value === 'lesson');
const showPaper = computed(() => currentType.value?.value === 'paper_marking');

const hourOptions = computed(() => Array.from(
    { length: Math.max(1, Number(hoursMax.value) || 99) },
    (_, index) => index + 1,
));

const durations = computed(() => page.options?.durations ?? []);
</script>

<template>
    <div v-if="showAllocation || showSession || showPaper" class="grid gap-4 sm:grid-cols-2">
        <UiFormField
            v-if="showAllocation"
            :label="copy.fields?.allocation"
            :hint="copy.fields?.allocationHint"
            :errors="errors.allocation_minutes ?? []"
            required
        >
            <div class="flex items-center gap-2">
                <label class="sr-only" for="service-allocation-hours">{{ copy.fields?.hours }}</label>
                <select
                    id="service-allocation-hours"
                    v-model.number="allocationHours"
                    class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <option v-for="hour in hourOptions" :key="hour" :value="hour">{{ hour }}h</option>
                </select>

                <label class="sr-only" for="service-allocation-minutes">{{ copy.fields?.minutes }}</label>
                <select
                    id="service-allocation-minutes"
                    v-model.number="allocationMinutesPart"
                    :disabled="allocationHours >= hoursMax"
                    class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
                    <option :value="0">0m</option>
                    <option :value="30">30m</option>
                </select>
            </div>

            <input type="hidden" name="allocation_minutes" :value="values.allocationMinutes ?? 60">
        </UiFormField>

        <UiFormField
            v-if="showSession"
            :label="copy.fields?.session"
            for-id="service-session"
            :hint="copy.fields?.sessionHint"
            :errors="errors.default_session_minutes ?? []"
        >
            <select
                id="service-session"
                v-model="values.defaultSessionMinutes"
                name="default_session_minutes"
                :class="FIELD"
            >
                <option value="">{{ copy.fields?.sessionNone }}</option>
                <option v-for="duration in durations" :key="duration" :value="duration">
                    {{ duration }} {{ copy.fields?.minutes }}
                </option>
            </select>
        </UiFormField>

        <UiFormField
            v-if="showCap"
            :label="copy.fields?.maxSessions"
            for-id="service-max-sessions"
            :hint="copy.fields?.maxSessionsHint"
            :errors="errors.max_sessions ?? []"
        >
            <input
                id="service-max-sessions"
                v-model="values.maxSessions"
                name="max_sessions"
                type="number"
                :min="page.limits?.maxSessionsMin ?? 1"
                :max="page.limits?.maxSessionsMax ?? 20"
                :placeholder="copy.fields?.maxSessionsPlaceholder"
                :class="FIELD"
            >
        </UiFormField>

        <UiFormField
            v-if="showPaper"
            :label="copy.fields?.maxPages"
            for-id="service-max-pages"
            :errors="errors.max_pages ?? []"
        >
            <input
                id="service-max-pages"
                v-model="values.maxPages"
                name="max_pages"
                type="number"
                :min="page.limits?.maxPagesMin ?? 1"
                :max="page.limits?.maxPagesMax ?? 500"
                :placeholder="copy.fields?.maxPagesPlaceholder"
                :class="FIELD"
            >
        </UiFormField>

        <UiFormField
            v-if="showPaper"
            :label="copy.fields?.turnaround"
            for-id="service-turnaround"
            :hint="copy.fields?.turnaroundHint"
            :errors="errors.turnaround_hours ?? []"
            required
        >
            <input
                id="service-turnaround"
                v-model="values.turnaroundHours"
                name="turnaround_hours"
                type="number"
                :min="page.limits?.turnaroundMin ?? 1"
                :max="page.limits?.turnaroundMax ?? 720"
                :placeholder="copy.fields?.turnaroundPlaceholder"
                :class="FIELD"
                required
            >
        </UiFormField>
    </div>
</template>
