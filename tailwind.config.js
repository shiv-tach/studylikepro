import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{vue,js}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                // Display face for the marketing landing page (payments.lk reference).
                display: ['"Clash Grotesk"', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: 'rgb(var(--primary) / <alpha-value>)',
                // Landing-page design tokens — ported from DOC/payments-lk-design-reference.md.
                // Additive names only; nothing else in the app uses brand/ink/line/go.
                brand: {
                    50: '#f0f6fa',
                    200: '#b8d6e6',
                    300: '#8abcd7',
                    400: '#4797c0',
                    500: '#006ea7',
                    600: '#005f90',
                    700: '#004d75',
                },
                ink: {
                    DEFAULT: '#0d1117',
                    50: '#f6f8fa',
                    400: '#98a2b0',
                    500: '#6b7684',
                    700: '#3f4b57',
                    800: '#1f2933',
                },
                line: '#e6eaef',
                go: {
                    DEFAULT: '#00a34d',
                    ink: '#00823e',
                },
            },
            boxShadow: {
                brand: '0 8px 24px #006ea747',
            },
        },
    },

    plugins: [forms],
    safelist: [
        // Ocean theme dynamic border classes used in Alpine :class bindings
        'border-cyan-200/60',
        'border-cyan-200/50',
        'dark:border-cyan-800/40',
        'dark:border-cyan-800/30',
    ],
};
