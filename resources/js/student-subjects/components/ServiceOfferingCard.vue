<script setup>
/**
 * One ready-made service. The action is whatever Blade decided the student may
 * do with it: buy it, start a marking order, or open the teacher's profile.
 */
import { CARD, CARD_HOVER } from '../../shared/styles';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy, useCsrfToken } from '../composables/usePageContext';

defineProps({
    offering: { type: Object, required: true },
});

const copy = useCopy();
const csrfToken = useCsrfToken();
const buttonClass =
    'flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90';

const outlineClass =
    'flex w-full items-center justify-center rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300';
</script>

<template>
    <article :class="[CARD, CARD_HOVER, 'flex flex-col p-5']">
        <img
            v-if="offering.teacher.avatarUrl"
            :src="offering.teacher.avatarUrl"
            :alt="offering.teacher.name"
            class="h-11 w-11 rounded-xl object-cover"
        />
        <span
            v-else
            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary"
        >
            {{ offering.teacher.initials }}
        </span>

        <div class="mt-3 min-w-0">
            <a
                :href="offering.teacher.url"
                class="block truncate text-sm font-bold text-primary transition-colors hover:text-primary/80"
            >
                {{ offering.teacher.name }}
            </a>

            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <UiChip v-if="offering.teacher.rating" tone="amber" icon="star">
                    {{ offering.teacher.rating }}
                    <span class="font-medium text-amber-600/80 dark:text-amber-400/80">
                        ({{ offering.teacher.ratingCount }})
                    </span>
                </UiChip>
                <UiChip v-else>{{ copy.newTeacher }}</UiChip>
            </div>
        </div>

        <div class="mt-3 flex items-start justify-between gap-3">
            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ offering.title }}</p>
            <p class="shrink-0 text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ offering.price }}</p>
        </div>

        <div v-if="offering.chips.length" class="mt-2 flex flex-wrap gap-1.5">
            <UiChip v-for="chip in offering.chips" :key="chip">{{ chip }}</UiChip>
        </div>

        <p v-if="offering.description" class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            {{ offering.description }}
        </p>

        <div class="mt-auto pt-4">
            <form v-if="offering.action.kind === 'form'" method="POST" :action="offering.action.url">
                <input type="hidden" name="_token" :value="csrfToken" />
                <button type="submit" :class="buttonClass">{{ offering.action.label }}</button>
            </form>

            <a
                v-else
                :href="offering.action.url"
                :class="offering.action.variant === 'primary' ? buttonClass : outlineClass"
            >
                {{ offering.action.label }}
            </a>
        </div>
    </article>
</template>
