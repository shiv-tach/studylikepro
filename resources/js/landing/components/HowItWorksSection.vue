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
    <section id="how-it-works" class="border-t border-slate-200/80 dark:border-slate-800/80">
        <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="max-w-2xl">
                <p class="text-xs font-bold uppercase tracking-widest text-primary">How it works</p>
                <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                    From stuck to unstuck in seven steps
                </h2>
                <p class="mt-3 text-slate-600 dark:text-slate-400">
                    Tap a step to see what happens — this is the same journey the app walks you through.
                </p>
            </div>

            <div class="mt-10 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:items-start lg:gap-8">
                <ol class="flex flex-col gap-2.5">
                    <li v-for="(step, index) in steps" :key="step.title">
                        <button
                            type="button"
                            :aria-current="activeIndex === index ? 'step' : undefined"
                            class="flex w-full items-center gap-4 rounded-2xl border px-4 py-3.5 text-left transition"
                            :class="activeIndex === index
                                ? 'border-primary/40 bg-primary/5 shadow-sm shadow-primary/5'
                                : 'border-slate-200/80 bg-white hover:border-primary/30 dark:border-slate-800/80 dark:bg-slate-900'"
                            @click="activeIndex = index"
                        >
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border text-sm font-bold"
                                :class="activeIndex === index
                                    ? 'border-transparent bg-primary text-white'
                                    : 'border-slate-200/80 bg-slate-50 text-slate-500 dark:border-slate-800/80 dark:bg-slate-950 dark:text-slate-400'"
                            >
                                {{ index + 1 }}
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-slate-900 dark:text-white">{{ step.title }}</span>
                                <span class="block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ step.summary }}</span>
                            </span>

                            <span
                                class="hidden shrink-0 sm:block"
                                :class="activeIndex === index ? 'text-primary' : 'text-slate-300 dark:text-slate-600'"
                            >
                                <AppIcon :name="step.icon" class="h-5 w-5" />
                            </span>
                        </button>
                    </li>
                </ol>

                <div
                    class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xl shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-900 lg:sticky lg:top-24"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-primary">
                            <AppIcon :name="activeStep.icon" class="h-6 w-6" />
                        </span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            Step {{ activeIndex + 1 }} of {{ steps.length }}
                        </span>
                    </div>

                    <h3 class="mt-5 text-lg font-bold text-slate-900 dark:text-white">{{ activeStep.title }}</h3>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ activeStep.detail }}</p>

                    <div class="mt-6 flex items-center gap-2">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800/80 dark:text-slate-300"
                            :disabled="activeIndex === 0"
                            @click="go(-1)"
                        >
                            Previous
                        </button>
                        <button
                            type="button"
                            class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-slate-900"
                            :disabled="activeIndex === steps.length - 1"
                            @click="go(1)"
                        >
                            Next
                        </button>
                    </div>

                    <div class="mt-6 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-primary to-purple-600 transition-all duration-300"
                            :style="{ width: `${((activeIndex + 1) / steps.length) * 100}%` }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
