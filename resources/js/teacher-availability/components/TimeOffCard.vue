<script setup>
/**
 * Time off: blocked date ranges disappear from the bookable slots, while
 * lessons already booked on them stay booked. Adding is one real POST form;
 * removing asks first and then submits a real DELETE form (the shared confirm
 * dialog owns no form of its own).
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiConfirmDialog from '../../shared/components/UiConfirmDialog.vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { FIELD, PRIMARY_BUTTON } from '../../shared/availability/styles';
import { format, useCopy, usePage, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const urls = useUrls();
const panel = usePanel();

const entries = computed(() => page.timeOff ?? []);
const form = computed(() => page.timeOffForm ?? {});

const fieldErrors = (key) => page.errors?.[key] ?? [];

const target = ref(null);
const deleteForm = ref(null);

function requestRemove(entry) {
    target.value = entry;
}

function confirmRemove() {
    if (!target.value || !deleteForm.value) {
        return;
    }

    deleteForm.value.action = target.value.deleteUrl;
    deleteForm.value.submit();
}

const dialogBody = computed(() => format(copy.timeOff?.removeBody ?? '', {
    range: target.value?.rangeLabel ?? '',
}));
</script>

<template>
    <section :class="panel">
        <UiSectionHeading
            :title="copy.timeOff?.title"
            :description="copy.timeOff?.description"
            icon="clock"
            level="h3"
        />

        <ul v-if="entries.length" class="mt-4 space-y-2">
            <li
                v-for="entry in entries"
                :key="entry.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200/80 px-4 py-2.5 dark:border-slate-800"
            >
                <div>
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ entry.rangeLabel }}</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ entry.reason }} · {{ entry.dayCountLabel }}
                    </p>
                </div>

                <button
                    type="button"
                    class="inline-flex items-center gap-1 rounded-xl px-3 py-1.5 text-xs font-semibold text-slate-500 transition hover:bg-rose-50 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-950/40"
                    :aria-label="format(copy.timeOff?.removeLabel, { range: entry.rangeLabel })"
                    @click="requestRemove(entry)"
                >
                    <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="trash" /></span>
                    {{ copy.timeOff?.remove }}
                </button>
            </li>
        </ul>

        <p
            v-else
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-4 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ copy.timeOff?.emptyTitle }} — {{ copy.timeOff?.emptyBody }}
        </p>

        <form
            method="POST"
            :action="urls.timeOff"
            class="mt-5 flex flex-wrap items-start gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800"
        >
            <input type="hidden" name="_token" :value="page.csrfToken">

            <UiFormField
                class="min-w-40"
                :label="copy.timeOff?.fromLabel"
                for-id="starts_on"
                :errors="fieldErrors('starts_on')"
                required
            >
                <input
                    id="starts_on"
                    name="starts_on"
                    type="date"
                    :value="form.starts_on ?? ''"
                    :class="FIELD"
                    class="mt-1 block w-full px-3 py-2"
                >
            </UiFormField>

            <UiFormField
                class="min-w-40"
                :label="copy.timeOff?.toLabel"
                for-id="ends_on"
                :errors="fieldErrors('ends_on')"
                required
            >
                <input
                    id="ends_on"
                    name="ends_on"
                    type="date"
                    :value="form.ends_on ?? ''"
                    :class="FIELD"
                    class="mt-1 block w-full px-3 py-2"
                >
            </UiFormField>

            <UiFormField
                class="min-w-48 flex-1"
                :label="copy.timeOff?.reasonLabel"
                for-id="reason"
                :errors="fieldErrors('reason')"
            >
                <input
                    id="reason"
                    name="reason"
                    type="text"
                    maxlength="120"
                    :value="form.reason ?? ''"
                    :placeholder="copy.timeOff?.reasonPlaceholder"
                    :class="FIELD"
                    class="mt-1 block w-full px-3 py-2"
                >
            </UiFormField>

            <button type="submit" :class="[PRIMARY_BUTTON, 'mt-6']">
                {{ copy.timeOff?.add }}
            </button>
        </form>

        <!-- The dialog owns no form; this one carries the real DELETE. -->
        <form ref="deleteForm" method="POST" class="hidden">
            <input type="hidden" name="_token" :value="page.csrfToken">
            <input type="hidden" name="_method" value="DELETE">
        </form>

        <UiConfirmDialog
            :open="Boolean(target)"
            :title="copy.timeOff?.removeTitle"
            :body="dialogBody"
            :confirm-label="copy.timeOff?.removeConfirm"
            :cancel-label="copy.confirmCancel"
            :close-label="copy.confirmClose"
            tone="rose"
            @confirm="confirmRemove"
            @close="target = null"
        />
    </section>
</template>
