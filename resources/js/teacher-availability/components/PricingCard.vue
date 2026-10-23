<script setup>
/**
 * Lesson length and the default price, plus a read-only view of the subject
 * overrides.
 *
 * Three real things live here and each one posts its own form: the hourly rate
 * (PUT pricing), the lesson length (PUT settings, unchanged endpoint) and the
 * deep links into /teacher/subjects for a subject's own rates. The earnings
 * preview is the only client-side money maths on the page — it mirrors
 * `PlatformSettings::formatMinor()` from the island's currency symbol, exactly
 * like the services composer does.
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { FIELD, PRIMARY_BUTTON } from '../../shared/availability/styles';
import { format, formatMinor, useCopy, usePage, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const urls = useUrls();
const panel = usePanel(true);

const pricing = computed(() => page.pricing ?? {});
const currency = computed(() => pricing.value.currency ?? {});
const rateErrors = computed(() => page.errors?.hourly_rate ?? []);
const lengthErrors = computed(() => page.errors?.lesson_duration_minutes ?? []);

const rate = ref(String(pricing.value.rate ?? ''));
const lessonDuration = ref(String(pricing.value.lessonDuration ?? ''));

/** What the teacher keeps per hour at the rate currently in the box. */
const earningsLabel = computed(() => {
    const minor = Math.round((Number(rate.value) || 0) * 100);
    const commission = Number(pricing.value.commissionPercent ?? 0);

    return formatMinor(Math.round(minor * (100 - commission) / 100), currency.value);
});
</script>

<template>
    <section :class="panel" class="space-y-6">
        <UiSectionHeading
            :title="copy.pricing?.title"
            :description="copy.pricing?.description"
            icon="wallet"
            level="h3"
        >
            <template #action>
                <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                    {{ copy.pricing?.timezoneChip }}
                </span>
            </template>
        </UiSectionHeading>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- The default price every booking starts from. -->
            <form method="POST" :action="urls.pricing" class="rounded-xl border border-primary/30 bg-primary/5 p-4">
                <input type="hidden" name="_token" :value="page.csrfToken">
                <input type="hidden" name="_method" value="PUT">

                <UiFormField
                    :label="copy.pricing?.rateLabel"
                    for-id="hourly_rate"
                    :hint="copy.pricing?.rateHint"
                    :errors="rateErrors"
                    required
                >
                    <input
                        id="hourly_rate"
                        v-model="rate"
                        name="hourly_rate"
                        type="number"
                        step="0.01"
                        :min="pricing.limits?.min ?? 100"
                        :max="pricing.limits?.max ?? 100000"
                        inputmode="decimal"
                        :class="FIELD"
                        class="mt-1 block w-full px-3 py-2 text-lg font-bold"
                    >
                </UiFormField>

                <p class="mt-3 rounded-xl bg-white/70 px-3 py-2 text-xs leading-relaxed text-slate-600 dark:bg-slate-900/60 dark:text-slate-300">
                    {{ format(copy.pricing?.ratePreview, {
                        amount: earningsLabel,
                        percent: pricing.commissionPercent,
                    }) }}
                </p>

                <button type="submit" :class="[PRIMARY_BUTTON, 'mt-3 w-full']">
                    {{ copy.pricing?.rateSave }}
                </button>
            </form>

            <!-- Lesson length: its own form because it re-scopes every slot. -->
            <form method="POST" :action="urls.settings" class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                <input type="hidden" name="_token" :value="page.csrfToken">
                <input type="hidden" name="_method" value="PUT">

                <UiFormField
                    :label="copy.pricing?.lessonLengthLabel"
                    for-id="lesson_duration_minutes"
                    :hint="copy.pricing?.lessonLengthHint"
                    :errors="lengthErrors"
                    required
                >
                    <select
                        id="lesson_duration_minutes"
                        v-model="lessonDuration"
                        name="lesson_duration_minutes"
                        :class="FIELD"
                        class="mt-1 block w-full px-3 py-2 text-sm font-semibold"
                    >
                        <option v-for="duration in pricing.durations" :key="duration.value" :value="String(duration.value)">
                            {{ duration.label }}
                        </option>
                    </select>
                </UiFormField>

                <dl class="mt-3 space-y-1 text-xs text-slate-500 dark:text-slate-400">
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ copy.pricing?.rateLabel }}</dt>
                        <dd class="font-semibold text-slate-700 dark:text-slate-200">{{ pricing.rateLabel }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ copy.pricing?.feeLabel }}</dt>
                        <dd class="font-semibold text-slate-700 dark:text-slate-200">{{ pricing.commissionPercent }}%</dd>
                    </div>
                </dl>

                <button type="submit" :class="[PRIMARY_BUTTON, 'mt-3 w-full']">
                    {{ copy.pricing?.lessonLengthSave }}
                </button>
            </form>
        </div>

        <!-- Subject overrides are edited on the subjects screen, not here. -->
        <div class="border-t border-slate-200/80 pt-5 dark:border-slate-800">
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.pricing?.subjectTitle }}</h4>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.pricing?.subjectDescription }}</p>

            <p
                v-if="!pricing.subjects?.length"
                class="mt-3 rounded-xl border border-dashed border-slate-300 px-4 py-4 text-center text-xs text-slate-500 dark:border-slate-700 dark:text-slate-400"
            >
                {{ copy.pricing?.subjectEmpty }}
            </p>

            <div v-else class="mt-3 grid gap-3 sm:grid-cols-2">
                <article
                    v-for="subject in pricing.subjects"
                    :key="subject.name"
                    class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800"
                >
                    <p class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-primary">
                        <span aria-hidden="true">{{ subject.icon }}</span>
                        {{ subject.name }}
                    </p>

                    <p v-if="subject.rateLabel" class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                        {{ subject.rateLabel }}
                        <span class="text-sm font-medium text-slate-400">{{ copy.pricing?.perHour }}</span>
                    </p>

                    <div v-if="subject.gradeRates?.length" class="mt-2 space-y-1">
                        <div
                            v-for="grade in subject.gradeRates"
                            :key="grade.label"
                            class="flex items-center justify-between gap-2 text-xs text-slate-500 dark:text-slate-400"
                        >
                            <span>{{ grade.label }}</span>
                            <span class="font-semibold text-slate-700 dark:text-slate-200">{{ grade.rateLabel }}</span>
                        </div>
                    </div>

                    <a
                        :href="subject.editUrl"
                        class="mt-3 inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary/80"
                    >
                        {{ copy.pricing?.editRates }}
                        <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="pencil" /></span>
                    </a>
                </article>
            </div>
        </div>
    </section>
</template>
