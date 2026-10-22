<script setup>
/**
 * The type rail: All services plus one tab per service type. Real `role="tab"`
 * buttons with roving focus, so arrow keys move between them and every panel
 * stays announced with its own label. The rail scrolls horizontally on small
 * screens instead of wrapping into three rows.
 */
import { nextTick, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    modelValue: { type: String, required: true },
    tabs: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const copy = useCopy();
const tabButtons = ref([]);

function selectTab(key, index) {
    emit('update:modelValue', key);
    nextTick(() => tabButtons.value[index]?.focus());
}

function onKeydown(event, index) {
    const last = props.tabs.length - 1;
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
    selectTab(props.tabs[next].key, next);
}
</script>

<template>
    <div
        role="tablist"
        :aria-label="copy.tabs?.label ?? 'Service types'"
        class="flex gap-1 overflow-x-auto rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900"
    >
        <button
            v-for="(tab, index) in tabs"
            :id="`teacher-services-tab-${tab.key}`"
            :key="tab.key"
            :ref="(element) => (tabButtons[index] = element)"
            type="button"
            role="tab"
            :aria-selected="modelValue === tab.key ? 'true' : 'false'"
            :aria-controls="`teacher-services-panel-${tab.key}`"
            :tabindex="modelValue === tab.key ? 0 : -1"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 sm:flex-1 sm:px-4"
            :class="modelValue === tab.key
                ? 'bg-primary text-white shadow-sm shadow-primary/30'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
            @click="selectTab(tab.key, index)"
            @keydown="onKeydown($event, index)"
        >
            <span class="h-4 w-4 shrink-0" aria-hidden="true"><AppIcon :name="tab.icon" /></span>
            <span class="whitespace-nowrap">{{ tab.label }}</span>

            <span
                class="rounded-full px-2 py-0.5 text-[11px] font-bold"
                :class="modelValue === tab.key
                    ? 'bg-white/20 text-white'
                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >
                {{ tab.count }}
            </span>
        </button>
    </div>
</template>
