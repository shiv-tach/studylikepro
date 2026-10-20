<script setup>
/**
 * Only the grades this teacher takes, grouped by level, each with the rate a
 * student would pay — no subject or lesson sprawl beyond the grade they belong
 * to. The rate strings arrive pre-formatted from Blade.
 */
defineProps({
    grades: { type: Object, required: true },
    copy: { type: Object, required: true },
});
</script>

<template>
    <section id="grades" class="hairline rounded-2xl bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-semibold">{{ copy.gradesTitle }}</h2>
                <p class="mt-1 max-w-xl text-sm text-ink-500">{{ copy.gradesIntro }}</p>
            </div>
            <span
                v-if="grades.summary"
                class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-600"
            >{{ grades.summary }}</span>
        </div>

        <p
            v-if="!grades.groups?.length"
            class="mt-4 rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-ink-500"
        >
            {{ copy.gradesEmpty }}
        </p>

        <div v-else class="mt-5 space-y-5">
            <div v-for="group in grades.groups" :key="group.level">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h3 class="text-[10px] font-semibold tracking-widest text-ink-400 uppercase">{{ group.level }}</h3>
                    <span v-if="group.range" class="text-[11px] text-ink-400">· {{ group.range }}</span>
                </div>

                <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                    <li
                        v-for="grade in group.grades"
                        :key="grade.id"
                        class="rounded-xl border border-line bg-white px-3 py-2 transition hover:border-brand-300"
                    >
                        <p class="text-sm font-semibold text-ink">{{ grade.chip }}</p>
                        <p v-if="grade.subjects.length" class="mt-0.5 text-xs text-ink-500">
                            {{ grade.subjects.join(' · ') }}
                        </p>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</template>
