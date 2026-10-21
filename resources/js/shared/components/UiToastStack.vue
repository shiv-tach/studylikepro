<script setup>
/**
 * The floating notice stack a workspace page shows for a redirect's flash
 * message or for something the page just did. It is teleported to the body and
 * pinned below the sticky top bar, so a confirmation is seen wherever the
 * teacher is scrolled to — the reason a banner at the top of a long form gets
 * missed.
 *
 * The stack is an `aria-live` region: a screen reader announces each toast as it
 * arrives, and the dismiss button stays keyboard reachable.
 */
import AppIcon from './AppIcon.vue';

defineProps({
    toasts: { type: Array, default: () => [] },
    label: { type: String, default: 'Notifications' },
    dismissLabel: { type: String, default: 'Dismiss' },
});

const emit = defineEmits(['dismiss']);

const TONES = {
    emerald: 'border-emerald-200/80 bg-emerald-50 text-emerald-900 dark:border-emerald-900/50 dark:bg-emerald-950/80 dark:text-emerald-200',
    amber: 'border-amber-200/80 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/80 dark:text-amber-200',
    rose: 'border-rose-200/80 bg-rose-50 text-rose-900 dark:border-rose-900/50 dark:bg-rose-950/80 dark:text-rose-200',
    primary: 'border-primary/30 bg-primary/5 text-slate-800 dark:bg-slate-900/90 dark:text-slate-100',
    slate: 'border-slate-200/80 bg-white text-slate-700 dark:border-slate-800/80 dark:bg-slate-900/90 dark:text-slate-200',
};

const ICONS = {
    emerald: 'check-circle',
    amber: 'alert',
    rose: 'alert',
    primary: 'sparkles',
    slate: 'bell',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="pointer-events-none fixed inset-x-4 top-20 z-40 flex flex-col items-end gap-2 sm:left-auto sm:right-6 sm:w-[22rem]"
            aria-live="polite"
            :aria-label="label"
        >
            <TransitionGroup
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-x-2 opacity-0"
                enter-to-class="translate-x-0 opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="translate-x-0 opacity-100"
                leave-to-class="translate-x-2 opacity-0"
                move-class="transition duration-200 ease-out"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    :class="[TONES[toast.tone] ?? TONES.slate, 'pointer-events-auto w-full rounded-2xl border p-4 shadow-lg backdrop-blur-md']"
                    role="status"
                >
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 h-4 w-4 shrink-0 opacity-80">
                            <AppIcon :name="toast.icon ?? ICONS[toast.tone] ?? 'bell'" />
                        </span>

                        <div class="min-w-0 flex-1">
                            <p v-if="toast.title" class="text-sm font-bold">{{ toast.title }}</p>
                            <p class="text-sm leading-relaxed">{{ toast.message }}</p>
                        </div>

                        <button
                            type="button"
                            class="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 opacity-60 transition-opacity hover:opacity-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                            :aria-label="dismissLabel"
                            @click="emit('dismiss', toast.id)"
                        >
                            <span class="block h-3.5 w-3.5"><AppIcon name="close" /></span>
                        </button>
                    </div>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>
