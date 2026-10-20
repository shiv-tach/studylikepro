<script setup>
import { computed } from 'vue';
import SiteFooter from '../landing/components/SiteFooter.vue';
import SiteHeader from '../landing/components/SiteHeader.vue';
import TeacherPage from './teacher/TeacherPage.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const urls = computed(() => props.config.urls ?? {});
</script>

<template>
    <div class="lp-theme flex min-h-screen flex-col bg-white text-ink">
        <a
            href="#top"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-full focus:bg-brand-500 focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:text-white"
        >
            Skip to content
        </a>

        <SiteHeader
            :app-name="config.appName"
            :authenticated="config.authenticated"
            :urls="urls"
            :logo="config.images.logo"
            :home-url="urls.home"
            active-section="teachers"
        />

        <TeacherPage :config="config" />

        <SiteFooter
            :app-name="config.appName"
            :urls="urls"
            :support="config.support ?? {}"
            :logo="config.images.logo"
            :landing-url="urls.home"
        />
    </div>
</template>
