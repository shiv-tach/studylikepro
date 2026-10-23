<script setup>
/**
 * What students actually see: the next open slots, grouped by the teacher's
 * local day. Blade formats the clock times and the day labels, so this card
 * never has to guess a timezone.
 */
import { computed } from 'vue';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const days = computed(() => page.preview ?? []);
</script>

<template>
    <section :class="panel">
        <UiSectionHeading
            :title="copy.preview?.title"
            :description="copy.preview?.description"
            icon="calendar"
            level="h3"
        />

        <UiEmptyState
            v-if="!days.length"
            class="mt-4"
            icon="calendar"
            :message="`${copy.preview?.emptyTitle}. ${copy.preview?.emptyBody}`"
        />

        <div v-else class="mt-4 space-y-4">
            <div v-for="day in days" :key="day.date">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ day.dateLabel }}</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    <span
                        v-for="slot in day.slots"
                        :key="`${day.date}-${slot.time}`"
                        class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary"
                    >
                        {{ slot.time }}
                    </span>
                </div>
            </div>
        </div>
    </section>
</template>
