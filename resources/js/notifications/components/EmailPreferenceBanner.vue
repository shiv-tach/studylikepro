<script setup>
/**
 * The email-status banner: says whether emails are on or off and links to the
 * settings page where the preference is actually changed.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useUrls } from '../composables/usePageContext';

const props = defineProps({
    emailEnabled: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();

const title = computed(() => (props.emailEnabled ? copy.emailsOn : copy.emailsOff));
const hint = computed(() => (props.emailEnabled ? copy.emailsOnHint : copy.emailsOffHint));
const action = computed(() => (props.emailEnabled ? copy.turnOff : copy.turnOn));
</script>

<template>
    <div :class="[panel, 'flex flex-wrap items-center justify-between gap-3']">
        <div>
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ title }}</p>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ hint }}</p>
        </div>

        <a
            :href="urls.settings"
            class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
        >
            {{ action }}
        </a>
    </div>
</template>
