<script setup>
/**
 * One field of a workspace form: its label, an optional hint, the control
 * itself (the default slot) and the server-rendered validation messages.
 * The control keeps its own `name` and `id` — this component only decides where
 * the label, the hint and Laravel's messages sit, so a rejected save reads the
 * same on every form.
 */
defineProps({
    label: { type: String, required: true },
    forId: { type: String, default: null },
    hint: { type: String, default: null },
    errors: { type: Array, default: () => [] },
    required: { type: Boolean, default: false },
});
</script>

<template>
    <div>
        <label
            v-if="forId"
            :for="forId"
            class="block text-sm font-medium text-slate-700 dark:text-slate-300"
        >
            {{ label }}
            <span v-if="required" class="text-rose-500" aria-hidden="true">*</span>
        </label>
        <span v-else class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ label }}</span>

        <slot />

        <p v-if="hint" class="mt-1 text-xs text-slate-400">{{ hint }}</p>

        <ul v-if="errors.length" class="mt-2 space-y-1" role="alert">
            <li
                v-for="(message, index) in errors"
                :key="index"
                class="text-sm font-medium text-rose-600 dark:text-rose-400"
            >
                {{ message }}
            </li>
        </ul>
    </div>
</template>
