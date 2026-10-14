<script setup>
/**
 * The repeatable "when could you attend" editor. A window is a plain
 * `windows[index][field]` input inside the surrounding form, so the browser
 * still posts the rows itself — Vue only decides how many rows exist.
 */
import { computed, ref } from 'vue';
import FieldMessages from './FieldMessages.vue';
import FormPanel from './FormPanel.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    windows: { type: Array, default: () => [] },
    max: { type: Number, default: 5 },
    minDate: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
});

const copy = useCopy();

const fieldClass =
    'mt-1 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

const addButtonClass =
    'inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-700 dark:text-slate-300';

// Rows carry a stable key so removing one animates the right card instead of
// re-labelling the rest; the key never reaches the server.
let nextKey = 0;
const rows = ref(
    (props.windows.length ? props.windows : [{ date: '', from: '18:00', to: '19:00' }]).map((window) => ({
        key: nextKey++,
        date: window.date ?? '',
        from: window.from ?? '18:00',
        to: window.to ?? '19:00',
    })),
);

const atLimit = computed(() => rows.value.length >= props.max);
const messagesFor = (key) => props.errors[key] ?? [];

function addRow() {
    if (!atLimit.value) {
        rows.value.push({ key: nextKey++, date: '', from: '18:00', to: '19:00' });
    }
}

function removeRow(index) {
    if (rows.value.length > 1) {
        rows.value.splice(index, 1);
    }
}
</script>

<template>
    <FormPanel :title="copy.windowsTitle" :description="copy.windowsHint">
        <template #action>
            <button type="button" :class="addButtonClass" :disabled="atLimit" @click="addRow">
                + {{ copy.windowAdd }}
            </button>
        </template>

        <TransitionGroup tag="div" name="window-row" class="mt-4 space-y-3">
            <div
                v-for="(row, index) in rows"
                :key="row.key"
                class="flex flex-wrap items-end gap-3 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800"
            >
                <div class="grow">
                    <label :for="`window-date-${index}`" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {{ copy.windowDate }}
                    </label>
                    <input
                        :id="`window-date-${index}`"
                        v-model="row.date"
                        :name="`windows[${index}][date]`"
                        type="date"
                        :min="minDate"
                        required
                        :class="[fieldClass, 'w-full']"
                    />
                    <FieldMessages :messages="messagesFor(`windows.${index}.date`)" />
                </div>

                <div>
                    <label :for="`window-from-${index}`" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {{ copy.windowFrom }}
                    </label>
                    <input
                        :id="`window-from-${index}`"
                        v-model="row.from"
                        :name="`windows[${index}][from]`"
                        type="time"
                        required
                        :class="fieldClass"
                    />
                    <FieldMessages :messages="messagesFor(`windows.${index}.from`)" />
                </div>

                <div>
                    <label :for="`window-to-${index}`" class="block text-xs font-semibold uppercase tracking-wider text-slate-400">
                        {{ copy.windowTo }}
                    </label>
                    <input
                        :id="`window-to-${index}`"
                        v-model="row.to"
                        :name="`windows[${index}][to]`"
                        type="time"
                        required
                        :class="fieldClass"
                    />
                    <FieldMessages :messages="messagesFor(`windows.${index}.to`)" />
                </div>

                <button
                    type="button"
                    :disabled="rows.length <= 1"
                    :aria-label="`${copy.windowRemove} ${index + 1}`"
                    class="mb-0.5 flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-rose-950/30"
                    @click="removeRow(index)"
                >
                    &times;
                </button>
            </div>
        </TransitionGroup>

        <p v-if="atLimit" class="mt-3 text-xs text-slate-400">{{ copy.windowLimit }}</p>

        <FieldMessages :messages="messagesFor('windows')" />
    </FormPanel>
</template>

<style scoped>
.window-row-move,
.window-row-enter-active,
.window-row-leave-active {
    transition: opacity 250ms ease, transform 250ms ease;
}

.window-row-enter-from,
.window-row-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}

@media (prefers-reduced-motion: reduce) {
    .window-row-move,
    .window-row-enter-active,
    .window-row-leave-active {
        transition: none;
    }
}
</style>
