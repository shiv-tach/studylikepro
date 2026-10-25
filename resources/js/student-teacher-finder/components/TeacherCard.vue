<script setup>
/**
 * One teacher in the results: who they are, what they teach, what the lesson
 * costs at the student's grade and when they are next free. Everything shown
 * here arrives pre-formatted from Blade, so the card never guesses a rate, a
 * timezone or a URL.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    teacher: { type: Object, required: true },
});

const copy = useCopy();
// The card lifts on hover, so it asks for the interactive surface.
const panel = usePanel(true);
</script>

<template>
    <a
        :href="teacher.url"
        :class="[panel, 'group flex h-full flex-col overflow-hidden focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40']"
    >
        <!-- Below sm a single-column card is as wide as the screen, so a
             full-bleed banner would slice most of the photo away: the avatar
             sits next to the name instead. The negative margins from sm up
             cancel the panel's p-6 padding for the full-bleed banner. -->
        <div class="mb-3 flex items-center gap-3 sm:mb-0 sm:block">
            <div class="h-20 w-20 shrink-0 overflow-hidden rounded-2xl bg-primary/5 sm:-mx-6 sm:-mt-6 sm:mb-3 sm:h-40 sm:w-auto sm:rounded-none">
                <img
                    v-if="teacher.avatarUrl"
                    :src="teacher.avatarUrl"
                    :alt="teacher.name"
                    class="h-full w-full object-cover object-center sm:object-top"
                    loading="lazy"
                    decoding="async"
                >
                <span
                    v-else
                    class="flex h-full w-full items-center justify-center bg-primary/10 text-2xl font-bold text-primary sm:text-4xl"
                    aria-hidden="true"
                >
                    {{ teacher.initials }}
                </span>
            </div>

            <div class="min-w-0">
                <h3 class="truncate text-base font-bold text-slate-800 dark:text-slate-100">{{ teacher.name }}</h3>
                <p v-if="teacher.headline" class="mt-0.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ teacher.headline }}</p>
            </div>
        </div>

        <div class="mt-3 flex flex-wrap items-center gap-2">
            <UiChip v-if="teacher.rating" tone="amber" icon="star">
                {{ teacher.rating }}
                <span class="font-medium text-amber-600/80 dark:text-amber-400/80">({{ teacher.ratingCount }})</span>
            </UiChip>

            <UiChip v-else tone="slate">{{ copy.newTeacher }}</UiChip>

            <UiChip tone="emerald" icon="shield-check">{{ copy.verified }}</UiChip>
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5">
            <UiChip v-for="subject in teacher.subjects" :key="subject" tone="primary">
                {{ subject }}
            </UiChip>

            <UiChip v-if="teacher.extraSubjects > 0" tone="slate">+{{ teacher.extraSubjects }}</UiChip>
        </div>

        <p v-if="teacher.metaLabel" class="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">
            {{ teacher.metaLabel }}
        </p>

        <div class="mt-auto pt-4">
            <div class="flex items-end justify-between gap-3">
                <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">
                    {{ teacher.rate }}<span class="text-sm font-medium text-slate-400">{{ copy.perHour }}</span>
                </p>

                <span class="inline-flex shrink-0 items-center gap-1 whitespace-nowrap pb-1 text-sm font-semibold text-primary">
                    {{ copy.viewProfile }}
                    <span class="h-4 w-4 transition-transform group-hover:translate-x-0.5"><AppIcon name="chevron-right" /></span>
                </span>
            </div>

            <p
                class="mt-1 text-xs"
                :class="teacher.availability ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400'"
            >
                {{ teacher.availability?.label ?? copy.noSlots }}
            </p>
        </div>
    </a>
</template>
