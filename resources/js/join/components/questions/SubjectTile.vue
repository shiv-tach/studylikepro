<script setup>
/**
 * One subject of the survey picker. The real checkbox carries `subjects[]`, so
 * the form posts exactly the ids the controller validates; the twin suggestion
 * under the tile offers the same-named subject of the other picked level, which
 * is how a "both levels" teacher avoids ticking O/L ICT and missing A/L ICT.
 */
defineProps({
    subject: { type: Object, required: true },
    checked: { type: Boolean, default: false },
    showLevel: { type: Boolean, default: false },
    suggestions: { type: Array, default: () => [] },
    copy: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['toggle', 'add']);

function label(template, values = {}) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.split(`:${key}`).join(String(value)),
        template ?? '',
    );
}
</script>

<template>
    <div class="flex h-full flex-col">
        <label
            class="flex h-full cursor-pointer items-center gap-3 rounded-xl border-2 px-3 py-2.5 transition duration-200 focus-within:ring-2 focus-within:ring-primary/40 motion-reduce:transition-none"
            :class="checked
                ? 'border-primary bg-primary/5 shadow-sm shadow-primary/10 dark:bg-primary/10'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/70 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:hover:bg-slate-800/40'"
        >
            <input
                type="checkbox"
                name="subjects[]"
                :value="subject.id"
                :checked="checked"
                class="sr-only"
                @change="emit('toggle', subject.id)"
            >

            <span class="text-xl leading-none" aria-hidden="true">{{ subject.icon }}</span>

            <span class="min-w-0 flex-1">
                <span class="block text-sm font-semibold leading-snug text-slate-800 dark:text-slate-100">
                    {{ subject.name }}
                </span>

                <span
                    v-if="showLevel"
                    class="mt-1 inline-flex rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-primary"
                >
                    {{ subject.levelName }}
                </span>
            </span>

            <span
                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
                :class="checked
                    ? 'border-primary bg-primary text-white'
                    : 'border-slate-300 text-transparent dark:border-slate-600'"
                aria-hidden="true"
            >
                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </span>
        </label>

        <div v-if="suggestions.length" class="mt-1 space-y-1 ps-1">
            <button
                v-for="twin in suggestions"
                :key="twin.id"
                type="button"
                class="inline-flex items-center gap-1 rounded-lg px-1.5 py-0.5 text-start text-[11px] font-medium text-primary transition-colors hover:bg-primary/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:bg-primary/10"
                @click="emit('add', twin.id)"
            >
                <span>
                    {{ label(copy.subjectTwinHint, { level: twin.levelName, name: subject.name }) }}
                </span>
                <span class="font-bold underline underline-offset-2">{{ copy.subjectTwinCta }}</span>
            </button>
        </div>
    </div>
</template>
