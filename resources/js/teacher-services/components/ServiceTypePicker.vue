<script setup>
/**
 * Step one of a new service: pick what is being sold. Four tiles, each with the
 * type's icon and a one-line explanation, so the teacher never has to guess
 * which combination of fields belongs to which service.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    modelValue: { type: String, default: null },
    types: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:modelValue']);

const copy = useCopy();
</script>

<template>
    <fieldset>
        <legend class="text-sm font-bold text-slate-800 dark:text-slate-100">
            {{ copy.composer?.typeLabel }}
        </legend>

        <div class="mt-3 grid gap-2 sm:grid-cols-2" role="radiogroup" :aria-label="copy.composer?.typeLabel">
            <button
                v-for="type in types"
                :key="type.value"
                type="button"
                role="radio"
                :aria-checked="modelValue === type.value ? 'true' : 'false'"
                class="flex items-start gap-3 rounded-xl border px-3 py-3 text-left transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                :class="modelValue === type.value
                    ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                    : 'border-slate-200 bg-white hover:border-primary/40 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:hover:bg-slate-800/60'"
                @click="emit('update:modelValue', type.value)"
            >
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl p-2"
                    :class="modelValue === type.value ? 'bg-primary text-white' : 'bg-primary/10 text-primary'"
                    aria-hidden="true"
                >
                    <AppIcon :name="type.icon" />
                </span>

                <span class="min-w-0">
                    <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ type.label }}</span>
                    <span class="mt-0.5 block text-[11px] leading-relaxed text-slate-500 dark:text-slate-400">{{ type.hint }}</span>
                </span>

                <span
                    v-if="modelValue === type.value"
                    class="ml-auto h-4 w-4 shrink-0 text-primary"
                    aria-hidden="true"
                >
                    <AppIcon name="check-circle" />
                </span>
            </button>
        </div>
    </fieldset>
</template>
