<script setup>
/**
 * What is still missing from the profile, counted from exactly the fields the
 * form holds — the same answers the server validates, so the meter can never
 * claim more than the form actually has.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { DONE_RING, EMPTY_RING } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { strength } = useProfileForm();
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.strengthTitle" icon="check-circle" level="h3" />

        <ul class="mt-5 space-y-2.5">
            <li v-for="step in strength.steps" :key="step.key" class="flex items-center gap-2.5 text-sm">
                <span
                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border"
                    :class="step.filled ? DONE_RING : EMPTY_RING"
                >
                    <span v-if="step.filled" class="h-3 w-3 text-emerald-600 dark:text-emerald-400">
                        <AppIcon name="check" />
                    </span>
                </span>

                <span :class="step.filled ? 'text-slate-700 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400'">
                    {{ copy.strengthItems[step.key] }}
                </span>

                <span
                    class="ml-auto text-[11px] font-semibold uppercase tracking-wide"
                    :class="step.filled ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
                >
                    {{ step.filled ? copy.strengthDone : copy.strengthTodo }}
                </span>
            </li>
        </ul>

        <p class="mt-5 text-xs text-slate-400">{{ copy.strengthHint }}</p>
    </section>
</template>
