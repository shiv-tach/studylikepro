<script setup>
/**
 * The compact band above the tabs: what the teacher has set up so far and the
 * base rate every unrated grade falls back to.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    teacher: { type: Object, default: () => ({}) },
    copy: { type: Object, default: () => ({}) },
});

const chips = computed(() => {
    const stats = props.stats ?? {};
    const copy = props.copy?.stats ?? {};
    const labels = stats.labels ?? {};
    const result = [];

    result.push({
        key: 'approved',
        icon: 'check-circle',
        value: labels.approved ?? `${stats.approved ?? 0} ${copy.approved ?? ''}`.trim(),
    });
    result.push({
        key: 'awaiting',
        icon: 'clock',
        value: labels.awaiting ?? `${stats.awaiting ?? 0} ${copy.awaiting ?? ''}`.trim(),
    });

    if (stats.grades > 0) {
        result.push({
            key: 'grades',
            icon: 'graduation',
            value: labels.grades ?? `${stats.grades} ${copy.grades ?? ''}`.trim(),
        });
    }

    if (stats.lessons > 0) {
        result.push({
            key: 'lessons',
            icon: 'book',
            value: labels.lessons ?? `${stats.lessons} ${copy.lessons ?? ''}`.trim(),
        });
    }

    return result;
});
</script>

<template>
    <section
        aria-labelledby="teacher-subjects-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    {{ copy.hero?.eyebrow }}
                </p>
                <h1 id="teacher-subjects-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ copy.hero?.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/90">
                    {{ copy.hero?.intro }}
                </p>

                <ul v-if="chips.length" class="mt-4 flex flex-wrap gap-2">
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

            <div class="rounded-2xl border border-white/25 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                    {{ copy.hero?.baseRate }}
                </p>
                <p class="mt-1 text-lg font-bold">{{ teacher.baseRateLabel }}</p>
                <p class="mt-0.5 max-w-[16rem] text-[11px] leading-relaxed text-white/70">
                    {{ copy.hero?.baseRateHint }}
                </p>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-20 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
