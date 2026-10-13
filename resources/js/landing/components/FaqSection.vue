<script setup>
import { ref } from 'vue';
import AppIcon from './AppIcon.vue';
import { faqs } from '../content';

defineProps({
    urls: { type: Object, required: true },
});

const openIndex = ref(0);

function toggle(index) {
    openIndex.value = openIndex.value === index ? null : index;
}
</script>

<template>
    <section id="faq" class="border-t border-slate-200/80 dark:border-slate-800/80">
        <div class="mx-auto w-full max-w-6xl px-4 py-16 sm:px-6 lg:px-8 lg:py-24">
            <div class="grid gap-10 lg:grid-cols-[minmax(0,20rem)_minmax(0,1fr)] lg:gap-16">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-primary">FAQ</p>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white">
                        Questions students ask before booking
                    </h2>
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">
                        Still unsure about something?
                        <a :href="urls.contact" class="font-semibold text-primary hover:underline">Contact us</a>
                        and we will answer it properly.
                    </p>
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
                        The money side is covered in the
                        <a :href="urls.refunds" class="font-semibold text-primary hover:underline">cancellation &amp; refunds policy</a>.
                    </p>
                </div>

                <div class="divide-y divide-slate-200/80 overflow-hidden rounded-2xl border border-slate-200/80 bg-white dark:divide-slate-800/80 dark:border-slate-800/80 dark:bg-slate-900">
                    <div v-for="(faq, index) in faqs" :key="faq.question">
                        <h3>
                            <button
                                type="button"
                                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-slate-950/50"
                                :aria-expanded="openIndex === index"
                                :aria-controls="`faq-panel-${index}`"
                                @click="toggle(index)"
                            >
                                <span class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ faq.question }}</span>
                                <span
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500 transition-transform dark:bg-slate-800 dark:text-slate-400"
                                    :class="openIndex === index ? 'rotate-180 text-primary dark:text-primary' : ''"
                                >
                                    <AppIcon name="chevron-down" class="h-4 w-4" />
                                </span>
                            </button>
                        </h3>
                        <div
                            v-show="openIndex === index"
                            :id="`faq-panel-${index}`"
                            class="px-5 pb-5 text-sm leading-relaxed text-slate-600 dark:text-slate-400"
                        >
                            {{ faq.answer }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
