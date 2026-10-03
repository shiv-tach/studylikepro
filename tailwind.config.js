import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: 'rgb(var(--primary) / <alpha-value>)',
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
