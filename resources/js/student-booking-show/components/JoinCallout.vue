<script setup>
/**
 * The "classroom is open" callout. Blade decides whether the room is live — the
 * status, the meeting state and the join window all have to line up — so this
 * panel only renders the teacher, the closing time and the join link it was
 * handed.
 */
defineProps({
    join: { type: Object, required: true },
});
</script>

<template>
    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xl dark:bg-emerald-900/40">🎥</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ join.title }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ join.body }}</p>
                </div>
            </div>

            <a
                :href="join.url"
                class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90"
            >
                {{ join.ctaLabel }} &nearr;
            </a>
        </div>
    </div>
</template>
