<script setup>
import SiteFooter from '../landing/components/SiteFooter.vue';
import SiteHeader from '../landing/components/SiteHeader.vue';
import SubjectPage from './subject/SubjectPage.vue';

const props = defineProps({
    config: { type: Object, required: true },
});
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
            :urls="config.urls"
            :logo="config.images.logo"
            :home-url="config.urls.home"
            active-section="subjects"
        />

        <SubjectPage :config="config" />

        <SiteFooter
            :app-name="config.appName"
            :urls="config.urls"
            :support="config.support ?? {}"
            :logo="config.images.logo"
            :landing-url="config.urls.home"
        />
    </div>
</template>
