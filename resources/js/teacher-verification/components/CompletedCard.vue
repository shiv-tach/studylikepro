<script setup>
/**
 * Once the documents are in review there is nothing left to edit on this page,
 * so it closes the flow. Green is reserved for an approved profile; while the
 * application is waiting it reads as a review state, not as "done".
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

const props = defineProps({
    status: { type: Object, default: () => ({}) },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const approved = computed(() => (props.status?.current ?? 'pending') === 'approved');

const title = computed(() => (approved.value ? copy.completeTitle : copy.reviewTitle));
const body = computed(() => (approved.value ? copy.completeBody : copy.reviewBody));
const cta = computed(() => (approved.value ? copy.completeCta : copy.reviewCta));
const href = computed(() => (approved.value ? urls.dashboard : (urls.submitted ?? urls.dashboard)));
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span
                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl p-2.5"
                    :class="approved
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                        : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'"
                >
                    <AppIcon :name="approved ? 'check-circle' : 'clock'" />
                </span>

                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ body }}</p>
                </div>
            </div>

            <a
                v-if="href"
                :href="href"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg active:scale-[0.98]"
            >
                {{ cta }}
                <span class="h-4 w-4"><AppIcon name="arrow-right" /></span>
            </a>
        </div>
    </section>
</template>
