# Studylikepro Design System & Theming Guide

This document serves as the official style guide, design pattern reference, and developer guidelines for the Studylikepro application. Any future enhancements, new UI features, or layout changes must strictly adhere to these patterns to maintain visual cohesion and high aesthetics.

---

## 🎨 Design Philosophy & Principles

The application is built on a **rich, responsive, and dynamic theme system** that gives users complete control over their workspace aesthetics. Key principles include:
1. **Dynamic Accents:** Do not hardcode specific accent colors (like `indigo` or `blue`). Use the dynamic `--primary` CSS variable mapped to Tailwind classes.
2. **Visual Consistency:** Respect the selected **Theme Preset** (Classic, Midnight, Sunset, etc.) and its associated background pattern.
3. **Smooth Transitions:** Enable transitions gracefully without causing page load "flashing".
4. **Glassmorphism & Depth:** Leverage backdrop filters, translucent borders (`border-slate-200/50`), and subtle shadows to create layer hierarchy.

---

## 🛠️ Theming Tokens

### 1. CSS Variable Mapping
The primary accent color is dynamic. Tailwind is configured to read the `--primary` variable as space-separated RGB numbers:
```css
/* resources/css/app.css */
:root {
    --primary: 99 102 241; /* Default Indigo */
}
```
In `tailwind.config.js`, this variable is integrated to support opacity modifiers:
```javascript
colors: {
    primary: 'rgb(var(--primary) / <alpha-value>)',
}
```

**Usage in Blade Views:**
* `bg-primary` for solid accent buttons/badges.
* `bg-primary/10 text-primary` for subtle alerts/highlight states.
* `border-primary` for focused input rings or active borders.
* `shadow-primary/20` for ambient accent shadows.

---

### 2. Theme Presets Matrix
The system supports six distinct presets configured via the database and synchronized to `localStorage`.

| Preset ID | Preset Name | Background / Grid CSS Class | Sidebar Style | Ambient Decorator | Design Style |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`classic`** | Classic | `bg-slate-50 dark:bg-slate-950` | `bg-white dark:bg-slate-900` | None | Clean, professional corporate look |
| **`forest`** | Forest | `bg-slate-50 dark:bg-slate-950` | `bg-white dark:bg-slate-900` | None | Earthy emerald tones |
| **`midnight`**| Midnight | `bg-slate-900 bg-pattern-grid` | `bg-slate-950 text-slate-100` | None | Dark-first aesthetic, glowing lines |
| **`sunset`**  | Sunset | `bg-amber-50/40 bg-pattern-dots` | `bg-white/90` | Gradient top-bar (`amber` to `indigo`) | Warm amber, rose, and gold accents |
| **`glass`**   | Glass | `bg-slate-100 bg-pattern-grid` | `bg-white/70 backdrop-blur-lg`| None | Translucent frosted glassmorphism |
| **`ocean`**   | Ocean | `bg-cyan-50/30 bg-pattern-dots` | `bg-white/80 backdrop-blur-md`| Gradient top-bar (`cyan` to `indigo`) | Cool coastal cyan and blue waves |

---

### 3. Background Patterns
Custom background patterns are defined in [app.css](file:///d:/Github/studylikepro/resources/css/app.css) using CSS radial gradients:
* **Grid Pattern (`.bg-pattern-grid`):** Subtle slate grid lines.
* **Dots Pattern (`.bg-pattern-dots`):** Playful small indigo dots.
* **Waves Pattern (`.bg-pattern-waves`):** Multi-layered cyan/blue/indigo radial gradients.

---

## ⚡ Theming Architecture & Anti-Flashing Guard

To prevent the brief flash of the light/default theme during page reloads, a blocking script is injected directly inside the `<head>` of [app.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/app.blade.php):

```html
<!-- Blocking theme script: prevents flash of light theme on page load -->
<script>
    (function() {
        const mode = localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'light' }}';
        const isDark = mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (isDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        const accent = localStorage.getItem('themeAccent') || '{{ $userTheme['accent'] ?? 'indigo' }}';
        const colors = { indigo: '99 102 241', violet: '139 92 246', emerald: '16 185 129', rose: '244 63 94', amber: '245 158 11', blue: '59 130 246', cyan: '6 182 212' };
        document.documentElement.style.setProperty('--primary', colors[accent] || colors.indigo);

        // Add the correct theme background class immediately
        const preset = localStorage.getItem('themePreset') || '{{ $userTheme['preset'] ?? 'classic' }}';
        let bgClass = 'bg-slate-50';
        if (isDark) {
            bgClass = 'bg-slate-950';
        } else {
            if (preset === 'midnight') bgClass = 'bg-slate-900';
            else if (preset === 'sunset') bgClass = 'bg-amber-50/40';
            else if (preset === 'glass') bgClass = 'bg-slate-100';
            else if (preset === 'ocean') bgClass = 'bg-cyan-50/30';
        }
        document.documentElement.classList.add(bgClass);
    })();
</script>
```

### Logged-out Pages Default to Light
The public entry points — [welcome.blade.php](file:///d:/Github/studylikepro/resources/views/welcome.blade.php), [public-layout.blade.php](file:///d:/Github/studylikepro/resources/views/components/public-layout.blade.php) (catalog, teachers, legal) and [guest.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/guest.blade.php) (login/register) — deliberately do **not** follow the operating system preference. They load the light palette unless the visitor explicitly picked **Dark** in Theme Settings (which is the only value that writes a dark `themeMode` to `localStorage`):

```html
<!-- Blocking theme script: public pages load light unless dark was chosen explicitly -->
<script>
    (function() {
        if (localStorage.getItem('themeMode') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    })();
</script>
```

`system` (or an unset value) resolves to light here, so the marketing, catalog, legal and auth screens always render light by default, while the signed-in shell keeps honouring the stored `light` / `dark` / `system` mode.

### Signed-in Pages Default to Light for New Users
The signed-in shell also defaults to **light** when a user has never saved a theme. [User::getThemeAttribute()](file:///d:/Github/studylikepro/app/Models/User.php) supplies `mode => 'light'` as the default for any missing key, so first-time students and teachers land on the light palette during onboarding regardless of their operating system preference. `system` remains selectable in Theme Settings for users who want to follow their OS.

### Flow Method: AlpineJS Live Preview
Theme adjustments on the settings page update the UI instantly using AlpineJS component state and a custom window event (`theme-changed`):
1. **User interaction:** User clicks a preset/accent in [edit.blade.php](file:///d:/Github/studylikepro/resources/views/settings/edit.blade.php).
2. **Preview function runs:**
   ```javascript
   previewTheme(preset, accent, mode, sidebarStyle) {
       // Dispatch custom event to app.blade.php Alpine listener
       window.dispatchEvent(new CustomEvent('theme-changed', {
           detail: { preset, accent, mode, sidebarStyle }
       }));
       // Cache in localStorage for immediate subpage navigation rendering
       localStorage.setItem('themePreset', preset);
       localStorage.setItem('themeAccent', accent);
       ...
   }
   ```

---

## 🧩 Vue Pages (Vue 3)

The signed-in chrome (sidebar + top bar) and a growing set of pages are rendered by Vue 3 single-file components; every other screen keeps Blade for its markup and Alpine for its page-level interactions. [DOC/vue-conversion-guide.md](DOC/vue-conversion-guide.md) is the step-by-step recipe for converting another screen, using `/student/interests` as the worked reference.

### Signed-in shell (sidebar + top bar)

Every `x-app-layout` screen — student, teacher, admin — wears the same Vue shell, so [layouts/app.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/app.blade.php) is now only the document, the theme bootstrap and the `{{ $slot }}`, and [layouts/navigation.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/navigation.blade.php) only computes the navigation and emits the config island.

* **Entry point:** `resources/js/app-shell/main.js` is its own Vite input, loaded by `layouts/app.blade.php`.
* **Structure:** `AppShell.vue` provides the theme preset and the drawer state, then renders `AppSidebar.vue` (brand, nav list, signed-in user) and `AppTopbar.vue` (hamburger, page heading, `NotificationBell.vue`, `ProfileMenu.vue`). Both dropdowns are `DropdownPanel.vue`, which owns click-outside/Escape and the open/close transition; nav rows are `SidebarNavItem.vue`; icons live in `icons.js` and the preset → class maps in `theme.js`.
* **Server data:** Blade writes a `#studylikepro-shell-config` JSON island (user, `navItems`, notifications, CSRF token, route map) plus a `#studylikepro-page-header` template holding the page's `header` slot. Never hardcode a URL in the shell — read it from `config.urls`. The island is read *before* `mount()`, because mounting clears the container.
* **Layout:** the mount point is `display: contents`, so the sticky top bar keeps the content column as its containing block and the sidebar stays `fixed`; `md:pl-64` on the content column is unchanged.
* **Theme preset:** the head bootstrap script and the Alpine `theme-changed` event stay the source of truth, so `useThemePreset` mirrors them and the chrome restyles live while the user previews a preset in Theme Settings.
* **No-JS fallback:** a `<noscript>` block renders the same `navItems` as plain links.
* **Contract test:** `tests/Feature/AppShellTest.php` asserts the island (the `shellConfig()` helper lives in `tests/Pest.php`).

### Marketing landing page (`/`)

`/` ([welcome.blade.php](file:///d:/Github/studylikepro/resources/views/welcome.blade.php)) is the public entry point.

* **Entry point:** `resources/js/landing/main.js` is a separate Vite input (`vite.config.js`), so Vue is never shipped to the Blade + Alpine screens.
* **Structure:** [App.vue](file:///d:/Github/studylikepro/resources/js/landing/App.vue) composes one component per section under `resources/js/landing/components/` (header, hero, stats, subjects, how-it-works, features, teachers, reviews, FAQ, CTA, footer). Icons live in `resources/js/landing/icons.js` and all copy in `resources/js/landing/content.js` — change wording there, not in the templates.
* **Server data:** Blade renders a `#landing-config` JSON island (`route()` URLs, `auth()->check()`, support details) and mounts the app on `#landing-app`. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **No-JS fallback:** a `<noscript>` block lists the key destinations, and the meta description/Open Graph tags are rendered server-side.
* **Styling rules still apply:** use the dynamic `primary` color, the slate palette, `rounded-2xl`/`rounded-3xl`, translucent borders and `dark:` variants exactly as the Blade pages do. Tailwind scans `resources/js/**/*.{vue,js}`, so classes written inside single-file components are compiled.
* **Theme:** the head bootstrap script is unchanged (light unless `themeMode === 'dark'`). The header toggle writes the same `themeMode` key, so the choice survives navigation to the Blade pages.
* **Motion:** the `v-reveal` directive (registered in `main.js`) fades sections in; it is skipped entirely for `prefers-reduced-motion`.
* **A11y:** sections are labelled, the FAQ uses `aria-expanded`/`aria-controls`, and buttons are real `<button>` elements with `aria-pressed` where they toggle state.

### Student dashboard (`/student/dashboard`)

The signed-in student workspace is a Vue app mounted **inside** the shell above (`x-app-layout`), so it inherits the shared Vue sidebar and top bar.

* **Entry point:** `resources/js/student-dashboard/main.js` is its own Vite input; [dashboard.blade.php](file:///d:/Github/studylikepro/resources/views/student/dashboard.blade.php) calls `@vite(...)` for it, so no other signed-in screen loads the bundle.
* **Server data:** [DashboardController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/DashboardController.php) computes the figures, the next lesson, the review nudge and the route map, then Blade renders them into a `#student-dashboard-config` JSON island and mounts the app on `#student-dashboard-app`. Never hardcode a URL in the Vue code — read it from `config.urls`; quick actions carry a `urlKey` into that map.
* **Structure:** [App.vue](file:///d:/Github/studylikepro/resources/js/student-dashboard/App.vue) composes the banner, stat tiles, next-lesson card, review prompt, quick actions, roadmap and flow panels from `resources/js/student-dashboard/components/`. Static copy lives in `content.js`, the icon set in `icons.js`, and the card surfaces in `styles.js`.
* **Theme preset:** the shell owns the preset, so a `useThemePreset` composable reads `localStorage` / the `theme-changed` event and the app `provide()`s it. Cards resolve their surface through the `usePanel` composable — `glass` and `ocean` get the frosted translucent card, the other presets the opaque one.
* **Motion:** `RevealOnScroll.vue` fades panels in with an `IntersectionObserver` and stat values ease up through a `useCountUp` composable; both are skipped for `prefers-reduced-motion`.
* **A11y:** sections are labelled, status badges are text (not colour-only), and every card is a real link.
* **Contract test:** `tests/Feature/Student/StudentDashboardTest.php` asserts the island the Vue app depends on.

### Student subject pages (`/student/interests`, `/student/subjects/{slug}`)

"My subjects" and the student subject page are the reference implementation for a conversion — the
full walkthrough lives in [DOC/vue-conversion-guide.md](DOC/vue-conversion-guide.md).

* **Entry point:** `resources/js/student-subjects/main.js` is one Vite input serving both pages; each Blade view calls `@vite(...)` and only the page's own mount point boots.
* **Server data:** [InterestsController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/InterestsController.php) and the subject controller keep every permission check, grouping and price format; Blade then renders the `#student-interests-config` / `#student-subject-config` JSON islands and mounts on `#student-interests-app` / `#student-subject-app`. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [InterestsApp.vue](file:///d:/Github/studylikepro/resources/js/student-subjects/InterestsApp.vue) and [SubjectApp.vue](file:///d:/Github/studylikepro/resources/js/student-subjects/SubjectApp.vue) compose the cards in `resources/js/student-subjects/components/` and reuse [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiChip`, `UiEmptyState`, `UiSectionHeading`, `UiBreadcrumbs`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) rather than shipping their own icon/style copies. Blade copy and the CSRF token are `provide()`d once through `composables/usePageContext.js`, so cards `inject()` them instead of prop-drilling.
* **Actions:** the subject page proves the interactive case — the payload decides *which* action a service card gets, and Vue renders a real `<form>` carrying `csrfToken`, so CSRF, validation errors and redirects all stay server-side.
* **No-JS fallback:** both views render the same subjects/lessons as a `<noscript>` list.
* **Contract tests:** `tests/Feature/Student/InterestsTest.php` and `tests/Feature/Student/BasketSubjectsTest.php` cover the behaviour; add an island assertion in the style of `StudentDashboardTest.php` when a payload changes.

### Student request pages (`/student/requests`, `/student/requests/create`)

The request list and the "Ask a question" form share one bundle, mounted inside the shell like the pages above.

* **Entry point:** `resources/js/student-requests/main.js` is one Vite input serving both pages; each Blade view calls `@vite(...)` and only the page's own mount point boots (`#student-requests-app`, `#student-request-form-app`).
* **Server data:** [TutoringRequestController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/TutoringRequestController.php) keeps every permission check, the grade lock and the timezone formatting; Blade then renders the `#student-requests-config` / `#student-request-form-config` JSON islands. The list island carries the cards, the three status buckets and the summary figures (counted over the student's whole history, not just the page); the form island carries the limits, the old input, the CSRF token and the validation messages keyed by the field that failed.
* **Structure:** [RequestsApp.vue](file:///d:/Github/studylikepro/resources/js/student-requests/RequestsApp.vue) composes the overview, the filter bar, the cards and the empty state; [CreateRequestApp.vue](file:///d:/Github/studylikepro/resources/js/student-requests/CreateRequestApp.vue) composes the question, window, photo and budget cards. Both reuse [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) and `composables/usePageContext.js` for the copy, the route map and the CSRF token instead of shipping their own copies.
* **Interactivity that stays client-side:** the status buckets filter the requests already on the page, the form owns the repeatable windows (add/remove, max 5, min 1, contiguous `windows[n][…]` names), the photo dropzone trims a selection to `maxAttachments` and shows thumbnails, and the question counter warns below the minimum length. Laravel still validates every one of them.
* **Actions:** the form is a real `method="POST"` form to `student.requests.store` carrying the island's CSRF token, so CSRF, validation errors, redirects and the queued AI classification all stay server-side.
* **No-JS fallback:** both views render a `<noscript>` version — a plain list, and a plain form with the same field names, limits and attributes.
* **Contract tests:** `tests/Feature/Student/RequestsIndexTest.php` and `tests/Feature/Student/RequestsCreateTest.php` assert the islands the two apps depend on.

### Learning profile (`/student/profile`)

The learning profile is where a student changes the answers the onboarding wizard collected: the grade that scopes every lesson, teacher and price, the language lessons are taught in, the goals a teacher reads before the first lesson and the avatar. Only the markup moved — the controller still sends incomplete profiles back into the wizard, and `StudentProfileRequest` still owns every rule.

* **Entry point:** `resources/js/student-profile/main.js` is its own Vite input; [profile.blade.php](file:///d:/Github/studylikepro/resources/views/student/profile.blade.php) calls `@vite(...)` for it, so no other screen pays for this page's code.
* **Server data:** [ProfileController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/ProfileController.php) keeps the completeness redirect and the catalog lookup; Blade then renders the `#student-profile-config` JSON island and mounts the app on `#student-profile-app`. The island carries the wizard's level → grade tree, the marketplace student languages with their flags, the student's own answers (replayed from `old()` after a rejected save), the per-field validation messages, the limits `StudentProfileRequest` enforces and the route map. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [StudentProfileApp.vue](file:///d:/Github/studylikepro/resources/js/student-profile/StudentProfileApp.vue) provides the answers and opens the one real form; the cards in `resources/js/student-profile/components/` render one block each — `ProfileHeroCard` (identity, grade/language chips and the strength meter), `AboutYouCard` (grade select, language picker, goals with its live counter), `GuardianCard`, `PhotoCard` (a live preview of the picked file) and the sticky `ProfileStrengthCard` + `ProfileSaveBar` in the sidebar. It reuses [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiChip`, `UiAlert`, `UiSectionHeading`, `UiFormField`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy, the route map and the CSRF token.
* **One form, many cards:** the answers, the picked photo and the errors live in `composables/useProfileForm.js` (`createProfileForm`), which the app `provide()`s under `PROFILE_FORM_KEY` and every card `inject()`s — so no card prop-drills `v-model` targets, and the “unsaved changes” hint, the strength meter and the file reset all read the same state.
* **Interactivity that stays server-side:** the form is a real `method="POST"` form carrying `_method=PUT`, the island's CSRF token and `enctype="multipart/form-data"`, so CSRF, validation, the avatar upload and the redirect all stay server-side; a rejected save reopens the page with the answers it sent and one message per field.
* **Strength meter:** it counts exactly the fields the form holds (grade, language, goals, guardian, photo) and can never claim more than the server has; `copy.progressLabel` keeps its `:percent` placeholder for the one number Blade cannot know.
* **No-JS fallback:** the view renders the same PUT form as plain Blade — the same field names, optgroups and validation output.
* **Contract test:** `tests/Feature/Student/StudentProfileTest.php` asserts the island the Vue app depends on.

### Student lessons (`/student/lessons`)

"My lessons" is the student's booking list: the upcoming/past tabs, the history filters and the lesson cards. Filtering, paging and the tab switch stay server-side, so this conversion only moves the markup — not the query.

* **Entry point:** `resources/js/student-lessons/main.js` is its own Vite input; [bookings/index.blade.php](file:///d:/Github/studylikepro/resources/views/student/bookings/index.blade.php) calls `@vite(...)` for it, so the other booking screens keep their bundles.
* **Server data:** [BookingController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/BookingController.php) keeps the request validation, the upcoming/past scopes, the eager loading and the timezone/money formatting; Blade then renders the `#student-lessons-config` JSON island and mounts the app on `#student-lessons-app`. The island carries both tabs (each URL keeping the filters already applied), the filter options taken from the student's own history, the pre-formatted lesson cards (status, pay-by countdown, duration, join-now, review nudge, time range and total) and the route map. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [App.vue](file:///d:/Github/studylikepro/resources/js/student-lessons/App.vue) composes the tabs, the filter panel, the cards and the empty state from `resources/js/student-lessons/components/`, reusing [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiEmptyState`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy and the route map.
* **Interactivity that stays server-side:** the tabs are real links and the filter panel is a real `method="GET"` form carrying the island's `tab`, so Laravel still validates `status`, `subject`, `teacher`, `from` and `to`.
* **Pagination:** `$bookings->links()` stays server-rendered below the island.
* **No-JS fallback:** the view renders a `<noscript>` list of the same lessons — or the same empty state — plus the teacher directory link.
* **Contract test:** `tests/Feature/Student/LessonsIndexTest.php` asserts the island the Vue app depends on.

### Teacher finder (`/student/find-a-teacher`)

The grade-matched teacher finder: the grade panel, the subject/language/date filters, one card per teacher and the empty state. Only the markup moved — the profile grade lock, the slot maths, the sorting and the paging all stay server-side.

* **Entry point:** `resources/js/student-teacher-finder/main.js` is its own Vite input; [find-teacher.blade.php](file:///d:/Github/studylikepro/resources/views/student/find-teacher.blade.php) calls `@vite(...)` for it, so the public directory and the dedicated teacher page keep the rest of the shell's bundles.
* **Server data:** [TeacherFinderController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/TeacherFinderController.php) keeps the `StudentTeacherSearchRequest` validation, the profile-grade scope, the availability ordering and every money and timezone format; Blade then renders the `#student-teacher-finder-config` JSON island and mounts the app on `#student-teacher-finder-app`. The island carries the grade (label only — the query string can never override it), the filter values and their pre-labelled options, the day/time-availability flag, the validation message, the total with its `trans_choice` sentence and the pre-formatted cards. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [App.vue](file:///d:/Github/studylikepro/resources/js/student-teacher-finder/App.vue) composes the grade panel (`GradeMatchBanner.vue`), the filter panel (`TeacherFinderFilters.vue`), the cards (`TeacherCard.vue`) and the empty state (`FinderEmptyState.vue`) from `resources/js/student-teacher-finder/components/`, reusing [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiChip`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy and the route map.
* **Card fields:** a card is the same shape the subject page's teacher grid uses (`rating`/`ratingCount`, `metaLabel`, `rate`, `availability`) so the two never disagree on how a teacher is described; a teacher with no rating shows the "new teacher" chip, and the availability line is decided by Blade (`Available :when` on a picked day, `Next: :when` otherwise, or the no-slots sentence).
* **Theme preset:** the old page bound Alpine's global `themePreset` through `:class`; the Vue card now injects the preset through `usePanel()`, so the opaque/frosted surface switch matches every other converted page.
* **Interactivity that stays server-side:** the filter panel is a real `method="GET"` form to `student.teachers.index`, so Laravel still validates `subject`, `language`, `sort`, `date`, `time_from` and `time_to`, and each card is a real link to `student.teachers.show`.
* **Pagination:** `$teachers->links()` stays server-rendered below the island.
* **No-JS fallback:** the view renders a `<noscript>` copy of the same grade panel, a plain GET form with the same field names and a plain list of the same teachers.
* **Contract test:** `tests/Feature/Student/StudentTeacherFinderTest.php` asserts the island the Vue app depends on (alongside the pre-existing behaviour tests).

### Student purchases (`/student/purchases`)

"My purchases" is the student's service wallet: the summary figures, the Active/Past tabs, the service-type filter and one card per purchase. Only the markup moved — the query, the validation and the paging all stay server-side.

* **Entry point:** `resources/js/student-purchases/main.js` is its own Vite input; [purchases/index.blade.php](file:///d:/Github/studylikepro/resources/views/student/purchases/index.blade.php) calls `@vite(...)` for it, so the checkout and session screens keep their bundles.
* **Server data:** [PurchaseController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/PurchaseController.php) keeps the tab/type validation, the status scopes and every money format; Blade then renders the `#student-purchases-config` JSON island and mounts the app on `#student-purchases-app`. The island carries both tabs (each URL keeping the service-type filter), the service-type chips, the three wallet figures and the pre-formatted cards — type, status, teacher, purchase date, remaining balance, progress, delivery state, expiry and the single action each card offers. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [PurchasesApp.vue](file:///d:/Github/studylikepro/resources/js/student-purchases/PurchasesApp.vue) composes the overview tiles, the filter bar, the cards and the empty state from `resources/js/student-purchases/components/`, reusing [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiChip`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy, the route map and the CSRF token.
* **Full width:** the page opens on `w-full` and the cards flow into `sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4`, so the wallet uses the whole workspace column instead of a centred one. The reveal wrapper is `h-full` and the card's footer is `mt-auto`, which keeps every price and action on the same line across a row.
* **Interactivity that stays server-side:** the tabs and the service-type chips are real links that carry the other filters already applied, and the one purchase that still needs paying posts a real `method="POST"` form to `student.purchases.checkout` with the island's CSRF token. Which action a card gets is decided by Blade (`Gate::allows('scheduleSession', …)`, the marking order's status, the summary's delivery), so the Vue app never re-derives a permission.
* **Payment hold:** a hold is only actionable while its window is open. The controller buckets `scopeHoldOpen` into Active and `scopeHoldLapsed` into Past, and Blade renders a lapsed row as `Expired` — the status the scheduler writes minutes later — because `PurchaseService::startCheckout()` refuses to pay for it. So the list never offers a payment that can only fail. A live hold carries its deadline as an instant (`payBy.deadline`) instead of a pre-rendered sentence, and [PaymentCountdown.vue](file:///d:/Github/studylikepro/resources/js/student-purchases/components/PaymentCountdown.vue) counts it down every second: amber in hours and minutes, red and pulsing under five minutes, and `Hold expired` at zero — the moment the card switches to `expiredStatus`, drops its balance and swaps the pay form for a `Refresh` button, so the page can be left open across a deadline without going stale. Copy lives in the island (`copy.payWithin` keeps its `:time` placeholder for the component to fill), and the pulse is `motion-reduce:animate-none`.
* **Pagination:** `$purchases->links()` stays server-rendered below the island.
* **No-JS fallback:** the view renders a `<noscript>` list of the same purchases — or the same empty state — plus the teacher directory link.
* **Contract test:** `tests/Feature/Student/PurchasesIndexTest.php` asserts the island the Vue app depends on.

### Purchase detail (`/student/purchases/{purchase}`)

One purchase in full: where it stands, what is left of it, whatever the teacher has delivered and the sessions booked against it. Only the markup moved — the authorization, the query and every money and date format stay server-side.

* **Entry point:** `resources/js/student-purchase-show/main.js` is its own Vite input; [purchases/show.blade.php](file:///d:/Github/studylikepro/resources/views/student/purchases/show.blade.php) calls `@vite(...)` for it, so neither the wallet nor the checkout screens pay for this page's code.
* **Server data:** [PurchaseController](file:///d:/Github/studylikepro/app/Http/Controllers/Student/PurchaseController.php) keeps the `Gate::authorize('view', …)` check and the eager loading; Blade then renders the `#student-purchase-show-config` JSON island and mounts the app on `#student-purchase-show-app`. The island carries the status with its tone, the window the purchase is valid for, the price and its fee breakdown, the payment that captured it, the plan balance and its progress, the delivery card, the pre-formatted sessions and the actions the student may take. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [PurchaseShowApp.vue](file:///d:/Github/studylikepro/resources/js/student-purchase-show/PurchaseShowApp.vue) composes the alert, the status panel, the plan balance, the delivery card and the sessions list from `resources/js/student-purchase-show/components/`, reusing [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiChip`, `UiAlert`, `PaymentCountdown`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy, the route map and the CSRF token.
* **Full width:** the page opens on `w-full` and the cards span the workspace column, matching the wallet and lesson screens instead of the centred `max-w-4xl` this page used to open on.
* **Status tones:** a status carries its Tailwind classes in the PHP enum, which sits outside Tailwind's scanned paths, so the island carries a semantic chip tone (`primary`, `amber`, `emerald`, `sky`, `rose`, `slate`) and `UiChip`/`styles.js` own the colours.
* **Interactivity that stays server-side:** the pay and cancel buttons are real `method="POST"` forms carrying the island's CSRF token, and the plan's booking link is rendered only when `Gate::allows('scheduleSession', …)` and a remaining balance both allow it.
* **Payment hold:** a lapsed hold reads as `Expired` here exactly as it does on the wallet list, because `PurchaseService::startCheckout()` refuses to pay for it. A live hold carries its deadline as an instant, so [PaymentCountdown.vue](file:///d:/Github/studylikepro/resources/js/shared/components/PaymentCountdown.vue) — shared with the wallet card, which ticks the same clock — counts it down, and the panel swaps to `Expired`, dropping the form for a refresh, if the page outlives the window.
* **No-JS fallback:** the view renders a `<noscript>` copy of the same status, plan, delivery and session blocks.
* **Contract test:** `tests/Feature/Student/PurchasesShowTest.php` asserts the island the Vue app depends on.

### Lesson detail (`/student/lessons/{booking}`)

[bookings/show.blade.php](file:///d:/Github/studylikepro/resources/views/student/bookings/show.blade.php) is the one booking screen that stays Blade + Alpine. It is the reference for the full-width, in-page-navigation layout:

* **Full width:** the page opens on `w-full` instead of `mx-auto max-w-<size>`, then splits into `xl:grid-cols-[15rem_minmax(0,1fr)]` (widening the rail to `16rem` at `2xl`). Every card sits in the second column, so the lesson content uses the whole workspace.
* **Rail:** the first grid column holds a `sticky top-24` card of section links. Because it is a stretched grid item, it travels the full height of the cards beside it. Below `xl` the rail would squeeze the cards, so it is hidden there and the same links render as sticky chips under the top bar (`sticky top-16`).
* **Sections:** `$sections` is built in the view from the cards the booking actually renders (the review entry is added only for a completed lesson), and each card carries its own `id` plus `scroll-mt-32 xl:scroll-mt-24` so native anchor jumps clear the sticky chrome.
* **Alpine contract:** one `x-data` on the page wrapper owns `ids`, `active`, the scroll listener and `go()`. `sync()` marks the last section whose top passed the offset line and pins the final section at the bottom of the page; `go()` holds the highlight for 900ms while the smooth scroll runs and skips motion for `prefers-reduced-motion`. The section links are real `href="#…"` anchors that also carry `scroll-margin`, so the navigation still works without JavaScript.

### Lesson chat inbox (`/messages`)

The inbox is one screen shared by both sides: the perspective follows the signed-in user, who must be the student or the teacher of the lesson. Only the markup moved — the scoping, the unread counts and the paging all stay server-side.

* **Entry point:** `resources/js/messages/main.js` is its own Vite input; [messages/index.blade.php](file:///d:/Github/studylikepro/resources/views/messages/index.blade.php) calls `@vite(...)` for it, so the thread ([messages-thread](file:///d:/Github/studylikepro/resources/js/messages-thread)) and every other screen keep their bundles.
* **Server data:** [ConversationController](file:///d:/Github/studylikepro/app/Http/Controllers/ConversationController.php) keeps the `forUser`/`latestFirst` scoping, the eager loading, the unread counts and the relative timestamps; Blade then renders the `#messages-config` JSON island and mounts the app on `#messages-app`. The island carries the total unread summary, the pre-formatted threads (counterpart name and initials, unread count and its screen-reader label, relative time, lesson title and start time, and the one-line preview with its `previewSystem`/`previewEmpty` flags) and the route map. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [App.vue](file:///d:/Github/studylikepro/resources/js/messages/App.vue) composes the unread banner (`UnreadBanner.vue`), the thread cards (`ConversationCard.vue`) and the empty state (`ConversationsEmptyState.vue`) from `resources/js/messages/components/`, reusing [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy and the route map.
* **Interactivity that stays server-side:** every card is a real link to `messages.show` and `$conversations->links()` stays server-rendered below the island, so opening a thread and paging are plain page loads.
* **Empty state:** the teacher and the student get different copy and a different call to action (Blade picks `teacher.schedule.index` or `student.bookings.index`).
* **No-JS fallback:** the view renders a `<noscript>` list of the same threads — or the same empty state — plus the pagination.
* **Contract test:** `tests/Feature/Chat/MessagesIndexTest.php` asserts the island the Vue app depends on.

### Lesson thread (`/messages/{conversation}`)

One conversation: the messages, the composer, the poller that catches the other side's replies and the report dialog. Only the markup moved — the authorization, the 50-message window and the payload every endpoint returns stay server-side.

* **Entry point:** `resources/js/messages-thread/main.js` is its own Vite input; [messages/show.blade.php](file:///d:/Github/studylikepro/resources/views/messages/show.blade.php) calls `@vite(...)` for it, so the inbox and every other screen keep their bundles.
* **Server data:** [ConversationController](file:///d:/Github/studylikepro/app/Http/Controllers/ConversationController.php) keeps the `Gate::authorize('view', …)` check, the 50-message page (`latest('id')` for the window, then read oldest-to-newest) and the read marker; Blade then renders the `#messages-thread-config` JSON island and mounts the app on `#messages-thread-app`. The island carries the counterpart and initials, the pre-formatted lesson line with its booking URL, the first page of messages **in `Message::toThreadPayload()` shape**, the report form's reasons, its `old()` values and its field errors, and the route map. Never hardcode a URL in the Vue code — read it from `config.urls`.
* **Structure:** [App.vue](file:///d:/Github/studylikepro/resources/js/messages-thread/App.vue) composes the flash alert, the lesson/report toolbar, the thread panel and the report dialog from `resources/js/messages-thread/components/`, reusing [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) (`AppIcon`, `UiAlert`, `RevealOnScroll`, `usePanel`, `useThemePreset`, `styles.js`) and `composables/usePageContext.js` for the copy, the route map, the CSRF token and the thread state.
* **One payload, no second template:** the island's messages are the same shape the store and poll endpoints return, so a message appended by `useThread()` is indistinguishable from one the server rendered — there is no second bubble template to keep in step (the Alpine version cloned three `<template>` blocks).
* **Interactivity that stays server-side:** reporting is a real `method="POST"` form carrying the island's CSRF token, so a rejected report bounces back with its `old()` values and the dialog reopens already populated; `Dispute::REASONS` is the only source of the options.
* **Composer:** Enter sends and Shift + Enter keeps the newline; the draft and the picked photo are only cleared once the message actually went out.
* **Polling:** every five seconds after the highest rendered id, skipped while the tab is hidden.
* **No-JS fallback:** the view renders the same thread read-only from the `messages.partials.bubble` partial, plus a plain report form.
* **Contract test:** `tests/Feature/Chat/MessagesThreadTest.php` asserts the island the Vue app depends on.

3. **Database update:** Form submission saves selection permanently.

---

## 📋 Developer & AI Guideline Checklist

When writing new views, editing components, or extending layouts, you **MUST** follow these practices:

### 1. Layout & Styling Rules
* **Backgrounds:** Do not hardcode `<div class="bg-slate-50">` for page content containers. Use dynamic Alpine class bindings to match `themePreset` or use standard transparent patterns.
* **Borders:** Use translucent colors for borders (e.g. `border-slate-200/80` or `dark:border-slate-800/80`). Under glass/ocean presets, apply thinner translucent borders (`border-slate-200/40 dark:border-slate-800/40`).
* **Icons:** Pair icons with theme-specific background rings or dynamic accent background colors:
  ```html
  <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-600 transition-colors group-hover:bg-primary group-hover:text-white dark:bg-violet-900/30 dark:text-violet-400">
      <!-- Icon SVG here -->
  </div>
  ```

### 2. High-Aesthetic UI Elements (Requirements Checklist)
* **Smooth Transitions:** Enable transition states via AlpineJS: `:class="{'transition-colors duration-300': themeTransitionEnabled}"`. Vue pages do the same through `usePanel()`/`styles.js` class bindings.
* **Rounded Corners:** Prefer `rounded-2xl` for cards/panels and `rounded-xl` for buttons/input elements.
* **Interactive hover effects:** Anchor hover interactions with scaling or slight accent translation changes (e.g., `hover:border-primary/40 hover:shadow-lg group-hover:translate-x-0.5`).
* **Tailwind class detection:** never build a class name from a variable inside a Vue component (`bg-${color}`); Tailwind only sees complete class strings in the source. Semantic status colours that live in a PHP enum are therefore mapped again in the component (see `NextLessonCard.vue`).

---

## 🗂️ Key Files Reference
* **Tailwind Config:** [tailwind.config.js](file:///d:/Github/studylikepro/tailwind.config.js)
* **Custom Patterns CSS:** [resources/css/app.css](file:///d:/Github/studylikepro/resources/css/app.css)
* **Master Layout File:** [resources/views/layouts/app.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/app.blade.php)
* **Sidebar Template:** [resources/views/layouts/navigation.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/navigation.blade.php)
* **Vue Shell Components:** [resources/js/app-shell/](file:///d:/Github/studylikepro/resources/js/app-shell)
* **Theme Configuration view:** [resources/views/settings/edit.blade.php](file:///d:/Github/studylikepro/resources/views/settings/edit.blade.php)
* **Vue Entries:** [resources/js/app-shell/main.js](file:///d:/Github/studylikepro/resources/js/app-shell/main.js), [resources/js/landing/main.js](file:///d:/Github/studylikepro/resources/js/landing/main.js), [resources/js/student-dashboard/main.js](file:///d:/Github/studylikepro/resources/js/student-dashboard/main.js), [resources/js/student-subjects/main.js](file:///d:/Github/studylikepro/resources/js/student-subjects/main.js), [resources/js/student-requests/main.js](file:///d:/Github/studylikepro/resources/js/student-requests/main.js), [resources/js/student-lessons/main.js](file:///d:/Github/studylikepro/resources/js/student-lessons/main.js), [resources/js/student-teacher-finder/main.js](file:///d:/Github/studylikepro/resources/js/student-teacher-finder/main.js), [resources/js/student-purchases/main.js](file:///d:/Github/studylikepro/resources/js/student-purchases/main.js), [resources/js/student-purchase-show/main.js](file:///d:/Github/studylikepro/resources/js/student-purchase-show/main.js), [resources/js/student-profile/main.js](file:///d:/Github/studylikepro/resources/js/student-profile/main.js), [resources/js/messages/main.js](file:///d:/Github/studylikepro/resources/js/messages/main.js)
* **Vue Shared Kit:** [resources/js/shared/](file:///d:/Github/studylikepro/resources/js/shared) — reuse these components, composables and class strings in every new page instead of shipping per-page copies. `UiFormField` is the shared form row (label, hint, slot, Laravel's messages).
* **Vue Page Bundles:** [resources/js/student-dashboard/](file:///d:/Github/studylikepro/resources/js/student-dashboard), [resources/js/student-subjects/](file:///d:/Github/studylikepro/resources/js/student-subjects), [resources/js/student-requests/](file:///d:/Github/studylikepro/resources/js/student-requests), [resources/js/student-lessons/](file:///d:/Github/studylikepro/resources/js/student-lessons), [resources/js/student-teacher-finder/](file:///d:/Github/studylikepro/resources/js/student-teacher-finder), [resources/js/student-purchases/](file:///d:/Github/studylikepro/resources/js/student-purchases), [resources/js/student-purchase-show/](file:///d:/Github/studylikepro/resources/js/student-purchase-show), [resources/js/student-profile/](file:///d:/Github/studylikepro/resources/js/student-profile), [resources/js/messages/](file:///d:/Github/studylikepro/resources/js/messages)
* **Conversion Guide:** [DOC/vue-conversion-guide.md](DOC/vue-conversion-guide.md)
