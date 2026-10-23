<script setup>
/**
 * When this teacher is free, in the viewer's own timezone. Blade has already
 * converted every slot, dropped the ones already booked, cut the list to the
 * nearest open days and labelled every day, so this panel only groups and
 * selects — picking a day never needs the server.
 */
import { computed, ref, watch } from 'vue';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    availability: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();

const days = computed(() => props.availability.days ?? []);
const selectedDate = ref(days.value[0]?.date ?? null);

watch(days, (next) => {
    if (!next.some((day) => day.date === selectedDate.value)) {
        selectedDate.value = next[0]?.date ?? null;
    }
});

const selectedDay = computed(() => days.value.find((day) => day.date === selectedDate.value) ?? null);

const nextFree = computed(() => {
    const day = days.value[0];
    const slot = day?.slots?.[0];

    return day && slot ? { day, slot } : null;
});

function dayName(day) {
    if (!day) {
        return '';
    }

    return day.isToday ? copy.today : day.isTomorrow ? copy.tomorrow : day.weekday;
}

// The banner always spells the date out: the next open day may be next week.
const nextFreeLabel = computed(() =>
    nextFree.value
        ? `${nextFree.value.day.isToday ? copy.today : nextFree.value.day.label} ${nextFree.value.slot.label}`
        : null,
);

const dayTabClass = (day) => [
    'min-w-[4.75rem] shrink-0 whitespace-nowrap rounded-xl border px-2 py-2 text-center transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
    day.date === selectedDate.value
        ? 'border-primary bg-primary/10'
        : 'border-slate-200 bg-white hover:border-primary/40 dark:border-slate-800 dark:bg-slate-900',
];
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.availabilityTitle" icon="calendar">
            <template #action>
                <UiChip>{{ availability.timezoneLabel }}</UiChip>
            </template>
        </UiSectionHeading>

        <p
            v-if="availability.empty"
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ copy.noSlots }}
        </p>

        <template v-else>
            <p v-if="nextFree" class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-primary/20 bg-primary/5 px-4 py-3" role="status" aria-live="polite">
                <span class="size-2.5 shrink-0 rounded-full bg-primary"></span>

                <span class="min-w-0">
                    <span class="block text-sm font-semibold text-primary">
                        {{ copy.nextFree }} · {{ nextFreeLabel }}
                    </span>
                    <span class="block text-xs" :class="MUTED_TEXT">{{ nextFree.day.slotCountLabel }}</span>
                </span>
            </p>

            <p v-if="availability.windowNotice" class="mt-3 text-xs" :class="MUTED_TEXT">{{ availability.windowNotice }}</p>

            <div
                class="mt-3 -mx-1 flex gap-2 overflow-x-auto px-1 pb-1"
                role="group"
                :aria-label="copy.dayPickerLabel"
            >
                <button
                    v-for="day in days"
                    :key="day.date"
                    type="button"
                    :aria-pressed="day.date === selectedDate"
                    :class="dayTabClass(day)"
                    @click="selectedDate = day.date"
                >
                    <span
                        class="block text-[10px] font-semibold uppercase tracking-widest"
                        :class="day.date === selectedDate ? 'text-primary' : 'text-slate-400'"
                    >{{ dayName(day) }}</span>
                    <span
                        class="mt-0.5 block text-lg font-semibold leading-none"
                        :class="HEADING_TEXT"
                    >{{ day.dayNumber }}</span>
                    <span class="mt-1 block text-[10px] font-medium" :class="MUTED_TEXT">{{ day.month }}</span>
                </button>
            </div>

            <div v-if="selectedDay" class="mt-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold" :class="HEADING_TEXT">{{ selectedDay.fullLabel }}</p>
                    <p class="text-xs" :class="MUTED_TEXT">{{ selectedDay.slotCountLabel }}</p>
                </div>

                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-5">
                    <span
                        v-for="slot in selectedDay.slots"
                        :key="slot.value"
                        class="rounded-xl border border-slate-200/80 bg-white px-2 py-2 text-center text-sm font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                    >
                        {{ slot.label }}
                    </span>
                </div>
            </div>

            <p v-if="availability.timezoneNotice" class="mt-3 text-xs" :class="MUTED_TEXT">{{ availability.timezoneNotice }}</p>
        </template>
    </section>
</template>
