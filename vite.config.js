import { cpSync, existsSync, mkdirSync, readFileSync, readdirSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

/**
 * pdf.js needs two things that are not ordinary imports: its worker and the wasm
 * modules that rasterise scanned pages (CCITT, JBIG2 and JPEG-2000). Both are
 * copied to public/vendor/pdfjs for the dev server and emitted into the build
 * output as assets/pdfjs-<version>/ for production, so a release carries them
 * inside the build directory rather than depending on `public/` being deployed
 * whole. See usePaperDocument.js for the URLs.
 *
 * The worker is emitted as `.js` on purpose: Apache/LiteSpeed serve `.mjs` as
 * `text/plain`, and a browser refuses to start that as a module worker, which is
 * what "Setting up fake worker failed" means.
 */
function pdfjsAssets() {
    const dist = fileURLToPath(new URL('node_modules/pdfjs-dist', import.meta.url));
    const wasmSource = `${dist}/wasm`;
    const workerSource = `${dist}/build/pdf.worker.min.mjs`;
    const devTarget = fileURLToPath(new URL('public/vendor/pdfjs', import.meta.url));

    let version = 'unknown';

    try {
        version = JSON.parse(readFileSync(`${dist}/package.json`, 'utf8')).version;
    } catch {
        // Left as-is: buildStart warns about the missing package.
    }

    const assetDir = `assets/pdfjs-${version}`;

    return {
        name: 'studylikepro:pdfjs-assets',
        buildStart() {
            if (!existsSync(wasmSource) || !existsSync(workerSource)) {
                this.warn('pdfjs-dist worker or wasm files are missing — run npm install.');

                return;
            }

            mkdirSync(`${devTarget}/wasm`, { recursive: true });
            cpSync(wasmSource, `${devTarget}/wasm`, { recursive: true });
            cpSync(workerSource, `${devTarget}/pdf.worker.min.js`);
        },
        generateBundle() {
            if (!existsSync(wasmSource) || !existsSync(workerSource)) {
                return;
            }

            this.emitFile({
                type: 'asset',
                fileName: `${assetDir}/pdf.worker.min.js`,
                source: readFileSync(workerSource),
            });

            readdirSync(wasmSource, { withFileTypes: true })
                .filter((entry) => entry.isFile())
                .forEach((entry) => {
                    this.emitFile({
                        type: 'asset',
                        fileName: `${assetDir}/wasm/${entry.name}`,
                        source: readFileSync(`${wasmSource}/${entry.name}`),
                    });
                });
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                // The marketing landing page is its own Vue entry, so the rest of
                // the app keeps shipping plain Blade + Alpine without Vue.
                'resources/js/landing/main.js',
                // The public catalog, teacher directory and contact screens share
                // one entry and the landing page's design tokens.
                'resources/js/public/main.js',
                // The student dashboard is the signed-in shell's Vue page; its
                // bundle stays out of every other Blade + Alpine screen.
                'resources/js/student-dashboard/main.js',
                // "My subjects" and the student subject page share one Vue
                // entry: whichever mount point the page renders boots.
                'resources/js/student-subjects/main.js',
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
                // The teacher's profile at /teacher/profile, with the live
                // preview of their public directory card.
                'resources/js/teacher-profile/main.js',
                // The availability manager at /teacher/availability: the week
                // editor plus the default price, the bookable preview and the
                // time off.
                'resources/js/teacher-availability/main.js',
                // The application page at /teacher/verification: the documents,
                // the live checklist and the submit confirmation.
                'resources/js/teacher-verification/main.js',
                // The teacher's subject setup at /teacher/subjects: the grade and
                // lesson scope, the per-grade rates, the request queue and the
                // subject proposal form.
                'resources/js/teacher-subjects/main.js',
                // The services catalog at /teacher/services: the tabbed list,
                // the type picker and the composer that creates or edits an
                // offering.
                'resources/js/teacher-services/main.js',
                // The teacher's order book at /teacher/orders and one order in
                // detail: the status rail, the money breakdown, the plan balance
                // and the sessions booked against the order.
                'resources/js/teacher-orders/main.js',
                'resources/js/teacher-order-show/main.js',
                // The teaching schedule at /teacher/schedule: what is next, the
                // ranked "do next" queue and the day-grouped agenda.
                'resources/js/teacher-schedule/main.js',
                // One lesson at /teacher/schedule/{booking}: the next-step
                // answer, the lesson flow and the money/timeline paperwork.
                'resources/js/teacher-booking-show/main.js',
                // What students said at /teacher/reviews: the rating summary,
                // the star distribution, the comments and the report dialog.
                'resources/js/teacher-reviews/main.js',
                // The teacher's money at /teacher/earnings: the totals, the
                // six-month trend, the payouts and the filterable ledger.
                'resources/js/teacher-earnings/main.js',
                // The paper-marking inbox at /teacher/marking and one order at
                // /teacher/marking/{order}: what is waiting, what is overdue and
                // the delivery form that returns the marked copy.
                'resources/js/teacher-marking/main.js',
                'resources/js/teacher-marking-show/main.js',
                // The signed-in chrome (sidebar + top bar) that wraps every
                // x-app-layout screen, so the layout itself is a Vue component.
                'resources/js/app-shell/main.js',
                // The lesson-chat inbox at /messages; its bundle stays out of
                // the thread itself and every other Blade + Alpine screen.
                'resources/js/messages/main.js',
                // One lesson thread at /messages/{conversation}, with the
                // composer, the poller and the report dialog.
                'resources/js/messages-thread/main.js',
                // The Settings section: the home cards + email toggle, the theme
                // picker and the admin platform console each ship their own
                // bundle so no screen pays for another's pickers.
                'resources/js/settings-home/main.js',
                'resources/js/settings-theme/main.js',
                'resources/js/admin-settings/main.js',
                // The account pages the Settings cards link to: the profile
                // details/password/danger zone and the notification centre.
                'resources/js/profile/main.js',
                'resources/js/notifications/main.js',
            ],
            refresh: true,
        }),
        vue({
            template: {
                // Blade handles asset URLs; leave them untouched in single-file components.
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
        pdfjsAssets(),
    ],
});
