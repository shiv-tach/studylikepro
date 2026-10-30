<script setup>
/**
 * Question 8 — the days a teacher can teach, starting from one of the
 * suggested weeks so the clock times come with the choice.
 *
 * The day inputs stay in the DOM and are only hidden (never removed), so a
 * checked day still submits while a suggested week is selected — exactly what
 * the Alpine `x-show` version did.
 */
import { computed } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import OptionCard from '../OptionCard.vue';
import FieldMessages from '../FieldMessages.vue';

const { config, form } = useSurveyContext();

const copy = computed(() => config.questionCopy);

const showDays = computed(() => (form.values.preset ?? '') === ''
    || form.errorsFor('days').length > 0
    || form.errorsUnder('days.').length > 0);

function applyPreset(key) {
    const days = config.options.presetDays?.[key];

    if (Array.isArray(days)) {
        form.values.days = [...days];
    }
}
</script>

<template>
    <fieldset>
        <legend class="sr-only">{{ config.title }}</legend>

        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ copy.presetsTitle }}</p>
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.presetsHint }}</p>

        <div class="mt-3 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <OptionCard
                v-for="preset in config.options.presets"
                :key="preset.key"
                v-model="form.values.preset"
                name="preset"
                :value="preset.key"
                :title="preset.label"
                :hint="preset.hint"
                :badge="preset.recommended ? copy.recommended : null"
                @change="applyPreset(preset.key)"
            >
                <span class="mt-2 block space-y-0.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                    <span v-for="window in preset.windows" :key="`${window.days}-${window.time}`" class="block">{{ window.days }} · {{ window.time }}</span>
                </span>
            </OptionCard>

            <OptionCard
                v-model="form.values.preset"
                name="preset"
                value=""
                :title="copy.ownDaysTitle"
                :hint="copy.ownDaysHint"
            />
        </div>

        <FieldMessages :messages="form.errorsFor('preset')" />

        <div v-show="showDays" class="mt-5">
            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ copy.daysTitle }}</p>

            <div class="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <OptionCard
                    v-for="day in config.options.days"
                    :key="day.value"
                    v-model="form.values.days"
                    type="checkbox"
                    name="days[]"
                    :value="day.value"
                    :title="day.label"
                    :meta="config.options.dayTimes?.[day.value] ?? null"
                    align="center"
                />
            </div>
        </div>

        <FieldMessages :messages="form.errorsFor('days')" />
        <FieldMessages :messages="form.errorsUnder('days.')" class="mt-1" />

        <p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs leading-relaxed text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">
            {{ copy.availabilityNote }}
        </p>
    </fieldset>
</template>
