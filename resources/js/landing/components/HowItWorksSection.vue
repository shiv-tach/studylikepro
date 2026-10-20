<script setup>
import { computed, ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { steps } from '../content';

const activeIndex = ref(0);
const activeStep = computed(() => steps[activeIndex.value]);

function go(offset) {
    const next = activeIndex.value + offset;

    if (next >= 0 && next < steps.length) {
        activeIndex.value = next;
    }
}
</script>

<template>
    <section id="how-it-works" class="scroll-mt-20 border-b border-line bg-white py-16 lg:py-24">
        <div class="mx-auto w-full max-w-6xl px-5">
            <p class="text-sm font-medium text-brand-600">How it works</p>
            <h2 class="mt-3 max-w-3xl text-display-lg font-semibold">From stuck to unstuck in seven steps</h2>
            <p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">
                Tap a step to see what happens — this is the same journey the app walks you through.
            </p>

            <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:items-start lg:gap-12">
                <ol class="flex flex-col gap-1">
                    <li v-for="(step, index) in steps" :key="step.title">
                        <button
                            type="button"
                            :aria-current="activeIndex === index ? 'step' : undefined"
                            class="flex w-full items-center gap-4 rounded-2xl px-4 py-3.5 text-left transition"
                            :class="activeIndex === index
                                ? 'hairline bg-white'
                                : 'hover:bg-ink-50'"
                            @click="activeIndex = index"
                        >
                            <span
                                class="flex size-10 shrink-0 items-center justify-center rounded-xl text-sm font-semibold"
                                :class="activeIndex === index
                                    ? 'bg-brand-500 text-white'
                                    : 'bg-ink-50 text-ink-500'"
                            >
                                {{ index + 1 }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-ink">{{ step.title }}</span>
                                <span class="block text-xs leading-relaxed text-ink-500">{{ step.summary }}</span>
                            </span>

                            <span
                                class="hidden shrink-0 sm:block"
                                :class="activeIndex === index ? 'text-brand-600' : 'text-ink-400'"
                            >
                                <AppIcon :name="step.icon" class="h-5 w-5" />
                            </span>
                        </button>
                    </li>
                </ol>

                <div
                    class="hairline rounded-2xl bg-white p-6 lg:sticky lg:top-24"
                    style="box-shadow: var(--shadow-md)"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex size-12 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                            <AppIcon :name="activeStep.icon" class="h-6 w-6" />
                        </span>
                        <span class="rounded-full bg-ink-50 px-3 py-1 text-xs font-medium text-ink-700">
                            Step {{ activeIndex + 1 }} of {{ steps.length }}
                        </span>
                    </div>

                    <h3 class="mt-5 font-display text-xl font-semibold text-ink">{{ activeStep.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-ink-700">{{ activeStep.detail }}</p>

                    <div class="mt-6 flex items-center gap-2">
                        <button
                            type="button"
                            class="hairline rounded-full px-4 py-2 text-sm font-medium text-ink-700 transition hover:border-ink-500 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="activeIndex === 0"
                            @click="go(-1)"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded-full bg-brand-500 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="activeIndex === steps.length - 1"
                            @click="go(1)"
                        >
                            Next
                        </button>
                    </div>

                    <div class="mt-6 h-1.5 w-full overflow-hidden rounded-full bg-ink-50">
                        <div
                            class="h-full rounded-full bg-brand-500 transition-all duration-300"
                            :style="{ width: `${((activeIndex + 1) / steps.length) * 100}%` }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
