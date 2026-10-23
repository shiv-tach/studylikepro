<script setup>
/**
 * Step two of the stepper: the teacher's nearest open days as a day rail, with
 * the times for the chosen day underneath. Blade has already scoped the slots to
 * the chosen subject, lesson length and the learner's grade, converted every
 * time to the viewer's timezone, and cut the list to the teacher's nearest open
 * days — so this card only groups and selects. Nothing here needs the server.
 */
import { computed, ref, watch } from 'vue';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    days: { type: Array, default: () => [] },
    modelValue: { type: String, default: '' },
    notice: { type: String, default: null },
    windowNotice: { type: String, default: null },
    timezoneNotice: { type: String, default: null },
    timezone: { type: String, default: '' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const copy = useCopy();

function dayOfSlot(value) {
    return props.days.find((day) => day.slots.some((slot) => slot.value === value)) ?? null;
}

// A rejected submit comes back with the slot it lost, so open that day.
const selectedDate = ref(dayOfSlot(props.modelValue)?.date ?? props.days[0]?.date ?? null);

const selectedDay = computed(() => props.days.find((day) => day.date === selectedDate.value) ?? null);

// Day and time are one choice: moving to another day drops a time that is not
// on it, so the form can never post a slot the student cannot see.
function selectDay(day) {
    selectedDate.value = day.date;

    if (props.modelValue && !day.slots.some((slot) => slot.value === props.modelValue)) {
        emit('update:modelValue', '');
    }
}

watch(
    () => props.modelValue,
    (value) => {
        const day = dayOfSlot(value);

        if (day) {
            selectedDate.value = day.date;
        }
    },
);

// Re-scoping the lesson length reloads the page, but keep the rail honest if it
// ever re-renders in place.
watch(
    () => props.days,
    (days) => {
        if (!days.some((day) => day.date === selectedDate.value)) {
            selectedDate.value = dayOfSlot(props.modelValue)?.date ?? days[0]?.date ?? null;
        }
    },
);

const nextFree = computed(() => {
    const day = props.days[0];
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
    <section>
        <UiSectionHeading :title="copy.stepTime" icon="calendar" level="h3">
            <template #action>
                <UiChip v-if="timezone">{{ timezone }}</UiChip>
            </template>
        </UiSectionHeading>

        <p v-if="error" class="mt-2 text-sm text-rose-600 dark:text-rose-400" role="alert">{{ error }}</p>

        <p
            v-if="days.length === 0"
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ notice }}
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

            <p v-if="windowNotice" class="mt-3 text-xs" :class="MUTED_TEXT">{{ windowNotice }}</p>

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
                    @click="selectDay(day)"
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
                    <label v-for="option in selectedDay.slots" :key="option.value" class="cursor-pointer">
                        <input
                            type="radio"
                            name="starts_at"
                            :value="option.value"
                            :checked="modelValue === option.value"
                            class="peer sr-only"
                            @change="emit('update:modelValue', option.value)"
                        >
                        <span
                            class="block rounded-xl border border-slate-200 bg-white px-2 py-2 text-center text-sm font-semibold text-slate-600 transition-colors peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                        >
                            {{ option.label }}
                        </span>
                    </label>
                </div>
            </div>

            <p v-if="timezoneNotice" class="mt-3 text-xs" :class="MUTED_TEXT">{{ timezoneNotice }}</p>
        </template>
    </section>
</template>
