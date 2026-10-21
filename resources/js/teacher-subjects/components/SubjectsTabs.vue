<script setup>
/**
 * The three ways through the workspace. Real `role="tab"` buttons with roving
 * focus, so arrow keys move between them and every panel stays announced with
 * its own label.
 */
import { computed, nextTick, ref } from 'vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    modelValue: { type: String, required: true },
    counts: { type: Object, default: () => ({}) },
    dirty: { type: Number, default: 0 },
});

const emit = defineEmits(['update:modelValue']);

const copy = useCopy();
const tabButtons = ref([]);

const tabs = computed(() => [
    {
        key: 'my',
        label: copy.tabs?.subjects ?? 'My subjects',
        count: props.counts.subjects ?? 0,
        dot: props.dirty > 0,
    },
    {
        key: 'requests',
        label: copy.tabs?.requests ?? 'Requests',
        count: props.counts.requests ?? 0,
        dot: false,
    },
    {
        key: 'add',
        label: copy.tabs?.add ?? 'Add subjects',
        count: null,
        dot: false,
    },
]);

function selectTab(key, index) {
    emit('update:modelValue', key);
    nextTick(() => tabButtons.value[index]?.focus());
}

function onKeydown(event, index) {
    const last = tabs.value.length - 1;
    let next = null;

    if (event.key === 'ArrowRight') {
        next = index === last ? 0 : index + 1;
    } else if (event.key === 'ArrowLeft') {
        next = index === 0 ? last : index - 1;
    } else if (event.key === 'Home') {
        next = 0;
    } else if (event.key === 'End') {
        next = last;
    }

    if (next === null) {
        return;
    }

    event.preventDefault();
    selectTab(tabs.value[next].key, next);
}
</script>

<template>
    <div
        role="tablist"
        :aria-label="copy.tabs?.label ?? 'Subject sections'"
        class="flex flex-wrap gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900"
    >
        <button
            v-for="(tab, index) in tabs"
            :id="`teacher-subjects-tab-${tab.key}`"
            :key="tab.key"
            :ref="(element) => (tabButtons[index] = element)"
            type="button"
            role="tab"
            :aria-selected="modelValue === tab.key ? 'true' : 'false'"
            :aria-controls="`teacher-subjects-panel-${tab.key}`"
            :tabindex="modelValue === tab.key ? 0 : -1"
            class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :class="modelValue === tab.key
                ? 'bg-primary text-white shadow-sm shadow-primary/30'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
            @click="emit('update:modelValue', tab.key)"
            @keydown="onKeydown($event, index)"
        >
            <span>{{ tab.label }}</span>

            <span
                v-if="tab.count !== null"
                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                :class="modelValue === tab.key
                    ? 'bg-white/20 text-white'
                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >
                {{ tab.count }}
            </span>

            <span
                v-if="tab.dot"
                class="h-1.5 w-1.5 shrink-0 rounded-full bg-amber-400"
                :title="copy.save?.unsaved"
                :aria-label="copy.save?.unsaved"
                role="status"
            ></span>
        </button>
    </div>
</template>
