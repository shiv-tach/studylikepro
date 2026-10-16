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
                // The signed-in chrome (sidebar + top bar) that wraps every
                // x-app-layout screen, so the layout itself is a Vue component.
                'resources/js/app-shell/main.js',
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
