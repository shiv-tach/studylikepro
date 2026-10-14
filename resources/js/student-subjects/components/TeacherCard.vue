<script setup>
/**
 * A teacher preview card. The whole card is a link into the teacher's profile;
 * the "Book now" action sits above it on the z axis so it stays clickable.
 */
import { CARD, CARD_HOVER, HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    teacher: { type: Object, required: true },
});

const copy = useCopy();
</script>

<template>
    <article :class="[CARD, CARD_HOVER, 'group relative flex flex-col p-6']">
        <a
            :href="teacher.url"
            class="absolute inset-0 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        >
            <span class="sr-only">{{ teacher.name }}</span>
        </a>

        <img
            v-if="teacher.avatarUrl"
            :src="teacher.avatarUrl"
            :alt="teacher.name"
            class="h-12 w-12 rounded-xl object-cover"
        />
        <span
            v-else
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold text-primary"
        >
            {{ teacher.initials }}
        </span>

        <div class="mt-3 min-w-0">
            <h3 :class="['truncate text-base font-bold', HEADING_TEXT]">{{ teacher.name }}</h3>

            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                <UiChip v-if="teacher.rating" tone="amber" icon="star">
                    {{ teacher.rating }}
                    <span class="font-medium text-amber-600/80 dark:text-amber-400/80">({{ teacher.ratingCount }})</span>
                </UiChip>
                <UiChip v-else>{{ copy.newTeacher }}</UiChip>

                <UiChip tone="emerald" icon="shield-check">{{ copy.verified }}</UiChip>
            </div>
        </div>

        <p v-if="teacher.headline" :class="['mt-2 line-clamp-2 text-sm', MUTED_TEXT]">
            {{ teacher.headline }}
        </p>

        <p v-if="teacher.metaLabel" :class="['mt-3 text-xs font-medium', MUTED_TEXT]">
            {{ teacher.metaLabel }}
        </p>

        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
            <div>
                <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">
                    <span v-if="teacher.rateFrom" class="text-xs font-medium text-slate-400">{{ copy.rateFrom }} </span>
                    {{ teacher.rate }}<span class="text-sm font-medium text-slate-400">{{ copy.perHour }}</span>
                </p>
                <p :class="['mt-0.5 text-xs', MUTED_TEXT]">
                    {{ teacher.nextSlot ? `${copy.nextLabel} ${teacher.nextSlot}` : copy.noSlots }}
                </p>
            </div>

            <a
                v-if="teacher.canBook"
                :href="teacher.bookUrl"
                class="relative z-10 inline-flex shrink-0 items-center gap-1 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90"
            >
                {{ copy.bookNow }}
            </a>

            <span v-else class="relative z-10 inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary">
                {{ copy.viewProfile }}
                <span class="h-4 w-4 transition-transform group-hover:translate-x-0.5">
                    <AppIcon name="chevron-right" />
                </span>
            </span>
        </div>
    </article>
</template>
