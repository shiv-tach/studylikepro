<script setup>
/**
 * Steps two and three of the stepper, wrapped in the real booking form POST.
 * Blade has already scoped the slots to the chosen subject, lesson length and
 * the learner's grade and converted every time to the viewer's timezone, so this
 * card only groups them and collects the learner's name. The selected slot is
 * the one value Laravel re-validates against the teacher's open slots.
 */
import { computed, ref } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';

const props = defineProps({
    selection: { type: Object, required: true },
    slots: { type: Array, default: () => [] },
    slotsNotice: { type: String, default: null },
    holdNotice: { type: String, required: true },
    selectedSlot: { type: String, default: null },
    learner: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    summary: { type: Object, required: true },
});

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();
const panel = usePanel();

const slot = ref(props.selectedSlot ?? '');
const learnerName = ref(props.learner.name ?? '');

// A slot has to be picked before the form can reserve it.
const canSubmit = computed(() => props.slots.length > 0 && slot.value !== '');

const labelClass = 'block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400';
const fieldClass =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
const primaryClass =
    'inline-flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50';
</script>

<template>
    <form method="POST" :action="urls.store" :class="panel">
        <input type="hidden" name="_token" :value="csrfToken">
        <input type="hidden" name="subject_id" :value="selection.subjectId">
        <input type="hidden" name="lesson_id" :value="selection.lessonId">
        <input type="hidden" name="duration" :value="selection.duration">

        <UiSectionHeading :title="copy.stepTime" icon="calendar" level="h3" />

        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ holdNotice }}</p>

        <p v-if="errors.startsAt" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">{{ errors.startsAt }}</p>

        <p
            v-if="slots.length === 0"
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ slotsNotice }}
        </p>

        <div v-else class="mt-4 space-y-5">
            <div v-for="group in slots" :key="group.date">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ group.label }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <label v-for="option in group.slots" :key="option.value" class="cursor-pointer">
                        <input v-model="slot" type="radio" name="starts_at" :value="option.value" class="peer sr-only">
                        <span
                            class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300"
                        >
                            {{ option.label }}
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="mt-6 border-t border-slate-200 pt-6 dark:border-slate-800">
            <UiSectionHeading :title="copy.stepWho" icon="user" level="h3" />

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="learner_name" :class="labelClass">{{ copy.learnerName }}</label>
                    <input
                        id="learner_name"
                        v-model="learnerName"
                        name="learner_name"
                        type="text"
                        :class="fieldClass"
                    >
                    <p v-if="errors.learnerName" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">
                        {{ errors.learnerName }}
                    </p>
                </div>

                <div>
                    <span :class="labelClass">{{ copy.grade }}</span>
                    <p class="mt-1 rounded-xl border border-slate-200/80 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-200">
                        {{ learner.gradeLabel }}
                    </p>
                    <p class="mt-1 text-xs text-slate-400">
                        {{ learner.gradeHint }}
                        <a :href="learner.changeUrl" class="font-semibold text-primary hover:underline">{{ learner.changeLabel }}</a>
                    </p>
                </div>
            </div>

            <p class="mt-3 text-xs text-slate-400">{{ learner.note }}</p>
        </div>

        <div class="mt-6 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 dark:border-slate-800">
            <p class="text-sm text-slate-500 dark:text-slate-400">
                {{ copy.total }}
                <span class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ summary.total }}</span>
                <span class="ml-1 text-xs text-slate-400">{{ summary.includesFee }}</span>
            </p>
            <button type="submit" :class="primaryClass" :disabled="!canSubmit">
                {{ copy.reserve }}
            </button>
        </div>
    </form>
</template>
