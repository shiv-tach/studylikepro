<script setup>
import { computed } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const urls = computed(() => props.config.urls ?? {});
</script>

<template>
    <section class="mx-auto w-full max-w-6xl px-5 pb-16 lg:pb-20">
        <div class="relative overflow-hidden rounded-3xl px-6 py-14 text-center text-white sm:px-12" style="background: var(--color-ink)">
            <div class="lp-aurora lp-aurora-dark" aria-hidden="true"><span></span><span></span><span></span></div>
            <div class="lp-grid-lines lp-grid-lines-dark pointer-events-none absolute inset-0" aria-hidden="true"></div>

            <h2 class="relative text-display-lg font-semibold">{{ copy.ctaTitle }}</h2>
            <p class="relative mx-auto mt-5 max-w-xl text-lg leading-relaxed text-white/70">{{ copy.ctaBody }}</p>

            <div class="relative mt-8 flex justify-center">
                <a
                    :href="config.authenticated ? urls.dashboard : urls.registerStudent"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brand-500 px-7 py-3.5 font-medium whitespace-nowrap text-white transition hover:bg-brand-600"
                    style="box-shadow: var(--shadow-brand)"
                >
                    {{ config.authenticated ? copy.ctaDashboard : copy.ctaGuest }}
                    <AppIcon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </section>
</template>
