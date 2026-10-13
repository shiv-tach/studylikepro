<script setup>
import { computed, ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { levels } from '../content';

defineProps({
    urls: { type: Object, required: true },
});

const activeKey = ref(levels[0].key);
const activeLevel = computed(() => levels.find((level) => level.key === activeKey.value) ?? levels[0]);
</script>

<template>
    <section id="subjects" class="border-t border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900">
        <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-widest text-primary">Subjects &amp; grades</p>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    The Sri Lankan syllabus, level by level
                </h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">
                    Every subject belongs to one level and carries a lesson list for each grade, so the help you book
                    always lines up with the work on your desk.
                </p>
            </div>

            <div class="mt-8 flex flex-wrap gap-2">
                <button
                    v-for="level in levels"
                    :key="level.key"
                    type="button"
                    :aria-pressed="activeKey === level.key"
                    class="rounded-xl border px-4 py-2 text-sm font-semibold transition"
                    :class="activeKey === level.key
                        ? 'border-primary bg-primary text-white shadow-md shadow-primary/20'
                        : 'border-slate-200/80 bg-slate-50/60 text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-950/40 dark:text-slate-300'"
                    @click="activeKey = level.key"
                >
                    {{ level.name }}
                    <span class="ml-1.5 text-xs font-medium opacity-80">{{ level.grades }}</span>
                </button>
            </div>

            <div
                class="mt-6 rounded-2xl border border-slate-200/80 bg-slate-50/50 p-6 dark:border-slate-800/80 dark:bg-slate-950/40 sm:p-8"
            >
                <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                    <div class="max-w-md">
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ activeLevel.name }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ activeLevel.blurb }}</p>
                        <a
                            :href="urls.subjects"
                            class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
                        >
                            Browse the full catalog
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>

                    <ul class="grid w-full gap-2.5 sm:grid-cols-2 lg:max-w-xl">
                        <li
                            v-for="subject in activeLevel.subjects"
                            :key="`${activeLevel.key}-${subject.name}`"
                            class="flex items-center gap-3 rounded-xl border border-slate-200/80 bg-white px-3.5 py-2.5 text-sm font-medium text-slate-700 transition-colors hover:border-primary/40 dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <span class="text-base">{{ subject.icon }}</span>
                            {{ subject.name }}
                        </li>
                    </ul>
                </div>
            </div>

            <p class="mt-4 text-xs text-slate-400 dark:text-slate-500">
                Levels: Primary (Grades 1–5) · O/L (Grades 6–11, with the optional subject baskets) · A/L (Grades 12–13) · Other.
            </p>
        </div>
    </section>
</template>
