<script setup>
import { nextTick, ref } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';

const props = defineProps({
    tabs: { type: Array, required: true },
    active: { type: String, required: true },
    label: { type: String, default: null },
});

const emit = defineEmits(['select']);

const list = ref(null);

// The tab rail is the page's own navigation: a click swaps the panel instead
// of leaving the subject, and the arrow keys move between the tabs.
async function move(step) {
    const index = props.tabs.findIndex((tab) => tab.key === props.active);
    const next = props.tabs[(index + step + props.tabs.length) % props.tabs.length];

    emit('select', next.key);
    await nextTick();
    list.value?.querySelector(`[data-tab="${next.key}"]`)?.focus();
}

function onKeydown(event) {
    if (event.key === 'ArrowRight') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowLeft') {
        event.preventDefault();
        move(-1);
    }
}
</script>

<template>
    <div class="sticky top-16 z-30 border-b border-line bg-white/90 backdrop-blur">
        <div class="mx-auto w-full max-w-6xl px-5">
            <nav
                ref="list"
                class="lp-tab-rail -mx-1 flex items-center gap-1 overflow-x-auto py-2"
                role="tablist"
                :aria-label="label"
                @keydown="onKeydown"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :data-tab="tab.key"
                    :id="`subject-tab-${tab.key}`"
                    :aria-selected="tab.key === active"
                    :aria-controls="`subject-panel-${tab.key}`"
                    :tabindex="tab.key === active ? 0 : -1"
                    class="group flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40 focus-visible:ring-offset-2"
                    :class="tab.key === active
                        ? 'bg-ink text-white'
                        : 'text-ink-700 hover:bg-ink-50'"
                    @click="emit('select', tab.key)"
                >
                    <AppIcon v-if="tab.icon" :name="tab.icon" class="h-4 w-4" />
                    {{ tab.label }}
                    <span
                        class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="tab.key === active ? 'bg-white/20 text-white' : 'bg-ink-50 text-ink-500'"
                    >{{ tab.count }}</span>
                </button>
            </nav>
        </div>
    </div>
</template>
