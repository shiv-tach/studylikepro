<script setup>
import { nextTick, onBeforeUnmount, ref } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';

const props = defineProps({
    grades: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    copy: { type: Object, default: () => ({}) },
    // The catalog opens grades in place; on the standalone subject page the
    // links navigate normally.
    clientNavigation: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);

const open = ref(false);
const trigger = ref(null);
const menu = ref(null);
const position = ref({ top: '0px', left: '0px' });

function place() {
    const rect = trigger.value?.getBoundingClientRect();

    if (!rect) {
        return;
    }

    // Keep the menu inside the viewport when the chip sits near the right edge.
    const width = 176;
    const left = Math.max(8, Math.min(rect.left, window.innerWidth - width - 8));

    position.value = { top: `${rect.bottom + 8}px`, left: `${left}px` };
}

async function toggle() {
    open.value = !open.value;

    if (!open.value) {
        return;
    }

    place();
    window.addEventListener('scroll', closeOnScroll, true);

    await nextTick();
    (menu.value?.querySelector('[aria-selected="true"]') ?? menu.value?.querySelector('a'))?.focus();
}

function close({ focus = true } = {}) {
    if (!open.value) {
        return;
    }

    open.value = false;
    window.removeEventListener('scroll', closeOnScroll, true);

    if (focus) {
        trigger.value?.focus();
    }
}

function closeOnScroll() {
    close({ focus: false });
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        event.preventDefault();
        close();

        return;
    }

    if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') {
        return;
    }

    event.preventDefault();

    const items = [...(menu.value?.querySelectorAll('a') ?? [])];

    if (!items.length) {
        return;
    }

    const index = items.indexOf(document.activeElement);
    const step = event.key === 'ArrowDown' ? 1 : -1;

    items[(index + step + items.length) % items.length]?.focus();
}

function pick(event, grade) {
    close({ focus: false });

    if (grade.selected || !props.clientNavigation) {
        return;
    }

    event.preventDefault();
    emit('navigate', grade.url);
}

onBeforeUnmount(() => window.removeEventListener('scroll', closeOnScroll, true));
</script>

<template>
    <div class="relative inline-flex">
        <button
            ref="trigger"
            type="button"
            class="inline-flex items-center gap-1.5 rounded-full bg-ink-50 px-3 py-1 text-xs font-semibold text-ink-700 transition hover:bg-ink-100"
            :aria-expanded="open"
            aria-haspopup="listbox"
            @click="toggle"
            @keydown.down.prevent="toggle"
        >
            {{ selected?.label ?? copy.gradeSwitcherLabel }}
            <AppIcon name="chevron-down" class="h-3.5 w-3.5" />
        </button>

        <Teleport to="body">
            <div v-if="open" class="fixed inset-0 z-50" @click.self="close()">
                <div :style="position" class="absolute">
                    <!-- The menu is teleported out of the hero, so it carries the
                         theme scope with it rather than relying on an ancestor. -->
                    <div
                        ref="menu"
                        role="listbox"
                        :aria-label="copy.gradeSwitcherLabel"
                        class="lp-theme max-h-72 w-44 overflow-y-auto rounded-xl border border-line bg-white p-1"
                        style="box-shadow: var(--shadow-lg)"
                        @keydown="onKeydown"
                    >
                        <a
                            v-for="grade in grades"
                            :key="grade.id"
                            :href="grade.url"
                            role="option"
                            :aria-selected="grade.selected"
                            class="block rounded-lg px-3 py-2 text-sm font-medium transition"
                            :class="grade.selected
                                ? 'bg-brand-50 text-brand-600'
                                : 'text-ink-700 hover:bg-ink-50'"
                            @click="pick($event, grade)"
                        >{{ grade.label }}</a>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
