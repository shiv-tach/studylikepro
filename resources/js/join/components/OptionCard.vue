<script setup>
/**
 * One selectable answer. The real input stays a `peer sr-only`, so the card is
 * the control, Tailwind's `peer-checked` draws the state and the form still
 * submits the exact name/value pair the controller validates — the Vue twin of
 * `resources/views/components/survey/option-card.blade.php`.
 *
 * `v-model` carries either one value (radio) or an array (checkbox group); the
 * native `change` event is re-emitted so a caller can react to a pick (the
 * availability preset fills the day grid).
 */
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'radio' },
    name: { type: String, required: true },
    value: { type: [String, Number], default: null },
    icon: { type: String, default: null },
    title: { type: String, default: null },
    hint: { type: String, default: null },
    meta: { type: String, default: null },
    badge: { type: String, default: null },
    align: { type: String, default: 'start' },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);
const model = defineModel({ default: undefined });

const checked = computed(() => (Array.isArray(model.value)
    ? model.value.some((item) => item === props.value)
    : model.value === props.value));

const cardClasses = computed(() => [
    'flex h-full cursor-pointer flex-col justify-center gap-2 rounded-2xl border-2 border-slate-200 bg-white p-4 transition duration-200',
    'hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-sm motion-reduce:transform-none motion-reduce:transition-none',
    'peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:shadow-md peer-checked:shadow-primary/10',
    'peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-focus-visible:ring-offset-2',
    'dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:peer-checked:border-primary/70 dark:peer-focus-visible:ring-offset-slate-950',
    props.align === 'center' ? 'items-center text-center' : '',
]);

const bodyClasses = computed(() => [
    'flex w-full gap-3',
    props.align === 'center' ? 'flex-col items-center text-center' : 'items-start',
]);

function onChange(event) {
    if (props.type === 'checkbox' && Array.isArray(model.value)) {
        const next = [...model.value];
        const index = next.findIndex((item) => item === props.value);

        if (event.target.checked && index === -1) {
            next.push(props.value);
        } else if (!event.target.checked && index !== -1) {
            next.splice(index, 1);
        }

        model.value = next;
    } else if (event.target.checked) {
        model.value = props.value;
    }

    emit('change', event);
}
</script>

<template>
    <label class="relative block h-full">
        <input
            :type="type"
            :name="name"
            :value="value ?? undefined"
            :checked="checked"
            :required="required"
            class="peer sr-only"
            @change="onChange"
        />

        <span :class="cardClasses">
            <span :class="bodyClasses">
                <span v-if="icon" class="text-2xl leading-none" aria-hidden="true">{{ icon }}</span>

                <span class="min-w-0 flex-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-base font-bold text-slate-900 dark:text-slate-100">{{ title }}</span>

                        <span v-if="badge" class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">
                            {{ badge }}
                        </span>
                    </span>

                    <span v-if="hint" class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ hint }}</span>

                    <span v-if="meta" class="mt-1.5 block text-xs font-semibold leading-relaxed text-slate-700 dark:text-slate-300">{{ meta }}</span>

                    <slot />
                </span>
            </span>
        </span>

        <span
            class="pointer-events-none absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white opacity-0 shadow-sm transition-opacity duration-200 peer-checked:opacity-100 motion-reduce:transition-none"
            aria-hidden="true"
        >
            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
            </svg>
        </span>
    </label>
</template>
