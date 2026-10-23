<script setup>
/**
 * The onboarding call-outs: the welcome card a brand-new teacher sees and the
 * "step 1 complete — next: …" card that hands them to the next step (weekly
 * availability, or verification once a week is set). The action is optional, so
 * the same surface covers both.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';

defineProps({
    icon: { type: String, default: 'sparkles' },
    title: { type: String, required: true },
    body: { type: String, required: true },
    action: { type: Object, default: null },
});

const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary">
                    <AppIcon :name="icon" />
                </span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ body }}</p>
                </div>
            </div>

            <a
                v-if="action"
                :href="action.url"
                class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-all duration-200 hover:bg-primary/90 hover:shadow-lg active:scale-[0.98]"
            >
                {{ action.label }}
                <span aria-hidden="true">→</span>
            </a>
        </div>
    </section>
</template>
