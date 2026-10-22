<script setup>
/**
 * The band above the catalog: what the teacher sells so far, and the one
 * primary action — create a service. Mirrors the subject workspace's hero so
 * the two setup screens feel like one product.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, usePage } from '../composables/usePageContext';
import { PRIMARY_BUTTON } from '../styles';

defineProps({
    canCreate: { type: Boolean, default: true },
});

const emit = defineEmits(['create']);

const copy = useCopy();
const page = usePage();

const chips = computed(() => {
    const stats = page.stats ?? {};

    if (!stats.total) {
        return [];
    }

    return [
        { key: 'total', icon: 'tag', value: copy.stats?.total },
        { key: 'active', icon: 'check-circle', value: copy.stats?.active },
        { key: 'paused', icon: 'pause', value: copy.stats?.paused },
        { key: 'sold', icon: 'credit-card', value: copy.stats?.sold },
    ];
});
</script>

<template>
    <section
        aria-labelledby="teacher-services-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    {{ copy.hero?.eyebrow }}
                </p>
                <h1 id="teacher-services-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ copy.hero?.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/90">
                    {{ copy.hero?.intro }}
                </p>

                <ul v-if="chips.length" class="mt-4 flex flex-wrap gap-2" :aria-label="copy.stats?.label">
                    <li
                        v-for="chip in chips"
                        :key="chip.key"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm"
                    >
                        <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon :name="chip.icon" /></span>
                        {{ chip.value }}
                    </li>
                </ul>
            </div>

            <div class="flex flex-col items-start gap-3 rounded-2xl border border-white/25 bg-white/10 px-4 py-4 backdrop-blur-sm sm:flex-row sm:items-center">
                <p class="max-w-[15rem] text-xs leading-relaxed text-white/80">
                    {{ copy.hero?.sideNote }}
                </p>

                <button
                    v-if="canCreate"
                    type="button"
                    :class="[PRIMARY_BUTTON, 'bg-white !text-primary shadow-black/10 hover:!bg-white/90 shrink-0']"
                    @click="emit('create')"
                >
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon name="plus" /></span>
                    {{ copy.hero?.cta }}
                </button>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
