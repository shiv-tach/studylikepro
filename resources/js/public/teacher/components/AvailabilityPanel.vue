<script setup>
import { computed, ref, watch } from 'vue';
import { statusTone } from '../statusTone';

/**
 * Open slots grouped by day. The status banner above the strip is the live
 * answer ("available now", "back at 18:00", next open slot); the strip itself
 * is the detail a parent drills into. Selecting a day only changes what is
 * shown — nothing here needs the server.
 */
const props = defineProps({
    availability: { type: Object, required: true },
    status: { type: Object, required: true },
    copy: { type: Object, required: true },
    booking: { type: Object, required: true },
});

const tone = computed(() => statusTone(props.status.state));
const days = computed(() => props.availability.days ?? []);
const selectedDate = ref(days.value[0]?.date ?? null);

watch(days, (next) => {
    if (!next.some((day) => day.date === selectedDate.value)) {
        selectedDate.value = next[0]?.date ?? null;
    }
});

const selectedDay = computed(() => days.value.find((day) => day.date === selectedDate.value) ?? null);

function isSelected(day) {
    return day.date === selectedDate.value;
}

function slotTitle(slot) {
    return `${slot.time} – ${slot.endTime} · ${props.availability.lessonMinutes} min`;
}
</script>

<template>
    <section id="availability" class="hairline rounded-2xl bg-white p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-xl font-semibold">{{ copy.availabilityTitle }}</h2>
                <p class="mt-1 max-w-xl text-sm text-ink-500">{{ copy.availabilityIntro }}</p>
            </div>
            <span class="rounded-full bg-ink-50 px-3 py-1 text-xs font-semibold text-ink-500">
                {{ availability.timezoneLabel }}
            </span>
        </div>

        <p
            v-if="availability.empty"
            class="mt-4 rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-ink-500"
        >
            {{ copy.noSlots }}
        </p>

        <template v-else>
            <div class="mt-5 flex flex-wrap items-center gap-3 rounded-2xl border px-4 py-3" :class="tone.panel" role="status" aria-live="polite">
                <span class="relative flex size-2.5 shrink-0">
                    <span
                        v-if="status.state === 'now'"
                        class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-70 motion-reduce:hidden"
                        :class="tone.dot"
                    ></span>
                    <span class="relative inline-flex size-2.5 rounded-full" :class="tone.dot"></span>
                </span>

                <div class="min-w-0">
                    <p class="text-sm font-semibold" :class="tone.text">{{ status.label }}</p>
                    <p v-if="status.detail" class="text-xs" :class="tone.muted">{{ status.detail }}</p>
                </div>

                <span class="ml-auto text-[10px] font-semibold tracking-widest uppercase" :class="tone.muted">
                    {{ copy.live }}
                </span>
            </div>

            <div class="mt-4 -mx-1 flex gap-2 overflow-x-auto px-1 pb-1 lp-tab-rail" role="tablist" :aria-label="copy.availabilityTitle">
                <button
                    v-for="day in days"
                    :key="day.date"
                    type="button"
                    role="tab"
                    :aria-selected="isSelected(day)"
                    :class="[
                        'w-[4.75rem] shrink-0 rounded-xl border px-2 py-2 text-center transition',
                        isSelected(day)
                            ? 'border-brand-500 bg-brand-50'
                            : 'border-line bg-white hover:border-brand-300',
                    ]"
                    @click="selectedDate = day.date"
                >
                    <span
                        class="block text-[10px] font-semibold tracking-widest uppercase"
                        :class="isSelected(day) ? 'text-brand-600' : 'text-ink-400'"
                    >{{ day.weekday }}</span>
                    <span
                        class="mt-0.5 block text-lg font-semibold leading-none"
                        :class="isSelected(day) ? 'text-brand-700' : 'text-ink'"
                    >{{ day.dayNumber }}</span>
                    <span
                        class="mt-1 block text-[10px] font-medium"
                        :class="day.isToday ? 'text-go-ink' : 'text-ink-500'"
                    >{{ day.isToday ? copy.today : day.slotCountLabel }}</span>
                </button>
            </div>

            <div v-if="selectedDay" class="mt-4">
                <div class="flex flex-wrap items-baseline justify-between gap-2">
                    <p class="text-sm font-semibold text-ink">{{ selectedDay.fullLabel }}</p>
                    <p class="text-xs text-ink-500">{{ selectedDay.slotCountLabel }}</p>
                </div>

                <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-4">
                    <template v-for="slot in selectedDay.slots" :key="slot.iso">
                        <a
                            v-if="booking.canBook"
                            :href="booking.action.url"
                            :title="slotTitle(slot)"
                            class="rounded-xl border border-line bg-white px-2 py-2 text-center text-sm font-semibold text-ink transition hover:border-brand-500 hover:text-brand-600"
                        >{{ slot.time }}</a>
                        <span
                            v-else
                            :title="slotTitle(slot)"
                            class="rounded-xl border border-line bg-white px-2 py-2 text-center text-sm font-semibold text-ink"
                        >{{ slot.time }}</span>
                    </template>
                </div>
            </div>

            <p class="mt-4 text-xs text-ink-400">{{ copy.slotNote }} {{ availability.timezoneNote }}</p>
        </template>
    </section>
</template>
