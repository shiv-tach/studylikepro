<script setup>
/**
 * One group of platform settings (Money, Bookings, Cancellations & refunds,
 * Services) on a card surface that follows the theme preset.
 */
import { usePanel } from '../../shared/composables/usePanel';
import SettingField from './SettingField.vue';

defineProps({
    group: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
    currencySymbol: { type: String, default: '' },
});

const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ group.label }}</h3>

        <div class="mt-4 space-y-5">
            <SettingField
                v-for="item in group.items"
                :key="item.key"
                :setting="item"
                :errors="errors[item.key] ?? []"
                :currency-symbol="currencySymbol"
            />
        </div>
    </section>
</template>
