<script setup>
/**
 * A single password field with a show/hide toggle. The toggle is a button, not a
 * link, so it never leaves the form and keyboard users can reach it; the input
 * keeps its own `name` and `id` so the server sees exactly what it validated.
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    id: { type: String, required: true },
    name: { type: String, required: true },
    modelValue: { type: String, default: '' },
    autocomplete: { type: String, default: 'off' },
    placeholder: { type: String, default: '' },
});

defineEmits(['update:modelValue']);

const revealed = ref(false);
const type = computed(() => (revealed.value ? 'text' : 'password'));
</script>

<template>
    <div class="relative mt-1">
        <input
            :id="id"
            :name="name"
            :type="type"
            :value="modelValue"
            :autocomplete="autocomplete"
            :placeholder="placeholder || null"
            class="block w-full rounded-xl border-slate-300 bg-white pr-11 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            @input="$emit('update:modelValue', $event.target.value)"
        >

        <button
            type="button"
            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-xl text-slate-400 transition-colors hover:text-slate-600 dark:hover:text-slate-200"
            :aria-label="revealed ? 'Hide password' : 'Show password'"
            :aria-pressed="revealed"
            @click="revealed = !revealed"
        >
            <span class="h-4 w-4"><AppIcon :name="revealed ? 'eye-off' : 'eye'" /></span>
        </button>
    </div>
</template>
