<script setup>
import { computed } from 'vue';
import { useLiveAvailability } from './composables/useLiveAvailability';
import AboutPanel from './components/AboutPanel.vue';
import AvailabilityPanel from './components/AvailabilityPanel.vue';
import BookingCard from './components/BookingCard.vue';
import GradesPanel from './components/GradesPanel.vue';
import ReviewsPanel from './components/ReviewsPanel.vue';
import TeacherHero from './components/TeacherHero.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const status = useLiveAvailability(props.config.availability, copy);
</script>

<template>
    <main class="flex-1">
        <TeacherHero
            :teacher="config.teacher"
            :breadcrumbs="config.breadcrumbs"
            :status="status"
            :copy="copy"
            :timezone-label="config.availability.timezoneLabel"
        />

        <section class="bg-ink-50 py-10 lg:py-14">
            <div class="mx-auto grid w-full max-w-6xl gap-6 px-5 lg:grid-cols-3">
                <div class="min-w-0 space-y-6 lg:col-span-2">
                    <AboutPanel :teacher="config.teacher" :copy="copy" />
                    <GradesPanel :grades="config.grades" :copy="copy" />
                    <AvailabilityPanel
                        :availability="config.availability"
                        :status="status"
                        :copy="copy"
                        :booking="config.booking"
                    />
                    <ReviewsPanel :reviews="config.reviews" :copy="copy" />
                </div>

                <div class="min-w-0 lg:sticky lg:top-24 lg:self-start">
                    <BookingCard :booking="config.booking" />
                </div>
            </div>
        </section>
    </main>
</template>
