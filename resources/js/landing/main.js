import { createApp } from 'vue';
import App from './App.vue';

// Blade renders the route map and auth state into a JSON island so the Vue app
// never hardcodes a URL that could drift from routes/web.php.
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    authenticated: false,
    urls: {
        subjects: '/subjects',
        teachers: '/teachers',
        login: '/login',
        registerStudent: '/register?role=student',
        dashboard: '/dashboard',
        contact: '/contact',
        privacy: '/privacy',
        terms: '/terms',
        refunds: '/refund-policy',
        home: '/',
    },
    support: {},
};

function readConfig() {
    const island = document.getElementById('landing-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Landing config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Fades and lifts sections into view on first scroll. Anything animated is opt
// in: without JavaScript, or with reduced motion, the content is simply visible.
const reveal = {
    mounted(element, binding) {
        if (prefersReducedMotion) {
            return;
        }

        element.classList.add('lp-reveal');

        if (binding.value) {
            element.style.transitionDelay = `${binding.value}ms`;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        element.classList.add('lp-reveal--visible');
                        observer.disconnect();
                    }
                });
            },
            { rootMargin: '0px 0px -10% 0px', threshold: 0.05 },
        );

        observer.observe(element);
        element.lpRevealObserver = observer;
    },
    unmounted(element) {
        element.lpRevealObserver?.disconnect();
    },
};

const mountPoint = document.getElementById('landing-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).directive('reveal', reveal).mount(mountPoint);
}
