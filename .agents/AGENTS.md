# Studylikepro Workspace Rules & Guidelines

This directory contains workspace customizations. All developer agents working on this workspace must strictly follow these rules:

## UI & Theming System
1. **Dynamic Theme Presets:** Always align new templates, forms, components, and pages with the user's selected theme preset (Classic, Midnight, Sunset, Glass, Ocean, Forest).
2. **Accents:** Never use hardcoded color values (e.g. `bg-indigo-600` or `text-blue-500`) for primary UI actions or markers. Use `bg-primary`, `text-primary`, `border-primary`, etc., to support the user's dynamically selected accent color.
3. **Anti-Flashing Guard:** Do not disturb or bypass the blocking theme script in [app.blade.php](file:///d:/Github/studylikepro/resources/views/layouts/app.blade.php).
4. **Design Reference:** For detailed documentation of presets, variables, AlpineJS preview events, patterns, and style rules, read [DESIGN_SYSTEM_AND_THEMING_GUIDE.md](file:///d:/Github/studylikepro/DESIGN_SYSTEM_AND_THEMING_GUIDE.md).

## Product Context
- Studylikepro is a tutoring marketplace: students upload questions, AI matches a topic, verified teachers deliver paid live 1-on-1 lessons.
- Follow the phased plan in [DOC/implementation-plan.md](file:///d:/Github/studylikepro/DOC/implementation-plan.md); keep scope to the current phase.
- Keep `php artisan test` and `vendor/bin/pint` green; add Pest tests for every behavior change.
