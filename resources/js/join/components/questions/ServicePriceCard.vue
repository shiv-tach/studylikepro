<script setup>
/**
 * One service of question 4 together with the price it is sold at.
 *
 * The price row deliberately sits *outside* the header `<label>`: an input
 * nested inside a label makes every click on the field toggle the checkbox.
 * The field is only `required` while the service is ticked, so a hidden input
 * can never block the form, and `v-show` keeps an amount the teacher already
 * typed when they untick a service and change their mind.
 */
import { computed } from 'vue';
import FieldMessages from '../FieldMessages.vue';
import { NUMBER_FIELD } from '../../styles';

const props = defineProps({
    service: { type: Object, required: true },
    currency: { type: String, default: '' },
    min: { type: Number, default: 0 },
    max: { type: Number, default: null },
    unitLabel: { type: String, default: null },
    hint: { type: String, default: null },
    messages: { type: Array, default: () => [] },
});

const picked = defineModel({ type: Array, default: () => [] });
const price = defineModel('price', { default: '' });

const checked = computed(() => picked.value.includes(props.service.value));
const inputId = computed(() => `service_${props.service.value}`);
const priceId = computed(() => (props.service.priced ? `service_rate_${props.service.value}` : 'hourly_rate'));

function toggle(event) {
    const next = [...picked.value];
    const index = next.indexOf(props.service.value);

    if (event.target.checked && index === -1) {
        next.push(props.service.value);
    } else if (!event.target.checked && index !== -1) {
        next.splice(index, 1);
    }

    picked.value = next;
}
</script>

<template>
    <div
        class="flex h-full flex-col gap-3 rounded-2xl border-2 p-4 transition duration-200"
        :class="checked
            ? 'border-primary bg-primary/5 shadow-md shadow-primary/10'
            : 'border-slate-200 bg-white hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-sm motion-reduce:transform-none motion-reduce:transition-none dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700'"
    >
        <input
            :id="inputId"
            type="checkbox"
            name="services[]"
            :value="service.value"
            :checked="checked"
            class="peer sr-only"
            @change="toggle"
        />

        <label :for="inputId" class="flex cursor-pointer items-start gap-3">
            <span class="text-2xl leading-none" aria-hidden="true">{{ service.icon }}</span>

            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-base font-bold text-slate-900 dark:text-slate-100">{{ service.label }}</span>

                    <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">
                        {{ service.badge }}
                    </span>
                </span>

                <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ service.hint }}</span>
            </span>

            <span
                class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 transition-colors"
                :class="checked ? 'border-primary bg-primary text-white' : 'border-slate-300 bg-white dark:border-slate-600 dark:bg-slate-900'"
                aria-hidden="true"
            >
                <svg v-if="checked" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                </svg>
            </span>
        </label>

        <div v-show="checked" class="border-t border-slate-200/80 pt-3 dark:border-slate-800/80">
            <label :for="priceId" class="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                {{ service.priceLabel }}
            </label>

            <div class="mt-1.5 flex items-center gap-2">
                <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ currency }}</span>

                <input
                    :id="priceId"
                    v-model="price"
                    :name="service.priced ? `service_rates[${service.value}]` : 'hourly_rate'"
                    type="number"
                    inputmode="numeric"
                    :min="min"
                    :max="max"
                    step="1"
                    :required="checked"
                    :class="NUMBER_FIELD"
                />

                <span v-if="unitLabel" class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">/ {{ unitLabel }}</span>
            </div>

            <p v-if="hint" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>

            <FieldMessages :messages="messages" />
        </div>
    </div>
</template>
