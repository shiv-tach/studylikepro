<script setup>
/**
 * A segmented tablist over the three views Blade sends. Switching is instant
 * because all three collections already arrived in the island; the active view
 * is mirrored into the URL hash so a reload or a shared link lands on the same
 * tab. Arrow keys, Home and End move the selection like a native tablist.
 */
import { nextTick, ref } from 'vue';

const props = defineProps({
    tabs: { type: Array, default: () => [] },
    active: { type: String, required: true },
    label: { type: String, default: 'Schedule views' },
});

const emit = defineEmits(['update:active']);

const buttons = ref([]);

function select(key, index) {
    emit('update:active', key);
    nextTick(() => buttons.value[index]?.focus());
}

function onKeydown(event, index) {
    const keys = ['ArrowRight', 'ArrowLeft', 'Home', 'End'];

    if (!keys.includes(event.key) || props.tabs.length === 0) {
        return;
    }

    event.preventDefault();

    const last = props.tabs.length - 1;
    let next = index;

    if (event.key === 'ArrowRight') {
        next = index === last ? 0 : index + 1;
    } else if (event.key === 'ArrowLeft') {
        next = index === 0 ? last : index - 1;
    } else if (event.key === 'Home') {
        next = 0;
    } else if (event.key === 'End') {
        next = last;
    }

    select(props.tabs[next].key, next);
}
</script>

<template>
    <div
        role="tablist"
        :aria-label="label"
        class="flex w-full max-w-full flex-wrap gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-sm sm:inline-flex sm:w-auto dark:border-slate-800/80 dark:bg-slate-900"
    >
        <button
            v-for="(tab, index) in tabs"
            :id="`schedule-tab-${tab.key}`"
            :key="tab.key"
            :ref="(el) => (buttons[index] = el)"
            type="button"
            role="tab"
            :aria-selected="tab.key === active"
            :aria-controls="`schedule-panel-${tab.key}`"
            :tabindex="tab.key === active ? 0 : -1"
            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:flex-none"
            :class="tab.key === active
                ? 'bg-primary text-white shadow-sm shadow-primary/30'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
            @click="select(tab.key, index)"
            @keydown="onKeydown($event, index)"
        >
            <span class="whitespace-nowrap">{{ tab.label }}</span>

            <span
                class="rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                :class="tab.key === active
                    ? 'bg-white/20 text-white'
                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
