<script setup>
import { computed } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';
import GradeSwitcher from './GradeSwitcher.vue';

const props = defineProps({
    config: { type: Object, required: true },
    // The catalog opens subjects in place, so "View all grades" is another
    // in-page swap there and a plain link on the standalone page.
    clientNavigation: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);

const copy = computed(() => props.config.copy ?? {});
const subject = computed(() => props.config.subject ?? {});

function showAllGrades(event) {
    if (!props.clientNavigation) {
        return;
    }

    event.preventDefault();
    emit('navigate', subject.value.grade.allGradesUrl);
}
</script>

<template>
    <section id="top" tabindex="-1" class="relative overflow-hidden border-b border-line bg-white focus:outline-none">
        <div class="lp-aurora lp-aurora-soft" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="lp-grid-lines pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-6xl px-5 pb-12 pt-10 lg:pb-16 lg:pt-14">
            <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500" aria-label="Breadcrumb">
                <template v-for="crumb in config.breadcrumbs" :key="crumb.label">
                    <a :href="crumb.url" class="transition hover:text-ink">{{ crumb.label }}</a>
                    <span aria-hidden="true">/</span>
                </template>
                <template v-if="config.level">
                    <a :href="config.level.url" class="transition hover:text-ink">{{ config.level.name }}</a>
                    <span aria-hidden="true">/</span>
                </template>
                <template v-if="subject.grade">
                    <a :href="subject.grade.catalogUrl" class="transition hover:text-ink">{{ subject.grade.label }}</a>
                    <span aria-hidden="true">/</span>
                </template>
                <span class="font-medium text-ink-700">{{ subject.name }}</span>
            </nav>

            <div class="mt-8 flex flex-wrap items-start justify-between gap-6">
                <div class="flex items-start gap-4">
                    <span
                        class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-brand-50 text-3xl"
                        aria-hidden="true"
                    >{{ subject.icon }}</span>
                    <div>
                        <h1 class="text-display-lg font-semibold">{{ subject.name }}</h1>
                        <p v-if="subject.description" class="mt-3 max-w-2xl text-lg leading-relaxed text-ink-700">
                            {{ subject.description }}
                        </p>
                        <div v-if="subject.basketLabel || subject.grade" class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold">
                            <span v-if="subject.basketLabel" class="rounded-full bg-brand-50 px-3 py-1 text-brand-600">
                                {{ subject.basketLabel }} · {{ copy.pickOne }}
                            </span>

                            <span v-if="config.level" class="rounded-full bg-ink-50 px-3 py-1 text-ink-700">
                                {{ config.level.name }}
                            </span>

                            <!-- The grade is a filter, not a fact: it can be swapped
                                 here instead of walking back through the catalog. -->
                            <GradeSwitcher
                                v-if="subject.grade && subject.grades?.length"
                                :grades="subject.grades"
                                :selected="subject.grade"
                                :copy="copy"
                                :client-navigation="clientNavigation"
                                @navigate="emit('navigate', $event)"
                            />
                            <span v-else-if="subject.grade" class="rounded-full bg-ink-50 px-3 py-1 text-ink-700">
                                {{ subject.grade.chip }}
                            </span>

                            <a
                                v-if="subject.grade"
                                :href="subject.grade.catalogUrl"
                                class="inline-flex items-center gap-1 font-semibold text-brand-600 transition hover:text-brand-700"
                            >
                                <AppIcon name="chevron-left" class="h-3.5 w-3.5" />
                                {{ copy.backToGrade }}
                            </a>
                            <a
                                v-if="subject.grade"
                                :href="subject.grade.allGradesUrl"
                                class="text-ink-500 transition hover:text-ink"
                                @click="showAllGrades"
                            >
                                {{ copy.viewAllGrades }}
                            </a>
                        </div>
                    </div>
                </div>

                <span class="rounded-full bg-ink-50 px-3 py-1 text-xs font-semibold text-ink-500">{{ subject.lessonLabel }}</span>
            </div>
        </div>
    </section>
</template>
