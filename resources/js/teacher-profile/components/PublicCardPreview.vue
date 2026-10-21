<script setup>
/**
 * A live copy of the card students see in the public teacher directory
 * (resources/js/public/TeachersApp.vue): the same 4:3 photo, name, headline,
 * rating, verification chip, subject pills, rate and next-slot line. The card
 * keeps the public page's light palette on purpose — it previews that page, not
 * the workspace it is embedded in — and every editable field updates as the
 * teacher types.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';

const copy = useCopy();
const panel = usePanel();

const { card } = useProfileForm();

// The public card shows the first three subjects and counts the rest.
const subjectPills = computed(() => (card.value.subjects ?? []).slice(0, 3));
const extraSubjects = computed(() => Math.max(0, card.value.extraSubjects ?? 0));

function verificationClasses() {
    return card.value.verified
        ? 'bg-emerald-500/10 text-emerald-700'
        : 'bg-ink-50 text-ink-500';
}
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.previewTitle }}</h3>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 motion-reduce:animate-none" aria-hidden="true"></span>
                {{ copy.previewLive }}
            </span>
        </div>

        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ copy.previewHint }}</p>

        <article
            class="mt-4 overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-sm transition-shadow duration-300"
            :aria-label="copy.previewTitle"
        >
            <div class="relative aspect-[4/3] w-full overflow-hidden bg-brand-50">
                <img
                    v-if="card.avatarUrl"
                    :src="card.avatarUrl"
                    :alt="card.name"
                    class="h-full w-full object-cover object-top"
                >
                <span
                    v-else
                    class="flex h-full w-full items-center justify-center text-4xl font-semibold text-brand-600"
                    aria-hidden="true"
                >{{ card.initials }}</span>
            </div>

            <div class="flex min-w-0 flex-col px-6 pt-5">
                <h4 class="truncate text-lg font-semibold text-ink">{{ card.name }}</h4>
                <p
                    class="mt-0.5 line-clamp-2 min-h-10 text-sm"
                    :class="card.headline ? 'text-ink-500' : 'text-ink-400 italic'"
                >
                    {{ card.headline || copy.previewHeadlineEmpty }}
                </p>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2 px-6 text-xs font-semibold">
                <span v-if="card.rating" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-brand-700">
                    ★ {{ card.rating }}
                    <span class="font-medium text-brand-500">({{ card.ratingCount }})</span>
                </span>
                <span v-else class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ copy.newTeacher }}</span>

                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5" :class="verificationClasses()">
                    <span class="h-3.5 w-3.5 shrink-0"><AppIcon name="shield" /></span>
                    {{ card.verified ? copy.verified : card.verificationLabel }}
                </span>
            </div>

            <div v-if="subjectPills.length || card.languages.length" class="mt-4 flex flex-wrap gap-1.5 px-6">
                <span
                    v-for="subject in subjectPills"
                    :key="subject.label"
                    class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-700"
                >{{ subject.label }}</span>

                <span v-if="extraSubjects" class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-500">
                    +{{ extraSubjects }}
                </span>

                <template v-if="!subjectPills.length">
                    <span
                        v-for="language in card.languages"
                        :key="language"
                        class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-semibold text-ink-700"
                    >{{ language }}</span>
                </template>
            </div>

            <p v-if="card.metaLabel" class="mt-3 px-6 text-xs font-medium text-ink-500">{{ card.metaLabel }}</p>

            <div class="mt-auto flex items-end justify-between gap-3 px-6 pb-6 pt-5">
                <div>
                    <p class="text-xl font-semibold text-ink">
                        <template v-if="card.rate">
                            {{ card.rate }}<span class="text-sm font-normal text-ink-400">{{ copy.perHour }}</span>
                        </template>
                        <span v-else class="text-base font-medium text-ink-400">{{ copy.previewRateEmpty }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-ink-500">
                        <template v-if="card.nextSlot">{{ copy.nextLabel }} {{ card.nextSlot }}</template>
                        <template v-else>{{ copy.noSlots }}</template>
                    </p>
                </div>
                <span class="inline-flex items-center gap-1 text-sm font-medium text-brand-600">
                    {{ copy.view }}
                    <span aria-hidden="true">→</span>
                </span>
            </div>
        </article>

        <p class="mt-3 text-xs leading-relaxed text-slate-400">{{ copy.previewNote }}</p>
    </section>
</template>
