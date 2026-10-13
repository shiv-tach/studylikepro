<script setup>
import { ref } from 'vue';
import { useClickOutside } from '../composables/useClickOutside';

const props = defineProps({
    // Which edge the panel pins to.
    align: { type: String, default: 'right', validator: (v) => ['left', 'right'].includes(v) },
    // Positioning/sizing utilities for the floating panel.
    panelClass: { type: String, default: 'w-48' },
});

const open = ref(false);
const root = ref(null);

useClickOutside(root, () => {
    open.value = false;
});

const toggle = () => {
    open.value = !open.value;
};

const close = () => {
    open.value = false;
};
</script>

<template>
    <div ref="root" class="relative">
        <!-- The trigger slot must render a real <button>; clicks bubble here so
             keyboard activation (Enter/Space) works for free. -->
        <div @click="toggle">
            <slot name="trigger" :open="open" :toggle="toggle" />
        </div>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="transform opacity-0 scale-95"
            enter-to-class="transform opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="transform opacity-100 scale-100"
            leave-to-class="transform opacity-0 scale-95"
        >
            <div
                v-show="open"
                :class="[
                    'absolute z-30 mt-2 origin-top rounded-xl border border-slate-200 bg-white shadow-lg ring-1 ring-black/5 dark:border-slate-800 dark:bg-slate-900 dark:ring-white/5',
                    align === 'left' ? 'left-0 origin-top-left' : 'right-0 origin-top-right',
                    panelClass,
                ]"
            >
                <slot :close="close" />
            </div>
        </Transition>
    </div>
</template>
