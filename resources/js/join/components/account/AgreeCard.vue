<script setup>
/**
 * The terms acceptance the account cannot be created without. A rejected save
 * replays the tick from `old('agree')` (the island's `values.agree`).
 */
import { computed } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import FieldMessages from '../FieldMessages.vue';

const { config, form } = useSurveyContext();

const copy = computed(() => config.copy);
</script>

<template>
    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6">
        <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
            <input
                v-model="form.values.agree"
                type="checkbox"
                name="agree"
                value="1"
                required
                class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
            />
            <span>
                <span class="block font-semibold text-slate-800 dark:text-slate-100">{{ copy.agreeTitle }}</span>
                <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ copy.agreePrefix }}
                    <a :href="config.urls.terms" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ copy.teacherTerms }}</a>
                    {{ copy.agreeAnd }}
                    <a :href="config.urls.privacy" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ copy.privacy }}</a>{{ copy.agreeSuffix }}
                </span>
            </span>
        </label>

        <FieldMessages :messages="form.errorsFor('agree')" />
    </div>
</template>
