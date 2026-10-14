<script setup>
/**
 * The ready-made service catalog, grouped in service-type order the way a
 * teacher's own services list reads.
 */
import { useCopy } from '../composables/usePageContext';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import ServiceOfferingCard from './ServiceOfferingCard.vue';

defineProps({
    groups: { type: Array, default: () => [] },
    directoryUrl: { type: String, default: null },
});

const copy = useCopy();</script>

<template>
    <section>
        <UiSectionHeading
            :title="copy.servicesTitle"
            :description="copy.servicesIntro"
            icon="tag"
        />

        <UiEmptyState
            v-if="groups.length === 0"
            class="mt-4"
            icon="tag"
            :message="copy.servicesEmpty"
            :link-label="copy.browseTeachers"
            :link-href="directoryUrl"
        />

        <div v-else class="mt-5 space-y-8">
            <div v-for="group in groups" :key="group.key">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-lg">
                        {{ group.icon }}
                    </span>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ group.label }}</h3>
                    <span
                        class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                    >
                        {{ group.items.length }}
                    </span>
                </div>

                <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <ServiceOfferingCard
                        v-for="offering in group.items"
                        :key="offering.id"
                        :offering="offering"
                    />
                </div>
            </div>
        </div>
    </section>
</template>
