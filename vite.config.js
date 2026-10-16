import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // The marketing landing page is its own Vue entry, so the rest of
                // the app keeps shipping plain Blade + Alpine without Vue.
                'resources/js/landing/main.js',
                // The student dashboard is the signed-in shell's Vue page; its
                // bundle stays out of every other Blade + Alpine screen.
                'resources/js/student-dashboard/main.js',
                // "My subjects" and the student subject page share one Vue
                // entry: whichever mount point the page renders boots.
                'resources/js/student-subjects/main.js',
                // The request list and the question form share one Vue entry,
                // mounted only on the pages that render their island.
                'resources/js/student-requests/main.js',
                // "My lessons" is the student's booking list; its bundle stays
                // out of the other booking screens.
                'resources/js/student-lessons/main.js',
                // The direct-booking stepper at /student/lessons/book/{teacher}
                // carries its own bundle, so the list and the detail page never
                // pay for the slot picker.
                'resources/js/student-booking-create/main.js',
                // One lesson at /student/lessons/{booking}: the status banner,
                // the timeline, the money and the review.
                'resources/js/student-booking-show/main.js',
                // The grade-matched teacher finder at /student/find-a-teacher
                // carries its own bundle, so the directory screens around it
                // never pay for the filter panel and the result cards.
                'resources/js/student-teacher-finder/main.js',
                // "My purchases" is the student's service wallet; its bundle
                // stays out of the detail, checkout and session screens.
                'resources/js/student-purchases/main.js',
                // The purchase detail page carries its own bundle, so neither
                // the wallet nor the checkout screens pay for the other's code.
                'resources/js/student-purchase-show/main.js',
                // One teacher in the student workspace, with the grade-scoped
                // rates, services, availability and reviews.
                'resources/js/student-teacher-profile/main.js',
                // The student's learning profile at /student/profile: the
                // answers that scope every lesson, teacher and price.
                'resources/js/student-profile/main.js',
                // The signed-in chrome (sidebar + top bar) that wraps every
                // x-app-layout screen, so the layout itself is a Vue component.
                'resources/js/app-shell/main.js',
                // The lesson-chat inbox at /messages; its bundle stays out of
                // the thread itself and every other Blade + Alpine screen.
                'resources/js/messages/main.js',
                // One lesson thread at /messages/{conversation}, with the
                // composer, the poller and the report dialog.
                'resources/js/messages-thread/main.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                // Blade handles asset URLs; leave them untouched in single-file components.
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
});
