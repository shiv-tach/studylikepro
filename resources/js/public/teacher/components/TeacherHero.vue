<script setup>
import { computed } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';
import { statusTone } from '../statusTone';

const props = defineProps({
    teacher: { type: Object, required: true },
    breadcrumbs: { type: Array, default: () => [] },
    status: { type: Object, required: true },
    copy: { type: Object, required: true },
    timezoneLabel: { type: String, default: '' },
});

const tone = computed(() => statusTone(props.status.state));
</script>

<template>
    <section id="top" tabindex="-1" class="relative overflow-hidden border-b border-line bg-white focus:outline-none">
        <div class="lp-aurora lp-aurora-soft" aria-hidden="true"><span></span><span></span><span></span></div>
        <div class="lp-grid-lines pointer-events-none absolute inset-0" aria-hidden="true"></div>

        <div class="relative mx-auto w-full max-w-6xl px-5 pb-10 pt-8 lg:pb-14 lg:pt-12">
            <nav class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-ink-500" aria-label="Breadcrumb">
                <template v-for="crumb in breadcrumbs" :key="crumb.label">
                    <a v-if="crumb.url" :href="crumb.url" class="transition hover:text-ink">{{ crumb.label }}</a>
                    <span v-else class="font-medium text-ink-700">{{ crumb.label }}</span>
                    <span v-if="crumb.url" aria-hidden="true">/</span>
                </template>
            </nav>

            <div class="mt-7 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="flex min-w-0 flex-col items-start gap-5 sm:flex-row sm:gap-6">
                    <div
                        class="aspect-[4/3] w-full max-w-sm shrink-0 overflow-hidden rounded-2xl bg-brand-50 sm:aspect-[4/5] sm:w-40 sm:max-w-none lg:w-52"
                    >
                        <img
                            v-if="teacher.avatarUrl"
                            :src="teacher.avatarUrl"
                            :alt="teacher.name"
                            class="h-full w-full object-cover object-top"
                            decoding="async"
                        />
                        <span
                            v-else
                            class="flex h-full w-full items-center justify-center font-display text-5xl font-semibold text-brand-600"
                            aria-hidden="true"
                        >{{ teacher.initials }}</span>
                    </div>

                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-display-lg font-semibold">{{ teacher.name }}</h1>
                            <span class="inline-flex items-center gap-1 rounded-full bg-go/10 px-2.5 py-0.5 text-[11px] font-semibold text-go-ink">
                                <AppIcon name="shield-check" class="h-3.5 w-3.5" />
                                {{ teacher.verifiedLabel }}
                            </span>
                        </div>

                        <p v-if="teacher.headline" class="mt-2 max-w-2xl text-lg leading-relaxed text-ink-700">
                            {{ teacher.headline }}
                        </p>

                        <div class="mt-4 flex flex-wrap items-center gap-2 text-[11px] font-semibold">
                            <span
                                v-if="teacher.rating"
                                class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-0.5 text-brand-600"
                            >
                                ★ {{ teacher.rating }}
                                <span class="font-medium text-brand-600/70">({{ teacher.reviewsLabel }})</span>
                            </span>
                            <span v-else class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ copy.newTeacher }}</span>

                            <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ teacher.lessonsTaught }}</span>
                            <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ teacher.experience }}</span>
                            <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-ink-500">{{ teacher.rateChip }}</span>
                        </div>
                    </div>
                </div>

                <!-- The live answer: free now, back later today, or the next open slot. -->
                <div class="shrink-0 rounded-2xl border p-4 sm:w-80" :class="tone.panel" role="status" aria-live="polite">
                    <p class="flex items-center gap-2 text-sm font-semibold" :class="tone.text">
                        <span class="relative flex size-2.5 shrink-0">
                            <span
                                v-if="status.state === 'now'"
                                class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-70 motion-reduce:hidden"
                                :class="tone.dot"
                            ></span>
                            <span class="relative inline-flex size-2.5 rounded-full" :class="tone.dot"></span>
                        </span>
                        {{ status.label }}
                    </p>

                    <p v-if="status.detail" class="mt-1.5 pl-5 text-xs" :class="tone.muted">{{ status.detail }}</p>
                    <p class="mt-2 pl-5 text-[11px]" :class="tone.muted">{{ copy.liveNote }} · {{ timezoneLabel }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
