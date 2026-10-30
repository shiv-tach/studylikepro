<script setup>
/**
 * The recap list: what has been answered so far, with a link back to the
 * question that gave the answer. Shared by the phone disclosure and the
 * desktop aside.
 */
defineProps({
    groups: { type: Array, default: () => [] },
    copy: { type: Object, default: () => ({}) },
});
</script>

<template>
    <ol class="space-y-3.5">
        <li v-for="group in groups" :key="group.key">
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span aria-hidden="true">{{ group.icon }}</span>
                {{ group.label }}
            </p>

            <ul class="mt-1 space-y-1.5">
                <li v-for="item in group.items" :key="item.key" class="flex items-start gap-2">
                    <span
                        class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full"
                        :class="item.done
                            ? 'bg-emerald-500 text-white'
                            : 'border border-dashed border-slate-300 text-slate-300 dark:border-slate-700 dark:text-slate-600'"
                        aria-hidden="true"
                    >
                        <svg v-if="item.done" class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ item.label }}</span>

                        <a
                            v-if="item.url"
                            :href="item.url"
                            class="block break-words text-xs font-semibold text-slate-700 underline-offset-2 transition-colors hover:text-primary hover:underline dark:text-slate-200"
                        >
                            {{ item.value ?? copy.notAnswered }}
                        </a>
                        <span
                            v-else
                            class="block break-words text-xs font-semibold"
                            :class="item.value !== null ? 'text-slate-700 dark:text-slate-200' : 'text-slate-400 dark:text-slate-500'"
                        >
                            {{ item.value ?? copy.notAnswered }}
                        </span>

                        <span v-if="item.meta" class="mt-0.5 block break-words text-[11px] text-slate-400 dark:text-slate-500">{{ item.meta }}</span>
                    </span>
                </li>
            </ul>
        </li>
    </ol>
</template>
