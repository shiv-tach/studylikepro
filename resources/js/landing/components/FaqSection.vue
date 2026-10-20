<script setup>
import { faqs } from '../content';

defineProps({
    urls: { type: Object, required: true },
});
</script>

<template>
    <section id="faq" class="scroll-mt-20 border-b border-line bg-white py-16 lg:py-20">
        <div class="mx-auto w-full max-w-3xl px-5">
            <p class="text-sm font-medium text-brand-600">FAQ</p>
            <h2 class="mt-3 text-2xl font-semibold text-ink md:text-3xl">Questions students ask before booking</h2>
            <p class="mt-5 text-lg leading-relaxed text-ink-700">
                Still unsure about something?
                <a :href="urls.contact" class="font-medium underline underline-offset-4">Contact us</a>
                and we will answer it properly. The money side is covered in the
                <a :href="urls.refunds" class="font-medium underline underline-offset-4">cancellation &amp; refunds policy</a>.
            </p>

            <div class="mt-8 divide-y divide-line border-y border-line">
                <details
                    v-for="(faq, index) in faqs"
                    :key="faq.question"
                    class="group py-5"
                    :open="index === 0"
                >
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium text-ink">
                        {{ faq.question }}
                        <span class="lp-faq-plus shrink-0 text-xl leading-none text-ink-500 transition" aria-hidden="true">+</span>
                    </summary>
                    <p class="mt-3 leading-relaxed text-ink-700">{{ faq.answer }}</p>
                </details>
            </div>
        </div>
    </section>
</template>
