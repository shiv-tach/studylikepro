<script setup>
import { computed } from 'vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const groups = computed(() => props.config.lessonGroups ?? []);
const grade = computed(() => props.config.subject?.grade ?? null);
</script>

<template>
    <section
        id="subject-panel-lessons"
        role="tabpanel"
        aria-labelledby="subject-tab-lessons"
        tabindex="0"
        class="mx-auto w-full max-w-6xl px-5 py-12 focus:outline-none lg:py-16"
    >
        <h2 class="font-display text-2xl font-semibold text-ink">{{ copy.lessonsTitle }}</h2>

        <div v-if="!groups.length" class="hairline mt-6 rounded-2xl bg-white p-10 text-center">
            <p class="text-sm text-ink-500">{{ grade ? copy.lessonsEmptyForGrade : copy.lessonsEmpty }}</p>
            <a
                v-if="grade"
                :href="grade.allGradesUrl"
                class="mt-4 inline-block text-sm font-medium text-brand-600 transition hover:text-brand-700"
            >{{ copy.viewAllGrades }}</a>
        </div>

        <div v-else class="mt-6 space-y-8">
            <div v-for="group in groups" :key="group.label">
                <h3 class="text-xs font-semibold tracking-widest text-ink-400 uppercase">
                    {{ group.label }} · {{ group.countLabel }}
                </h3>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <li
                        v-for="lesson in group.items"
                        :key="lesson.id"
                        class="hairline flex items-center gap-3 rounded-2xl bg-white px-4 py-3.5"
                    >
                        <span class="size-2 shrink-0 rounded-full bg-brand-500" aria-hidden="true"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-ink">{{ lesson.name }}</span>
                            <span v-if="lesson.nameSi" class="block text-xs text-ink-400">{{ lesson.nameSi }}</span>
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
