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
    <section id="subjects" class="scroll-mt-20 border-b border-line bg-ink-50 py-16 lg:py-24">
        <div class="mx-auto w-full max-w-6xl px-5">
            <p class="text-sm font-medium text-brand-600">Subjects &amp; grades</p>
            <h2 class="mt-3 max-w-3xl text-display-lg font-semibold">The Sri Lankan syllabus, level by level</h2>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">
                Every subject belongs to one level and carries a lesson list for each grade, so the help you book
                always lines up with the work on your desk.
            </p>

            <div class="mt-8 flex flex-wrap gap-2">
                <button
                    v-for="level in levels"
                    :key="level.key"
                    type="button"
                    :aria-pressed="activeKey === level.key"
                    class="rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="activeKey === level.key
                        ? 'bg-ink text-white'
                        : 'hairline bg-white text-ink-700 hover:border-ink-500'"
                    @click="activeKey = level.key"
                >
                    {{ level.name }}
                    <span class="ml-1.5 text-xs opacity-70">{{ level.grades }}</span>
                </button>
            </div>

            <div class="hairline mt-6 rounded-2xl bg-white p-7">
                <div class="grid gap-8 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] lg:gap-12">
                    <div class="min-w-0">
                        <h3 class="font-display text-xl font-semibold text-ink">{{ activeLevel.name }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-700">{{ activeLevel.blurb }}</p>
                        <a
                            :href="urls.subjects"
                            class="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-600 underline underline-offset-4"
                        >
                            Browse the full catalog
                            <AppIcon name="arrow-right" class="h-4 w-4" />
                        </a>
                    </div>

                    <ul class="flex min-w-0 flex-wrap gap-1.5">
                        <li
                            v-for="subject in activeLevel.subjects"
                            :key="`${activeLevel.key}-${subject.name}`"
                            class="inline-flex items-center gap-1.5 rounded-full bg-ink-50 px-3 py-1.5 text-sm font-medium text-ink-700"
                        >
                            <span aria-hidden="true">{{ subject.icon }}</span>
                            {{ subject.name }}
                        </li>
                    </ul>
                </div>
            </div>

            <p class="mt-6 text-xs text-ink-500">
                Levels: Primary (Grades 1–5) · O/L (Grades 6–11, with the optional subject baskets) · A/L (Grades 12–13) · Other.
            </p>
        </div>
    </section>
</template>
