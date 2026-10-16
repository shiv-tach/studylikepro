<script setup>
/**
 * One booking thread in the inbox: who the conversation is with, which lesson it
 * belongs to, when it last moved and a one-line preview of the latest message.
 * Everything shown here arrives formatted from Blade, so the card never guesses
 * a name, a timestamp or a URL — the whole row is a real link to the thread.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    thread: { type: Object, required: true },
});

const panel = usePanel(true);

// An unread thread reads a touch heavier, so the eye lands on it first.
const previewClass = computed(() => (props.thread.unread > 0
    ? 'font-semibold text-slate-700 dark:text-slate-200'
    : 'text-slate-500 dark:text-slate-400'));

// The system notice and the "say hello" nudge are italic, matching the thread.
const italic = computed(() => props.thread.previewSystem || props.thread.previewEmpty);
</script>

<template>
    <a
        :href="thread.url"
        :class="[panel, 'group flex items-start gap-4 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40']"
    >
        <span class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold uppercase text-primary">
            {{ thread.initials }}

            <span
                v-if="thread.unread > 0"
                class="absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-slate-900"
            >
                {{ thread.unread }}
                <span v-if="thread.unreadLabel" class="sr-only">{{ thread.unreadLabel }}</span>
            </span>
        </span>

        <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-3">
                <p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ thread.counterpart }}</p>
                <span class="shrink-0 text-xs text-slate-400">{{ thread.at }}</span>
            </div>

            <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                {{ thread.title }}
                <template v-if="thread.schedule"> · {{ thread.schedule }}</template>
            </p>

            <p class="mt-2 truncate text-sm" :class="[previewClass, { italic }]">{{ thread.preview }}</p>
        </div>

        <span class="mt-1 hidden h-4 w-4 shrink-0 text-slate-300 transition-transform group-hover:translate-x-0.5 group-hover:text-primary sm:block dark:text-slate-600">
            <AppIcon name="chevron-right" />
        </span>
    </a>
</template>
