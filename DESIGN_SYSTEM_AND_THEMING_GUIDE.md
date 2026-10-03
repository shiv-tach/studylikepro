# POS Application Design System & Theming Guide

This document serves as the official style guide, design pattern reference, and developer guidelines for the POS application. Any future enhancements, new UI features, or layout changes must strictly adhere to these patterns to maintain visual cohesion and high aesthetics.

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
Custom background patterns are defined in [app.css](file:///d:/POS/resources/css/app.css) using CSS radial gradients:
* **Grid Pattern (`.bg-pattern-grid`):** Subtle slate grid lines.
* **Dots Pattern (`.bg-pattern-dots`):** Playful small indigo dots.
* **Waves Pattern (`.bg-pattern-waves`):** Multi-layered cyan/blue/indigo radial gradients.

---

## ⚡ Theming Architecture & Anti-Flashing Guard

To prevent the brief flash of the light/default theme during page reloads, a blocking script is injected directly inside the `<head>` of [app.blade.php](file:///d:/POS/resources/views/layouts/app.blade.php):

```html
<!-- Blocking theme script: prevents flash of light theme on page load -->
<script>
    (function() {
        const mode = localStorage.getItem('themeMode') || '{{ $userTheme['mode'] ?? 'system' }}';
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

### Flow Method: AlpineJS Live Preview
Theme adjustments on the settings page update the UI instantly using AlpineJS component state and a custom window event (`theme-changed`):
1. **User interaction:** User clicks a preset/accent in [edit.blade.php](file:///d:/POS/resources/views/settings/edit.blade.php).
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
* **Smooth Transitions:** Enable transition states via AlpineJS: `:class="{'transition-colors duration-300': themeTransitionEnabled}"`.
* **Rounded Corners:** Prefer `rounded-2xl` for cards/panels and `rounded-xl` for buttons/input elements.
* **Interactive hover effects:** Anchor hover interactions with scaling or slight accent translation changes (e.g., `hover:border-primary/40 hover:shadow-lg group-hover:translate-x-0.5`).

---

## 🗂️ Key Files Reference
* **Tailwind Config:** [tailwind.config.js](file:///d:/POS/tailwind.config.js)
* **Custom Patterns CSS:** [resources/css/app.css](file:///d:/POS/resources/css/app.css)
* **Master Layout File:** [resources/views/layouts/app.blade.php](file:///d:/POS/resources/views/layouts/app.blade.php)
* **Sidebar Template:** [resources/views/layouts/navigation.blade.php](file:///d:/POS/resources/views/layouts/navigation.blade.php)
* **Theme Configuration view:** [resources/views/settings/edit.blade.php](file:///d:/POS/resources/views/settings/edit.blade.php)
