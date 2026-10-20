<script setup>
import AppIcon from '../../../landing/components/AppIcon.vue';

/**
 * The conversion card. Every label, rate and URL is Blade's; the card only
 * lays them out so the price, the lesson length and the next step stay in view
 * while the visitor reads.
 */
defineProps({
    booking: { type: Object, required: true },
});
</script>

<template>
    <aside class="hairline rounded-2xl bg-white p-6" style="box-shadow: var(--shadow-sm)">
        <p class="text-[10px] font-semibold tracking-widest text-ink-400 uppercase">{{ booking.startingAt }}</p>
        <p class="mt-1 font-display text-3xl font-semibold">
            {{ booking.rate }}<span class="text-sm font-medium text-ink-400">{{ booking.perHour }}</span>
        </p>
        <p class="mt-1 text-xs text-ink-500">{{ booking.lessonLength }}</p>

        <div class="mt-4 flex items-center gap-2 rounded-xl bg-ink-50 px-3 py-2 text-xs text-ink-600">
            <AppIcon name="calendar" class="h-4 w-4 shrink-0 text-brand-500" />
            <span>{{ booking.slotsLabel }}</span>
        </div>

        <a
            :href="booking.action.url"
            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-500 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-brand-600"
            style="box-shadow: var(--shadow-brand)"
        >
            {{ booking.action.label }}
            <AppIcon name="arrow-right" class="h-4 w-4" />
        </a>

        <a
            v-if="booking.secondary"
            :href="booking.secondary.url"
            class="mt-2 block w-full rounded-full border border-line px-4 py-2.5 text-center text-sm font-medium text-ink-700 transition hover:bg-ink-50"
        >{{ booking.secondary.label }}</a>

        <ul class="mt-5 space-y-2 border-t border-line pt-4 text-xs text-ink-500">
            <li v-for="item in booking.trust" :key="item.label" class="flex items-start gap-2">
                <AppIcon :name="item.icon" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-brand-500" />
                <span>{{ item.label }}</span>
            </li>
        </ul>
    </aside>
</template>
