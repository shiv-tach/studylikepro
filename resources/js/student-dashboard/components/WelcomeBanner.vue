<script setup>
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    appName: { type: String, default: 'Studylikepro' },
    user: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const chips = computed(() =>
    [
        props.user.grade ? { key: 'grade', emoji: '🎓', value: props.user.grade } : null,
        props.user.learningLanguage ? { key: 'language', emoji: '🗣️', value: props.user.learningLanguage } : null,
        props.user.timezone ? { key: 'timezone', emoji: '🕐', value: props.user.timezone } : null,
    ].filter(Boolean),
);
</script>

<template>
    <section
        aria-labelledby="dashboard-welcome"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 max-w-2xl">
            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">{{ appName }}</p>
            <h1 id="dashboard-welcome" class="mt-1 text-xl font-bold md:text-2xl">Welcome, {{ user.name }}! 👋</h1>
            <p class="mt-2 text-sm text-white/90">
                Studylikepro connects you with verified tutors for live 1-on-1 lessons. Find the right subject,
                book a time that works for you, and join the classroom.
            </p>

            <ul v-if="chips.length" class="mt-4 flex flex-wrap gap-2">
                <li
                    v-for="chip in chips"
                    :key="chip.key"
                    class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm"
                >
                    <span aria-hidden="true">{{ chip.emoji }}</span>{{ chip.value }}
                </li>
            </ul>

            <div class="mt-6 flex flex-wrap gap-3">
                <a
                    :href="urls.subjects"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-primary"
                >
                    <span class="h-4 w-4"><AppIcon name="book" /></span>
                    Explore my subjects
                </a>
                <a
                    :href="urls.teachers"
                    class="inline-flex items-center gap-2 rounded-xl border border-white/40 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition-colors hover:bg-white/20"
                >
                    <span class="h-4 w-4"><AppIcon name="users" /></span>
                    Find a teacher
                </a>
            </div>
        </div>

        <div class="absolute -mr-6 -mt-6 right-0 top-0 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="absolute -mb-10 bottom-0 right-20 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
