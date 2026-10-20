import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

/**
 * The public profile answers "is this teacher free right now?" without a
 * reload: Blade hands over the teacher's bookable windows in UTC plus the copy
 * templates, and this composable re-answers the question every few seconds.
 * The server renders the same answer for the first paint and for the
 * no-JavaScript fallback, so both paths agree.
 */
const TICK_MS = 20_000;

function fill(template, values) {
    return String(template ?? '').replace(/:([a-z]+)/gi, (match, key) =>
        values[key] === undefined || values[key] === null ? match : String(values[key]),
    );
}

function plural(template, count) {
    const [one, other] = String(template ?? '').split('|');

    return fill(count === 1 ? one : (other ?? one), { count });
}

export function availabilityStatus(availability, copy, nowMs = Date.now()) {
    const days = availability?.days ?? [];
    const windows = availability?.windows ?? [];

    if (days.length === 0) {
        return { state: 'none', label: copy.noSlots ?? '', detail: null };
    }

    const current = windows.find(
        (window) => Date.parse(window.start) <= nowMs && Date.parse(window.end) > nowMs,
    );

    if (current) {
        return {
            state: 'now',
            label: copy.availableNow,
            detail: fill(copy.freeUntil, { time: current.endTime }),
        };
    }

    const next = windows.find((window) => Date.parse(window.start) > nowMs);

    if (next) {
        const minutes = Math.max(1, Math.ceil((Date.parse(next.start) - nowMs) / 60_000));

        let label = fill(copy.nextAvailable, { day: next.dayLabel, time: next.startTime });

        if (next.dayLabel === copy.today && minutes <= 90) {
            label = fill(copy.availableIn, { minutes });
        } else if (next.dayLabel === copy.today) {
            label = fill(copy.availableToday, { time: next.startTime });
        } else if (next.dayLabel === copy.tomorrow) {
            label = fill(copy.availableTomorrow, { time: next.startTime });
        }

        return { state: 'soon', label, detail: `${next.startTime}–${next.endTime}` };
    }

    const day = days[0];
    const slot = day?.slots?.[0];

    return {
        state: 'later',
        label: fill(copy.nextOpenSlot, { day: day.shortLabel, time: slot?.time }),
        detail: availability.slotCountCapped
            ? fill(copy.openSlotsCapped, { count: availability.slotCount })
            : plural(copy.openSlots, availability.slotCount ?? 0),
    };
}

export function useLiveAvailability(availability, copy) {
    const now = ref(Date.now());
    let timer = null;

    onMounted(() => {
        timer = window.setInterval(() => {
            now.value = Date.now();
        }, TICK_MS);
    });

    onBeforeUnmount(() => {
        if (timer !== null) {
            window.clearInterval(timer);
        }
    });

    return computed(() => availabilityStatus(availability, copy.value ?? {}, now.value));
}
