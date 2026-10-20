import { createApp } from 'vue';
import App from './App.vue';
import { createRevealDirective } from './reveal';
// Public design system (tokens, decorative layers, native-first chrome) shared
// by the landing page and the public catalog/directory/contact screens. Kept
// out of app.css so the signed-in product never pays for it.
import '../../css/landing.css';

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
    images: {
        logo: '/images/brand/logo-mark.png',
        hero: {
            src: '/images/hero/hero-students-800.jpg',
            webpHref: '/images/hero/hero-students-800.webp',
            srcset: '/images/hero/hero-students-480.jpg 480w, /images/hero/hero-students-800.jpg 800w, /images/hero/hero-students-1200.jpg 1200w',
            webpSrcset: '/images/hero/hero-students-480.webp 480w, /images/hero/hero-students-800.webp 800w, /images/hero/hero-students-1200.webp 1200w',
            sizes: '(min-width: 1024px) 512px, (min-width: 640px) 448px, calc(100vw - 2.5rem)',
            width: 1200,
            height: 1500,
            alt: 'Two students holding a graded exam paper and a tablet showing their lesson dashboard',
        },
    },
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
            images: { ...DEFAULT_CONFIG.images, ...(parsed.images ?? {}) },
        };
    } catch (error) {
        console.error('Landing config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('landing-app');

if (mountPoint) {
    createApp(App, { config: readConfig() })
        .directive('reveal', createRevealDirective())
        .mount(mountPoint);
}
