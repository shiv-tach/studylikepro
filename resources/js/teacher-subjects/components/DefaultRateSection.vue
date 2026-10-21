<script setup>
/**
 * Step 3: the fallback rate. A grade without its own rate uses this, and an
 * empty value falls back to the teacher's base rate on the server.
 */
import UiFormField from '../../shared/components/UiFormField.vue';
import { format, useCopy, usePage } from '../composables/usePageContext';
import { FIELD } from '../styles';

const props = defineProps({
    subject: { type: Object, required: true },
});

const copy = useCopy();
const page = usePage();
</script>

<template>
    <section :aria-label="copy.rate?.title" class="max-w-md">
        <p v-if="copy.rate?.step" class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">
            {{ copy.rate.step }}
        </p>

        <UiFormField
            :label="copy.rate?.title"
            :for-id="`rate-${subject.id}`"
            :hint="format(copy.rate?.hint, { rate: page.teacher?.baseRateLabel })"
            :errors="subject.errors?.rate ?? []"
        >
            <div class="relative mt-1">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">
                    {{ page.teacher?.currencySymbol }}
                </span>
                <input
                    :id="`rate-${subject.id}`"
                    name="rate_per_hour"
                    type="number"
                    min="100"
                    max="100000"
                    inputmode="decimal"
                    v-model.number="subject.defaultRate"
                    :placeholder="copy.rate?.placeholder"
                    :class="[FIELD, 'pl-8']"
                >
            </div>
        </UiFormField>
    </section>
</template>
