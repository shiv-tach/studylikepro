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
