<script setup>
/**
 * When this teacher is free, in the viewer's own timezone. Blade has already
 * converted every slot, dropped the ones already booked and cut the list to the
 * next two weeks, so this panel only groups what it was handed.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    availability: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.availabilityTitle" icon="calendar">
            <template #action>
                <UiChip>{{ availability.timezoneLabel }}</UiChip>
            </template>
        </UiSectionHeading>

        <p
            v-if="availability.empty"
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ copy.noSlots }}
        </p>

        <div v-else class="mt-4 space-y-4">
            <div v-for="day in availability.days" :key="day.label">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ day.label }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="slot in day.slots"
                        :key="slot"
                        class="rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 dark:border-slate-800 dark:text-slate-300"
                    >
                        {{ slot }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
