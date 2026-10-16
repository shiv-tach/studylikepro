<script setup>
/**
 * The one interactive card on the settings home: the email-preference toggle.
 * It is a real POST form, so the preference is validated and saved server-side;
 * the checkbox only reflects the value Blade sent back.
 */
import { computed, ref } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { format, useCopy, useCsrfToken, useUrls } from '../composables/usePageContext';

const props = defineProps({
    title: { type: String, required: true },
    icon: { type: String, default: 'bell' },
    unreadCount: { type: Number, default: 0 },
    emailEnabled: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const csrfToken = useCsrfToken();

// The checkbox is the form's own state; the server re-renders it after a save.
const email = ref(props.emailEnabled);
const submitting = ref(false);

const badge = computed(() =>
    props.unreadCount > 0
        ? { tone: 'primary', label: format(copy.unreadBadge, { count: props.unreadCount }) }
        : { tone: 'slate', label: copy.upToDateBadge },
);
</script>

<template>
    <div
        class="group flex h-full flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40"
    >
        <div class="mb-4 flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 p-2.5 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                <AppIcon :name="icon" />
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ title }}</h3>
                <UiChip :tone="badge.tone">{{ badge.label }}</UiChip>
            </div>
        </div>

        <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            {{ copy.bodyPrefix }}
            <a :href="urls.notifications" class="font-semibold text-primary hover:underline">{{ copy.bodyLink }}</a>{{ copy.bodySuffix }}
        </p>

        <form
            method="POST"
            :action="urls.notificationsUpdate"
            class="mt-4 space-y-3"
            @submit="submitting = true"
        >
            <input type="hidden" name="_token" :value="csrfToken">

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                <input
                    v-model="email"
                    type="checkbox"
                    name="email"
                    value="1"
                    class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800"
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ copy.emailLabel }}</span>
                    <span class="block text-xs text-slate-500 dark:text-slate-400">{{ copy.emailHint }}</span>
                </span>
            </label>

            <button
                type="submit"
                :disabled="submitting"
                class="inline-flex items-center justify-center rounded-xl bg-primary px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:opacity-60 dark:focus:ring-offset-slate-900"
            >
                {{ submitting ? copy.saving : copy.save }}
            </button>
        </form>

        <a :href="urls.notifications" class="mt-4 flex items-center gap-1 text-sm font-medium text-primary">
            {{ copy.viewNotifications }}
            <span class="h-4 w-4 transition-transform group-hover:translate-x-0.5">
                <AppIcon name="chevron-right" />
            </span>
        </a>
    </div>
</template>
