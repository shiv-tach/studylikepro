<script setup>
/**
 * The fields every service has, whatever its type: the title students read, the
 * flat price and an optional description.
 */
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy, usePage, useWorkspace } from '../composables/usePageContext';
import { FIELD } from '../styles';

const copy = useCopy();
const page = usePage();
const workspace = useWorkspace();

const { values, errors, currentType } = workspace;
</script>

<template>
    <div class="space-y-4">
        <UiFormField
            :label="copy.fields?.title"
            for-id="service-title"
            :errors="errors.title ?? []"
            required
        >
            <input
                id="service-title"
                v-model="values.title"
                name="title"
                type="text"
                :maxlength="page.limits?.title ?? 160"
                :placeholder="copy.fields?.titlePlaceholder"
                :class="FIELD"
                required
            >
        </UiFormField>

        <UiFormField
            :label="copy.fields?.price"
            for-id="service-price"
            :hint="currentType?.priceHint"
            :errors="errors.price ?? []"
            required
        >
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">
                    {{ page.currency?.symbol }}
                </span>
                <input
                    id="service-price"
                    v-model="values.price"
                    name="price"
                    type="number"
                    inputmode="decimal"
                    :min="page.limits?.priceMin ?? 1"
                    :max="page.limits?.priceMax ?? 1000000"
                    class="block w-full rounded-xl border-slate-300 bg-white py-2 pl-12 pr-3 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    required
                >
            </div>
        </UiFormField>

        <UiFormField
            :label="copy.fields?.description"
            for-id="service-description"
            :errors="errors.description ?? []"
        >
            <textarea
                id="service-description"
                v-model="values.description"
                name="description"
                rows="3"
                :maxlength="page.limits?.description ?? 2000"
                :placeholder="copy.fields?.descriptionPlaceholder"
                :class="FIELD"
            ></textarea>
        </UiFormField>
    </div>
</template>
