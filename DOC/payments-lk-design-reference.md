# Payments.lk — Design System & UI/UX Reference

Captured: **16 October 2026** from `https://payments.lk` (homepage, `/pricing`, `/checkout`,
`/checkout-experiences` + `demo-1…4`, `/developers`, `/faq`, `/facts`, `/blog`,
`/blog/phase-1-launch`, `/privacy`, `/terms`, `/si`, `/ta`, `/llms.txt`).

Purpose: a build-ready reference for the visual language, component recipes and UX behaviour of a
"fintech infrastructure" marketing site — so the same look/feel can be reproduced.

---

## 1. What the site is built with

| Layer | Choice | Evidence |
|---|---|---|
| Framework | **Next.js App Router** (RSC), React | `/_next/static/...`, `<!DOCTYPE html>` + streamed `<!--$?-->` templates |
| Styling | **Tailwind CSS v4.3.3**, CSS-first config (`@theme`) | `/*! tailwindcss v4.3.3 */`, no `tailwind.config.js` in page, theme vars compiled into `:root` |
| Icons | Hand-rolled inline `<svg>` (24×24, `stroke-width: 1.8`, `fill:none`) | every icon is inline SVG, no icon font |
| Body font | **Inter** (self-hosted via `next/font/local`, CSS vars `--font-inter`, `__variable_*`) | `--font-body: var(--font-inter), ui-sans-serif, system-ui, sans-serif` |
| Display font | **Clash Grotesk** 500/600/700 from Fontshare CDN | `<link href="https://api.fontshare.com/v2/css?f[]=clash-grotesk@500,600,700&display=swap">` |
| Localised fonts | Sinhala + Tamil stacks (`--font-sinhala`, `--font-tamil`, `--font-serif-sinhala`, `--font-serif-tamil`) | `@font-face` fallback vars |
| Locales | `en` (`/`), `si` (`/si`), `ta` (`/ta`) + `hreflang` | `<html lang="si" class="… lang-si">` |
| Meta | `theme-color` `#006ea7`, OG 1200×630 `/opengraph-image`, `og:locale en_LK` | page head |
| Machine surface | `/llms.txt`, "For AI agents" footer link, plain-text pricing | footer + `/faq` links |

Two stylesheets load: the main bundle (~90 KB) and a small font CSS. Only **two `<dialog>` elements**
exist on every page (mobile menu + AI assistant), so essentially the whole site is
**CSS + native HTML semantics** (`<details>`, `<dialog>`, `<output>`, `<dl>`, `<table>`), not a
JS component framework.

---

## 2. Design tokens

### 2.1 Colour

Defined in `@theme` (Tailwind v4) and exposed as `--color-*`. Everything else in the UI is built from
these plus Tailwind's default `red/amber/emerald` scales for status.

**Brand (primary blue)**

| Token | Hex | Use |
|---|---|---|
| `brand-50` | `#f0f6fa` | icon tiles, quiet panels, hover fills |
| `brand-200` | `#b8d6e6` | focus ring, soft borders |
| `brand-300` | `#8abcd7` | card hover border, aurora blob |
| `brand-400` | `#4797c0` | input focus border |
| `brand-500` | `#006ea7` | **primary button**, links, accents, `theme-color` |
| `brand-600` | `#005f90` | primary hover, link text |
| `brand-700` | `#004d75` | text on light brand surfaces |

**Ink (neutral text / surfaces)**

| Token | Hex | Use |
|---|---|---|
| `ink` | `#0d1117` | body text, dark bands, terminal background |
| `ink-800` | `#1f2933` | control labels |
| `ink-700` | `#3f4b57` | nav links, secondary body copy |
| `ink-500` | `#6b7684` | captions, meta, disclaimers |
| `ink-400` | `#98a2b0` | placeholders |
| `ink-50` | `#f6f8fa` | alternating section background, chip fill |

**Other**

| Token | Hex | Use |
|---|---|---|
| `line` | `#e6eaef` | 1px hairlines, dividers, card borders |
| `go` | `#00a34d` | success / "cheapest" / live dots / QR bar |
| `go-ink` | `#00823e` | green text on tinted green |
| `cyan` | `#12a0b4` | 3rd aurora/blob colour |
| `white` / `black` | `#fff` / `#000` | surfaces |

Tint recipes that recur everywhere:
`bg-brand-50 text-brand-700` (icon tile), `bg-go/10 text-go-ink` (positive pill),
`bg-ink-50 text-ink-700` (neutral pill), `bg-ink-500/35` (competitor bar),
`bg-brand-500` (own bar), `bg-go/45` (QR share bar).
Borders on dark: `border-white/10`; text on dark: `text-white/70`, `text-white/65`,
`text-white/60`, `text-white/55`, `text-white/50`, `text-white/45`, `text-white/40`.

### 2.2 Typography

```
--font-body:    var(--font-inter), ui-sans-serif, system-ui, sans-serif
--font-display: "Clash Grotesk", "Inter", ui-sans-serif, system-ui, sans-serif
--font-mono:    ui-monospace, "SF Mono", SFMono-Regular, Menlo, monospace
```

`body` sets `font-family: var(--font-body)` and **`font-feature-settings: "cv05" 1, "ss03" 1`**
(alternate `l`/`a` glyphs in Inter) plus `-webkit-font-smoothing: antialiased`.

**Display scale** (clamp-based, letter-spacing is a core part of the look):

| Class | Size | Line-height | Tracking |
|---|---|---|---|
| `.text-display-xl` | `clamp(2.05rem, 6.2vw, 5rem)` | `0.98` | `-0.035em` |
| `.text-display-hero` | `clamp(2rem, 4vw, 3.4rem)` | `1.06` | `-0.03em` |
| `.text-display-lg` | `clamp(1.75rem, 3.6vw, 3rem)` | `1.05` | `-0.028em` |

Tailwind steps used alongside: `text-5xl` (hero-ish numbers, pricing rates), `text-3xl`, `text-2xl`
(section headings, `md:text-3xl`), `text-xl`, `text-lg`, `text-base`, `text-sm`, `text-xs`,
and micro sizes `text-[13.5px]`, `text-[13px]`, `text-[12.5px]`, `text-[12px]`, `text-[11px]`.

Rules of thumb picked up from the markup:

- The big rate (`2.29%`) is **always `font-display` + `font-semibold` + `tabular-nums`** so digits
  don't jitter while animating.
- Section headings are `text-2xl font-semibold md:text-3xl`; **feature** headings upgrade to
  `text-display-lg`.
- Eyebrows are `text-sm font-medium text-brand-600` (or `uppercase tracking-wide` for
  "Checkout experiences").
- Body copy is `leading-relaxed text-ink-700`; captions `text-sm text-ink-500`.
- Long prose is capped: `max-w-2xl` / `max-w-3xl` / `max-w-xl` on paragraphs, never full width.

### 2.3 Spacing, radii, shadows, motion

```
--spacing: .25rem          (Tailwind 4px step)
--radius-sm .25rem  --radius-md .375rem  --radius-lg .5rem
--radius-xl .75rem  --radius-2xl 1rem    rounded-full for pills
```

Shadow stack (custom, layered, low-opacity ink — not Tailwind defaults):

```
--shadow-xs:    0 1px 2px #0d11170d
--shadow-sm:    0 1px 2px #0d11170f, 0 2px 6px #0d11170a
--shadow-md:    0 2px 4px #0d11170d, 0 8px 20px #0d11170f
--shadow-lg:    0 4px 8px #0d11170d, 0 20px 48px #0d111717
--shadow-xl:    0 8px 16px #0d11170f, 0 36px 80px #0d111724
--shadow-brand: 0 8px 24px #006ea747      /* only the primary hero CTA */
```

Applied via inline `style="box-shadow:var(--shadow-lg)"` on key panels rather than classes.

Motion:

```
--ease-out-expo: cubic-bezier(.16,1,.3,1)   /* the signature easing */
--default-transition-duration: .15s
```

Every interactive element uses `transition` (colour) or `transition-transform duration-200`.
Ambient animation keyframes defined once: `drift-a/b/c` (aurora blobs), `trace` (circuit stroke),
`marquee`, `node-pulse`, `map-breathe`, `map-glow`, `glitch-a/b`, `pulse-dot`, `pulse-in`,
`drop-in`, `float`, `pulse-ring`, and a family of `sc-*` keyframes for the checkout micro-demos.

A `:root` **`hairline`** utility replaces `border` everywhere:

```css
.hairline        { border: 1px solid color-mix(in oklab, var(--color-ink) 9%, transparent); }
.lift            { transition: transform .35s var(--ease-out-expo), box-shadow .35s var(--ease-out-expo); }
.lift:hover      { transform: translateY(-3px); box-shadow: var(--shadow-lg); }
.tilt            { transform: perspective(2200px) rotateY(-7deg); }   /* hero preview only */
.tilt:hover      { transform: perspective(2200px) rotateY(-3deg); }
.text-gradient   { background: linear-gradient(96deg,#004d75 0%,#006ea7 45%,#12a0b4 100%);
                   -webkit-background-clip: text; color: transparent; }
```

---

## 3. Layout system

- **Container**: `mx-auto max-w-6xl px-5` (1152 px, 20 px gutters). Reading pages narrow to
  `max-w-3xl`. Full-bleed tool pages (checkout demos) use `max-w-[1500px] px-4 lg:px-6`.
- **Vertical rhythm**: sections are `py-16` (64 px) or `py-20` (80 px); the largest band uses
  `py-20 lg:py-24`.
- **Alternating bands**: the page alternates pure-white sections with `bg-ink-50` (`#f6f8fa`)
  sections, each separated by `border-t border-line` / `border-y border-line`.
- **Two-column feature rows**: `grid gap-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center lg:gap-14`
  — an asymmetric split weighed toward the visual. Text column is always `min-w-0`.
- **Card grids**: `grid gap-4 md:grid-cols-2 lg:grid-cols-3` (industries),
  `sm:grid-cols-3` (pricing tiers), `grid-cols-2 gap-3 sm:gap-4` (checkout demo tiles).
- **Hairline-divided grids (signature move)** — a 1 px `bg-line` background showing through
  `gap-px` white cells, giving perfect dividers with no borders:
  ```html
  <ol class="grid gap-px overflow-hidden rounded-2xl border border-line bg-line sm:grid-cols-3">
    <li class="lift flex flex-col bg-white p-7">…</li>
  </ol>
  ```
- **Anchor scroll**: every linked section carries `id` + `scroll-mt-16|20|24`; `html` has
  `scroll-behavior: smooth` (disabled under `prefers-reduced-motion`).
- **Breakpoints**: Tailwind defaults — `sm 640`, `md 768`, `lg 1024`, `xl 1280`, `2xl 1536` px.
  Nav collapses below `lg`; secondary nav items appear at `xl`; `tilt` is disabled below `lg`.

---

## 4. Component library

### 4.1 Sticky site header

```html
<header class="sticky top-0 z-40 border-b border-line/80 bg-white/85 backdrop-blur">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-3 px-4 sm:px-5">
    <button class="-ml-1.5 flex items-center gap-1.5 rounded-lg px-1.5 py-1.5 text-sm font-medium
                   text-ink-700 hover:bg-ink-50 hover:text-ink lg:hidden">
      <span class="sr-only sm:not-sr-only">Menu</span>
    </button>
    <a href="/" class="shrink-0"><img src="/logo.svg" class="w-auto" style="height:24px"></a>
    <nav class="hidden items-center gap-6 text-sm text-ink-700 lg:flex">
      <a class="hover:text-ink">Pricing</a> … <a class="hidden xl:inline">Enterprise</a>
    </nav>
    <div class="flex shrink-0 items-center gap-1 text-sm sm:gap-3">
      <div class="hidden sm:block"><nav class="flex items-center gap-0.5">…language pills…</nav></div>
      <a class="hidden px-2 py-2 whitespace-nowrap text-ink-700 hover:text-ink sm:inline">Log in</a>
      <a class="rounded-full bg-brand-500 px-4 py-2 font-medium text-white transition
                hover:bg-brand-600">
        <span class="sm:hidden">Apply</span><span class="hidden sm:inline">Start application</span>
      </a>
    </div>
  </div>
</header>
```

UX: translucent + `backdrop-blur`, 64 px tall, CTA label shortens to "Apply" on mobile, language
switch hides below `sm`. Menu button uses `sr-only sm:not-sr-only` so it is icon-only on phones and
"Menu" from 640 px up.

### 4.2 Mobile nav — native `<dialog>` side sheet

```html
<dialog class="m-0 h-dvh max-h-none w-[min(20rem,86vw)] max-w-none bg-white p-0
               shadow-[var(--shadow-xl)] transition-transform duration-200 ease-out
               backdrop:bg-ink/40 open:translate-x-0 starting:open:-translate-x-full
               motion-reduce:transition-none lg:hidden">
  <div class="flex h-full flex-col outline-none">
    <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-line px-5">…</div>
    <div class="min-h-0 flex-1 overflow-y-auto px-3 py-5">…</div>
    <div class="shrink-0 border-t border-line px-5 py-4">…CTA…</div>
  </div>
</dialog>
```

Full-height side sheet, `starting:open:-translate-x-full` for the slide-in, `backdrop:bg-ink/40`
scrim, `motion-reduce:transition-none`. The AI assistant uses the same recipe mirrored
(`ml-auto`, `starting:open:translate-x-full`, `w-full sm:w-[26rem]`) i.e. a right-hand sheet.

### 4.3 Hero

Structure: `section.relative.overflow-hidden` → three decorative layers → content grid.

**Decorative layers** (all `pointer-events-none`, `aria-hidden`):

1. `.aurora` — three blurred (`filter: blur(72px)`, `opacity: .5`) radial blobs animating
   `drift-a/b/c` over 22–30 s with `--ease-out-expo`; dark variant `.aurora-dark` layers
   `#006ea7b3`, `#12a0b473`, `#00a34d59`.
2. `.grid-lines` — `background-image: linear-gradient(90deg,#0d11170f 1px,transparent 0),
   linear-gradient(#0d11170f 1px,transparent 0)`, `background-size: 64px 64px`, masked with
   `radial-gradient(70% 60% at 50% 0, #000 30%, transparent 78%)`. Dark twin `.grid-lines-dark`
   uses `#ffffff0f` and masks from the bottom.
3. `.circuit` — an inline SVG whose `.trace` paths run `stroke-dasharray: 70 1000` with a `trace`
   animation (2.8 s linear infinite), masked left→right so it only appears behind the headline.

**Content grid**: `grid max-w-6xl gap-14 px-5 pb-20 pt-14 lg:grid-cols-[1.15fr_0.85fr]
lg:items-center lg:pb-24 lg:pt-24`.

Left column:

```html
<!-- eyebrow badge -->
<p class="inline-flex items-center gap-2 rounded-full bg-white/70 px-3 py-1.5 text-xs font-medium
          text-brand-700 backdrop-blur hairline">
  <span class="relative flex size-1.5">
    <span class="absolute inline-flex size-full animate-ping rounded-full bg-go opacity-70"></span>
    <span class="relative inline-flex size-1.5 rounded-full bg-go"></span>
  </span>
  Modern payment rails for Sri Lanka 🇱🇰
</p>

<!-- headline: line 1 animates, line 2 static -->
<h1 class="mt-6 text-display-hero font-semibold">
  <span class="block text-balance">Accept payments from
    <span class="text-gradient glitch" data-text="2.29%">2.29%</span>.</span>
  <span class="block text-balance">No monthly fees, now or ever.</span>
</h1>

<p class="mt-7 max-w-xl text-lg leading-relaxed text-ink-700">…</p>

<div class="mt-9 flex flex-col gap-3 sm:flex-row sm:items-center">
  <a class="shrink-0 rounded-full bg-brand-500 px-7 py-3.5 text-center font-medium
            whitespace-nowrap text-white transition hover:bg-brand-600"
     style="box-shadow:var(--shadow-brand)">Start your application</a>
  <a class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-white/80 px-7 py-3.5
            text-center font-medium whitespace-nowrap text-ink backdrop-blur transition
            hover:bg-white hairline">Open sandbox</a>
</div>

<!-- hero stats as a definition list -->
<dl class="mt-10 flex flex-wrap gap-x-9 gap-y-4">
  <div><dt class="text-xs text-ink-500">Setup fee</dt>
       <dd class="font-display text-xl font-semibold">Rs. 0</dd></div>
  …
</dl>
```

**The `glitch` effect** on the rate: a `.glitch` span duplicates its own text via
`content: attr(data-text)` in `::before` (coloured `--color-go`) and `::after`, then
`glitch-a` / `glitch-b` keyframes (4 s `step-end`, invisible until ~40 %) flash the duplicates so
the number appears to "re-settle" periodically. Cheap, no JS, and `tabular-nums` keeps layout stable.

**Right column**: a tilted device/app mock — `figure` with
`w-full max-w-[25rem] overflow-hidden rounded-2xl shadow-[var(--shadow-xl)] ring-1 ring-black/5`
wrapping a `.tilt` container (perspective 2200 px, `rotateY(-7deg)` → `-3deg` on hover; disabled
≤1023 px). Its contents are a live looping payment animation (card fields filling, amount, stamp).

**Hero marquee** below the fold (`aria-label="Cards and rails accepted on every Payments.lk checkout"`):

```html
<div class="marquee-wrap relative overflow-hidden">
  <div class="marquee-track flex w-max items-center">
    <ul class="flex shrink-0 items-center gap-6 pr-6 opacity-90 sm:gap-9 sm:pr-9">…logos…</ul>
    <!-- second <ul> is the duplicate for a seamless loop -->
  </div>
</div>
```

```css
.marquee-wrap  { mask-image: linear-gradient(90deg,#0000,#000 8% 92%,#0000); }
.marquee-track { animation: marquee 28s linear infinite; }
.marquee-wrap:hover .marquee-track { animation-play-state: paused; }
.marquee-track > ul:last-child { display:none; }   /* reduced-motion / no-JS fallback wraps once */
```

Each logo cell: `flex w-16 items-center justify-center sm:w-[4.5rem] h-8 sm:h-9` with
`max-h-full max-w-full object-contain rounded` on the `<img>`.

### 4.4 Buttons

| Variant | Recipe |
|---|---|
| **Primary (hero/large)** | `shrink-0 rounded-full bg-brand-500 px-7 py-3.5 text-center font-medium whitespace-nowrap text-white transition hover:bg-brand-600` + `style="box-shadow:var(--shadow-brand)"` |
| **Primary (section)** | same, `px-6 py-3.5`, no brand shadow |
| **Secondary / ghost** | `shrink-0 rounded-full bg-white px-6 py-3.5 text-center font-medium whitespace-nowrap text-ink transition hover:border-ink-500 hairline` |
| **Secondary on imagery** | `bg-white/80 … backdrop-blur hover:bg-white hairline` |
| **Tertiary (on tinted band)** | `mt-7 inline-block rounded-full border border-line px-6 py-3 font-medium transition hover:border-ink-500` |
| **Header CTA** | `rounded-full bg-brand-500 px-4 py-2 font-medium text-white transition hover:bg-brand-600` |
| **Text link** | `text-sm font-medium text-brand-600 underline underline-offset-4` |
| **Inline link** | `font-medium underline underline-offset-4` (ink or white) |
| **Submit (small, in form)** | `shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:cursor-not-allowed disabled:bg-ink-400/50` |
| **Floating chat** | `flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white shadow-[0_12px_28px_-10px_rgb(13_17_23/0.45)] transition hover:bg-[#1EBE5B] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#128C7E] motion-safe:hover:scale-105` |

Consistent rules: **everything is `rounded-full`** except segmented controls and small form
buttons (`rounded-lg`); label is always `font-medium` + `whitespace-nowrap`; primary CTAs sit in a
`flex flex-col gap-3 sm:flex-row sm:items-center` stack that becomes a row at `sm`.

Analytics hooks are class names (`bc_start_application`, `bc_open_sandbox`, `bc_see_surge_cloud`,
`bc_payable`, …) — a neat, zero-JS way to tag CTAs.

### 4.5 Panels & cards

| Card | Recipe |
|---|---|
| **Lift card** (industries, steps) | `lift flex flex-col rounded-2xl bg-white p-5 hairline` → hover raises 3 px + `--shadow-lg` |
| **Feature panel** | `rounded-2xl bg-white p-7 hairline` (`--shadow-md` inline when it should read as elevated) |
| **Quote/comparison panel** | `mt-8 grid gap-8 rounded-2xl border border-line bg-white p-7 md:grid-cols-2` |
| **Visible card system** | `overflow-hidden rounded-2xl bg-white hairline` + `style="box-shadow:var(--shadow-lg)"` |
| **Tier card** | `lift flex flex-col bg-white p-7` inside the `gap-px bg-line` grid; badge pinned by `mt-auto`-style `flex-1` spacer |
| **Link tile** | `group block h-full overflow-hidden rounded-2xl border border-line bg-white transition hover:border-brand-300 hover:shadow-[var(--shadow-md)]` |
| **Bare link tile** | `block h-full p-6 transition hover:bg-ink-50` inside the `gap-px` grid |
| **Icon tile** | `flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-700` |
| **Dark terminal panel** | `overflow-hidden rounded-2xl` + `style="background:var(--color-ink);box-shadow:var(--shadow-xl)"` |

### 4.6 Chips, pills & badges

```html
<!-- neutral tag (industry modules) -->
<li class="rounded-full bg-ink-50 px-2.5 py-1 text-[11px] font-medium text-ink-700">Point of sale</li>

<!-- semantic badge (tier footers) -->
<span class="mt-5 inline-flex w-fit rounded-full px-3 py-1 text-xs font-medium
             bg-brand-50 text-brand-700">Where you start</span>
<span class="… bg-go/10 text-go-ink">As you grow</span>
<span class="… bg-ink-50 text-ink-700">When you outgrow it</span>

<!-- status table chip -->
<span class="rounded-full px-2 py-0.5 text-[12px] font-medium bg-go/10 text-go-ink">Live</span>

<!-- icon + label badge -->
<span class="inline-flex items-center gap-1.5 rounded-full bg-go/10 px-2.5 py-1 text-xs
             font-semibold text-go-ink">✓ Cheapest in the market</span>

<!-- filter/segment chip (FAQ section nav, language switch) -->
<a class="rounded-full border border-line bg-white px-4 py-2 text-sm font-medium
          transition hover:border-ink-500">Pricing</a>
<a class="rounded-full bg-ink px-4 py-2 text-sm font-medium text-white hover:bg-ink-800">Fact sheet</a>
```

The active language pill is `bg-ink text-white`, inactive is `text-ink-500 hover:bg-ink-50
hover:text-ink` — same pill geometry (`px-2.5 py-1 text-xs font-medium`).

### 4.7 Section header block

Repeats on every section, always in this order:

```html
<p class="text-sm font-medium text-brand-600">Enterprise</p>          <!-- eyebrow (optional) -->
<h2 class="mt-3 max-w-3xl text-display-lg font-semibold">…</h2>       <!-- or text-2xl md:text-3xl -->
<p class="mt-5 max-w-2xl text-lg leading-relaxed text-ink-700">…</p>  <!-- lead -->
```

When the section has a "see all" affordance, the heading row becomes
`flex flex-wrap items-baseline justify-between gap-4` with the link on the right.

### 4.8 Lists

- **Dot bullets** (feature lists): `flex gap-2.5` + `mt-2 size-1.5 shrink-0 rounded-full bg-brand-500`
  (or `gap-3` with `mt-0.5` for 2-line items). No `list-style`.
- **Check bullets** (benefits): `flex gap-3 text-sm leading-relaxed text-ink-700` +
  `mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full bg-go/10 text-go-ink`
  containing a 10 px check SVG.
- **Icon + label rows**: `flex gap-3` + a bare 16 px `size-4` check SVG.
- **Divided list**: `divide-y divide-line border-y border-line` (FAQ) or
  `divide-y divide-line` on a `<dl>`.
- **Chip cloud**: `flex flex-wrap gap-1.5`.

### 4.9 Accordion (FAQ)

Native `<details>` — crawled, keyboard-accessible, no JS:

```html
<div class="mt-8 divide-y divide-line border-y border-line">
  <details class="group py-5">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 font-medium">
      Is the headline rate really the whole cost?
      <span class="text-ink-500 transition group-open:rotate-45">+</span>
    </summary>
    <p class="mt-3 leading-relaxed text-ink-700">…</p>
  </details>
</div>
```

The `+` becomes `×` by rotating 45° when `[open]` — `group-open:rotate-45`, `transition` on
transform. All answers are in the DOM (good for SEO/LLMs); `max-w-3xl` container.

### 4.10 FAQ page pattern — sticky heading + `<dl>`

```html
<section id="pricing" class="grid scroll-mt-24 grid-cols-[minmax(0,1fr)] gap-6 border-b border-line
                            py-14 lg:grid-cols-[16rem_1fr] lg:gap-12">
  <h2 class="text-2xl font-semibold lg:sticky lg:top-24 lg:self-start">Pricing</h2>
  <dl class="min-w-0 divide-y divide-line">
    <div class="py-6 first:pt-0 last:pb-0">
      <dt class="text-lg font-medium">…question…</dt>
      <dd class="mt-3 max-w-3xl leading-relaxed break-words text-ink-700">…answer…</dd>
    </div>
  </dl>
</section>
```

Above it, a chip nav: `<nav aria-label="Sections" class="mt-8 flex flex-wrap gap-2">` of anchor
pills ending in a dark "Fact sheet" pill.

### 4.11 Tables

Two shapes are used.

**Key/value fact sheet** (`/facts`) — semantically a table, visually borderless:

```html
<table class="w-full text-sm">
  <tbody class="divide-y divide-line">
    <tr class="align-top">
      <th scope="row" class="w-[38%] px-5 py-4 text-left font-medium text-ink sm:w-[30%]">Name</th>
      <td class="px-5 py-4 text-ink-700"><p>Payments.lk</p></td>
    </tr>
  </tbody>
</table>
```

**Data table** (`/checkout` demo dashboard) — `w-full min-w-[30rem] text-left text-[13.5px]`,
`thead` at `text-[12px] text-ink-500`, rows `border-t border-line`, numeric columns right-aligned
with `tabular-nums` on `<tbody>`.

**Comparison / leaderboard rows** (the signature "ranked market" component):

```html
<ul class="mt-3 space-y-2.5">
  <li class="grid gap-x-5 gap-y-2 rounded-xl p-4 hairline sm:items-center
             sm:grid-cols-[1.5rem_minmax(0,1.2fr)_minmax(0,1fr)_auto] bg-brand-50/60">
    <span><span role="img" aria-label="Rank 1" class="text-lg">🥇</span></span>
    <div class="min-w-0"><img src="/logo.svg" class="h-5 w-auto object-contain object-left">
      <p class="mt-0.5 text-sm text-ink-500">2.49% on cards, 0.99% on QR, nothing else</p></div>
    <div class="flex h-2.5 overflow-hidden rounded-full bg-ink-50">
      <div class="h-full transition-[width] duration-300 bg-brand-500" style="width:41.6%"></div>
      <div class="h-full transition-[width] duration-300 bg-go"        style="width:2.9%"></div>
    </div>
    <div class="text-right">
      <p class="font-display text-xl font-semibold tabular-nums text-ink">Rs. 9,060</p>
      <p class="text-xs text-ink-500 tabular-nums">Rs. 108,720 / yr
        <span class="block">Rs. 8,466 cards · Rs. 594 QR</span></p>
    </div>
  </li>
</ul>
```

Own row = `bg-brand-50/60` + `bg-brand-500` bar; competitors = `bg-white` + `bg-ink-500/35` bar.
Widths are animated (`transition-[width] duration-300`) so numbers visibly "race".

### 4.12 Code / terminal block

```html
<div class="overflow-hidden rounded-2xl" style="background:var(--color-ink);box-shadow:var(--shadow-xl)">
  <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
    <span class="size-2.5 rounded-full bg-white/20"></span> ×3
    <span class="ml-2 font-mono text-[11px] text-white/40">Take your first payment</span>
  </div>
  <pre class="overflow-x-auto p-5 font-mono text-[12.5px] leading-relaxed"><code>
    <span class="text-[#7dd3fc]">curl</span><span class="text-[#86efac]"> https://api.payments.lk/v1/checkouts \</span><br>
    <span class="text-white/55">  -H </span><span class="text-[#86efac]">"Authorization: Bearer sk_test_…"</span>
  </code></pre>
</div>
```

Inline-styles/standalone variant: `rounded-2xl bg-ink p-5 font-mono text-[12.5px] leading-relaxed
text-white/90`. Syntax palette: command `#7dd3fc`, strings `#86efac`, punctuation `white/55`.
**No syntax-highlighting library** — three literal colours.

### 4.13 Interactive calculator

Sentence-with-inline-fields is the distinctive input pattern:

```html
<p class="flex flex-wrap items-baseline gap-x-2.5 gap-y-2 text-lg text-ink-700">
  <span>I take</span>
  <output for="volume" class="font-display text-3xl font-semibold text-ink tabular-nums">Rs. 400,000</output>
  <span>a month, at about</span>
  <span class="inline-flex items-baseline rounded-lg border border-line bg-white px-2.5 py-1
               transition focus-within:border-brand-500">
    <span class="text-sm text-ink-500">Rs.</span>
    <label for="ticket" class="sr-only">Average order value</label>
    <input id="ticket" type="number" min="100" step="100"
           class="w-20 bg-transparent pl-1 font-display text-xl font-semibold tabular-nums outline-none">
  </span>
  <span>an order, with</span> … <span>% of it by QR</span>
</p>
<input id="volume" type="range" aria-label="Monthly card volume" min="0" max="100" step="0.5"
       class="mt-5 h-6 w-full cursor-pointer accent-[var(--color-brand-500)]">
```

Key UX details: native `<input type="number">` and `type="range"` (no custom slider JS), brand
colour via `accent-color`, `output` mirrors the range, and the whole input sits inline in a
sentence. Results panel below: `font-display text-5xl font-semibold` hero number with a
`text-base font-normal text-ink-500` unit suffix, plus a `rounded-xl bg-brand-50 px-5 py-4`
"what you keep" callout.

### 4.14 Customizer controls (checkout theme builder)

| Control | Recipe |
|---|---|
| **Theme picker** | `grid grid-cols-2 gap-2`; option = `grid gap-1.5 rounded-lg border p-1.5 text-left text-[13px] transition`; selected = `border-brand-500 ring-2 ring-brand-500/20`, unselected = `border-line hover:border-ink-400`. Preview = `grid h-10 grid-cols-2 overflow-hidden rounded-md border border-line/70` with two colour `<span>`s and a `h-2 w-8 rounded-full` accent bar. |
| **Colour swatch row** | `flex gap-1.5`; `size-6 rounded-full border transition border-line` → selected `border-brand-500 ring-2 ring-brand-500/30`; `aria-pressed` + `aria-label="#hex"` |
| **Colour input** | `<input type="color" class="size-6 cursor-pointer rounded border-0 bg-transparent p-0">` inside `flex items-center gap-2 rounded-md border border-line py-0.5 pr-2 pl-0.5 font-mono text-[12px] text-ink-700` with the hex printed as text |
| **Switch** | `<button role="switch" aria-checked="true" class="relative h-5 w-9 rounded-full transition focus:outline-none focus-visible:ring-3 focus-visible:ring-brand-500/30 bg-brand-500">` + knob `absolute top-0.5 size-4 rounded-full bg-white shadow transition-all left-[1.125rem]` |
| **Select** | `rounded-md border border-line bg-white py-1 pr-7 pl-2.5 text-[13px] text-ink-800 focus:border-brand-500 focus:outline-none disabled:opacity-60` |
| **Control panel shell** | `grid content-start overflow-hidden rounded-xl border border-line bg-white shadow-[var(--shadow-sm)]` with `border-b border-line px-4 py-3.5 last:border-b-0` groups and `text-[12px] font-semibold tracking-wide text-ink-500 uppercase` group titles |
| **Row inside group** | `flex min-h-9 items-center justify-between gap-3 py-1` with label `text-[14px] text-ink-800` |

**Checkout theming is done with CSS custom properties** injected on the preview root — the whole
preview re-skins without any class swapping:

```
--co-page --co-text --co-muted --co-line --co-input --co-input-line
--co-summary --co-summary-text --co-summary-muted --co-summary-line
--co-accent --co-on-accent
--co-radius --co-radius-button --co-radius-card --co-font
```

Used as `bg-(--co-summary)`, `text-(--co-summary-muted)`, `border-(--co-input-line)`,
`ring-(--co-accent)/35`, `text-(--co-text)` — i.e. Tailwind v4's `(--var)` arbitrary-value syntax.
The preview also sets `color-scheme` and applies `--co-font`/`--co-radius` via inline `style`, and
the container is a `@container` so the "page" reflows on container width, not viewport.
Copy promises automatic contrast fixing ("If a colour would make text hard to read, the page
adjusts it").

### 4.15 Browser-chrome preview mock

Used for the four checkout demo tiles:

```html
<span class="relative grid h-32 place-items-center overflow-hidden border-b border-line
             bg-[#eef1f5] bg-[radial-gradient(#d3dae3_1px,transparent_1px)]
             [background-size:14px_14px] sm:h-40">
  <span class="grid w-full origin-center scale-[0.8] place-items-center sm:scale-90">
    <div class="w-[78%] overflow-hidden rounded-lg border border-line bg-white shadow-[var(--shadow-md)]">
      <div class="flex h-5 items-center gap-1 border-b border-line bg-[#f7f9fb] px-2">
        <span class="size-1.5 rounded-full bg-[#ff5f57]"></span>
        <span class="size-1.5 rounded-full bg-[#febc2e]"></span>
        <span class="size-1.5 rounded-full bg-[#28c840]"></span>
      </div>
      <!-- skeleton content: rounded grey bars, muted blocks -->
    </div>
  </span>
</span>
```

Recipe: dotted (`radial-gradient`) canvas at `14–16px` spacing + macOS traffic-light chrome
(`#ff5f57 / #febc2e / #28c840`) + hand-built grey skeleton `span`s. Same dotted canvas is used as
the background of the demo builder pages (`bg-[radial-gradient(#d3dae3_1px,transparent_1px)]
[background-size:16px_16px]`).

### 4.16 Dark "parent company" band (site footer)

```html
<footer id="about" class="relative scroll-mt-16 overflow-hidden text-white"
        style="background:var(--color-ink)">
  <div aria-hidden="true" class="aurora aurora-dark"><span></span></div>
  <div aria-hidden="true" class="pointer-events-none absolute inset-0 grid-lines grid-lines-dark"></div>
  <div class="relative mx-auto max-w-6xl px-5"> … </div>
</footer>
```

— i.e. the hero's decorative system reused inverted. Contents, in order:

1. `h2.text-display-lg` holding a white "a [Payable] company" lockup button:
   `inline-flex h-14 items-center gap-2.5 rounded-2xl bg-white px-5 font-body text-sm
   font-normal text-ink-500 transition hover:bg-brand-50`.
2. Narrative paragraph `text-lg leading-relaxed text-white/70` with inline underlined links.
3. Stat index: `grid grid-cols-2 gap-8 text-sm sm:grid-cols-3` — each stat
   `font-display text-3xl font-semibold` + `mt-2 text-sm leading-relaxed text-white/55`. Live
   counter (`tabular-nums`) as the first value.
4. Sri Lanka map figure: `district-map` (`container-type: inline-size`) + `.map-island`
   (`color:#8abcd7e6; filter: drop-shadow(0 0 18px #006ea759); animation: map-breathe 6s`) +
   `.map-glow` blur backdrop. Province/district labels are absolutely positioned
   (`district-label absolute -translate-x-1/2 -translate-y-1/2 flex flex-col items-center
   rounded-md text-center leading-none`) with `district-name tracking-wide text-white/60 uppercase`,
   `district-count font-semibold text-white tabular-nums`,
   `district-volume font-medium text-go tabular-nums`. Counters tick live.
5. Bank logo grid: `grid grid-cols-2 gap-3 sm:grid-cols-4` of
   `flex h-16 items-center justify-center rounded-xl bg-white px-4` cells holding
   `max-h-8 w-auto max-w-full object-contain` images.
6. Contact block (`CALL US` + `font-display text-2xl` phone link) and footer link columns:
   `grid grid-cols-2 gap-8 text-sm sm:grid-cols-3` grouped under
   `text-xs font-semibold tracking-widest text-white/45 uppercase` headings; links are
   `inline-block py-1.5 hover:text-white` in `text-white/65`.
7. Legal row: `flex flex-col gap-4 border-t border-white/10 py-6 md:flex-row md:items-center
   md:justify-between` with `text-xs text-white/50` copyright/rate disclaimer and the language
   switch (`.footer-switch`).

### 4.17 Floating actions

```html
<div class="fixed right-4 bottom-4 z-40 flex flex-col items-end gap-3 sm:right-5 sm:bottom-5">
  <button type="button" aria-expanded="false" aria-label="Ask a question or chat with us"
          title="Ask a question or chat with us"
          class="flex size-14 items-center justify-center rounded-full bg-[#25D366] text-white
                 shadow-[0_12px_28px_-10px_rgb(13_17_23/0.45)] transition hover:bg-[#1EBE5B]
                 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#128C7E]
                 motion-safe:hover:scale-105">…whatsapp glyph…</button>
</div>
```

A fixed bottom-right stack (`z-40`, `gap-3`) holds the WhatsApp FAB, which opens the **AI assistant
right-hand `<dialog>`** ("Ask Payments.lk — Answers from the pages on this site."). The assistant is
also reachable from an inline "Ask a question about Payments.lk" form on the homepage FAQ section
and at the foot of `/faq`, `/facts`, `/blog`, `/pricing`, `/checkout`, `/developers`.

### 4.18 Inline AI ask form

```html
<form class="mt-8">
  <label for="ask" class="block text-sm font-medium text-ink">Ask a question about Payments.lk</label>
  <div class="mt-2 flex items-center gap-2 rounded-xl border border-line bg-white py-1.5 pr-1.5 pl-3.5
              shadow-[var(--shadow-sm)] transition focus-within:border-brand-400
              focus-within:ring-2 focus-within:ring-brand-200">
    <input id="ask" type="text" maxlength="600" placeholder="For example: how long does approval take?"
           autocomplete="off" enterkeyhint="send" aria-describedby="ask-note"
           class="min-w-0 flex-1 bg-transparent py-1.5 text-base text-ink outline-none
                  placeholder:text-ink-400 sm:text-sm">
    <button type="submit" aria-haspopup="dialog"
            class="shrink-0 rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white
                   transition hover:bg-brand-700 focus-visible:outline-2 focus-visible:outline-offset-2
                   focus-visible:outline-brand-600 disabled:cursor-not-allowed
                   disabled:bg-ink-400/50">Ask</button>
  </div>
  <p id="ask-note" class="mt-2 text-xs leading-relaxed text-ink-500">Our AI assistant answers from
    the pages on this site and links to its sources.</p>
</form>
```

Submit is `disabled` until the field is non-empty. The **focus-within ring on the wrapper** (not the
input) is the house style for text fields.

### 4.19 Live activity toast

Client-rendered social-proof toast, bottom-left, e.g.
*"A software startup in Homagama submitted an application · 6 min ago"* with a small avatar. It
rotates through recent-signup messages. (Cosmetic credibility device — worth replicating only if the
data is real; the rest of the site is scrupulous about not inventing figures.)

### 4.20 Animated CSS product mockups ("Enterprise" bento)

The Enterprise section never uses screen recordings for the modules — each is a **hand-built HTML
mockup that animates itself in a timed loop**. Structure:

```html
<div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
  <figure class="sc-frame flex flex-col overflow-hidden rounded-2xl bg-white hairline md:col-span-2">
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 border-b border-line px-5 py-3">
      <figcaption class="text-sm font-semibold">Automated VAT compliance for Sri Lanka</figcaption>
      <span class="rounded-full bg-ink-50 px-2.5 py-0.5 text-[11px] font-medium text-ink-500">
        Every invoice, as it happens
      </span>
    </div>
    <div class="relative flex-1 p-5"> …animated body… </div>
  </figure>
  …
</div>
```

Panels are 2- or 4-column spans inside a 4-column bento. Every animated child carries an inline
`style="animation-delay:0.3s"` etc. so a single 8 s loop reads as a storyboard.

**The `sc-*` animation vocabulary** (all 8 s infinite, except `.sc-room` at 5 s):

| Class | Keyframes | Effect |
|---|---|---|
| `.sc-rise` | `0%,3%{opacity:0;transform:translateY(6px)}` | ledger/invoice rows appearing one by one (staggered delays) |
| `.sc-fill` | `0%,30%{width:0}` → 100 % | progress bar reaching completion |
| `.sc-pop` | `0%,52%{opacity:0;transform:scale(.9)}` | "PAID" / success badge stamping in |
| `.sc-stamp` | `0%,40%{opacity:0;transform:rotate(-12deg) scale(1.4)}` | rotated rubber-stamp effect |
| `.sc-slip` | `0%,8%{opacity:0;transform:translate(-16px)}` | payslip sliding in |
| `.sc-drain` | `0%,20%{width:60%}` | stock level draining |
| `.sc-lane` | `0%,10%{transform:translate(0)}` | kitchen lane / kanban movement |
| `.sc-room` | `0%{color:var(--color-brand-700);background:#006ea71f}` `step-end` | hotel room grid cell lighting up (`.sc-room flex aspect-square items-center justify-center rounded-md text-[10px] font-medium tabular-nums`) |

Mockups built this way in the section: **Card terminal** (`<video autoPlay loop muted playsInline>`
in a `md:grid-cols-[1fr_1.1fr]` figure — the one place video is used), **VAT compliance**
(`<dl class="divide-y divide-line">` of `flex items-baseline justify-between gap-4 py-2.5` rows +
progress bar), **Invoicing** (invoice card with line items and a total),
**Inventory** (stock list with quantities + "Reorder raised" toast),
**Payroll** (stacked payslips with `Net pay` rows), **Hotel PMS** (6-column room grid with
`Occupied / Ready / Housekeeping` legend), **Restaurant and bar** (3-column kitchen lanes:
`New → Kitchen → Served` with table/order tickets).

Reduced-motion fallback is explicit and complete: `.sc-rise/.sc-pop/.sc-slip → opacity:1, no
animation`, `.sc-fill → width:100%`, `.sc-stamp → static rotated`, `.sc-drain/.sc-lane/.sc-room →
animation:none`. The result is a still, readable mockup.

**Also in this section:** the "industry modules" card grid (`lift … rounded-2xl bg-white p-5
hairline` with icon tile, `h3.font-semibold`, `text-sm text-ink-700` summary and a chip cloud), and
the software-cost comparison built from the same ranked-bar row component as §4.11, plus a
`mt-14` footnote line in `text-xs text-ink-500`.

---

## 5. Page-by-page inventory

| Route | H1 | Structure |
|---|---|---|
| `/` | Accept payments from 2.29% *(animated)* | Hero → marquee → pricing overview → zero-fee promise → cost calculator → professionals (no BR) → How it works (3 steps) → checkout pages → developers → **Enterprise mega-section** → FAQ accordion + ask form → dark Payable footer band |
| `/pricing` | Everything we charge, and everything they charge. | Annual-fee comparison list (bars) → fee schedule tier cards (gap-px grid) → promise panel → monthly-cost comparison → calculator → full charge sheet |
| `/checkout` | A checkout page for what you sell, ready in minutes | Feature list → live theme/colour/font/corner customizer + preview → publish/share → server-created checkouts → PCI note → feature grid → no-extra-fee → CTA |
| `/checkout-experiences` | Try a checkout before you build one | Hero with `.grid-lines` → 2-col demo tile grid (browser-chrome skeletons) |
| `/checkout-experiences/demo-1…4` | Checkout page / Payment link / Pay button / In-app | Tool shell: `max-w-[1500px]`, sticky 56 px header with breadcrumb-ish title + "Change demo" link, dotted canvas, `lg:grid-cols-[300px_1fr]`, control panel `role="tablist"` (Features/…) in a `lg:sticky lg:top-[4.75rem]` sidebar, live preview in the main column |
| `/developers` | One endpoint. Keys on approval. | Hero with terminal block → sandbox→live steps → "predictable by design" → plugin/SDK `gap-px` tile grid (WooCommerce, PrestaShop, …) → code blocks (`rounded-2xl bg-ink`) → agent-readable section → API/Sandbox/Webhooks docs |
| `/faq` | Every question, answered. | Hero → ask form → section chip nav → 8 `<dl>` sections with sticky `h2`s |
| `/facts` | Everything about Payments.lk, stated once. | 8 key/value `<table>` blocks ("If you are an AI agent" last) |
| `/blog` | Launch notes. | `max-w-3xl`, `divide-y divide-line border-y` post list: `text-xs text-ink-500` date → `text-xl font-semibold` title link → `leading-relaxed text-ink-700` summary |
| `/blog/{slug}` | Article title | `max-w-3xl px-5 py-16`, back-link, date, `text-display-lg` H1, `text-lg leading-relaxed` standfirst, body as `space-y-6` of `leading-relaxed text-ink-700` blocks with `text-xl font-semibold` H2s |
| `/privacy`, `/terms` | Privacy policy / Terms of use | `max-w-3xl` legal prose, "In short" summary first, numbered H2 sections |
| `/si`, `/ta` | Translated | Same layout; `<html lang="si" class="… lang-si">`, Sinhala/Tamil font stacks |
| `/llms.txt` | — | Plain-text machine summary of the business, pricing and links |

---

## 6. UX & interaction rules

1. **Native-first interactivity.** `<details>` for accordions, `<dialog>` for sheets, `<output>` for
   computed values, `<table>`/`<dl>` for data, `role="tablist"` only where a real tab UI exists.
   Almost nothing needs JS.
2. **Sticky, translucent chrome.** Header is `sticky top-0 z-40 bg-white/85 backdrop-blur`;
   in-page section headings stick (`lg:sticky lg:top-24`) and tool sidebars stick
   (`lg:sticky lg:top-[4.75rem]`).
3. **Motion is ambient, never blocking.** Long, slow, alternating loops (22–30 s drifts, 28 s
   marquee, 4–6 s glitches), always paused/removed under `prefers-reduced-motion`. `motion-safe:` /
   `motion-reduce:` variants used explicitly (`motion-safe:hover:scale-105`).
4. **Numbers are trustworthy-looking.** `tabular-nums` on every figure; live counters;
   animated bar widths; published rate dates under every comparison table.
5. **Honest-data policy.** Comparisons state their source and date ("Competitor figures are the
   providers' published rates as at 5 September 2026…"), and disclaimers explain illustrative
   figures. Under-threshold stats show an educational message instead of invented testimonials.
6. **Progressive disclosure via `<dialog>` side sheets**, `backdrop:bg-ink/40`,
   `starting:open:translate-x-*` for the entry transition.
7. **Focus ring is global**: `:where(a,button,input,select,textarea,[tabindex]):focus-visible
   { outline: 2px solid var(--color-brand-500); outline-offset: 2px; border-radius: 6px; }`
8. **Two clicks to conversion everywhere.** Primary CTA in header, hero, every major section, and
   the footer, all pointing at the same `app.payments.lk/admin/setup`.
9. **Zero-JS anti-pattern avoidance**: no `x-cloak`-style hidden content, all FAQ answers in DOM,
   `/llms.txt` + plain-text copy for agents.
10. **Responsive stance**: mobile-first, single-column → asymmetric two-column at `lg`, nav
    collapses at `lg`, decorative effects (`tilt`, full grid-lines) disabled on small screens.

---

## 7. Accessibility notes worth copying

- `<dialog>` + `aria-labelledby`, `aria-expanded` on the trigger, visible close button
  (`aria-label="Close the assistant"`, `size-9` hit area).
- `aria-current="true"` on the active language link; `hrefLang` + `lang` on every locale link.
- Decorative layers are `aria-hidden="true"` + `pointer-events-none`.
- Marquee wrapper has a descriptive `aria-label`; the duplicated track is the a11y-hidden copy.
- `<label class="sr-only">` for inline/sentence inputs and switches; `role="switch"` + `aria-checked`
  for toggles; `aria-pressed` for swatch/theme selection.
- Rank emoji carry `role="img" aria-label="Rank 1"`.
- Charts/figures use `<figure>` + `<figcaption>`; the map figure has a full sentence
  `aria-label`.
- Disabled submit buttons use both `disabled` and `disabled:cursor-not-allowed disabled:bg-ink-400/50`.
- Reduced motion honoured at three levels: global (`scroll-behavior: auto`), component
  (`.circuit .trace{animation:none;opacity:.18}`, `.marquee` mask off, `.tilt` transform off), and
  utility (`motion-reduce:transition-none`).

---

## 8. i18n

- Three locales: `en` (root), `si` (`/si`), `ta` (`/ta`), all with `hreflang` and mirrored routes.
- `<html lang="si" class="lang-si">` swaps font stacks by overriding the CSS vars:
  ```css
  .lang-si { --font-body: var(--font-sinhala), "Noto Sans Sinhala", sans-serif;
             --font-display: var(--font-sinhala), "Noto Sans Sinhala", sans-serif; }
  .lang-ta { --font-body: var(--font-tamil), "Noto Sans Tamil", sans-serif; … }
  .lang-si, .lang-ta { font-size: 93.75%; }
  :is(.lang-si,.lang-ta) body { line-height: 1.65; }
  /* tracking off, tighter leading, semibold instead of bold for non-Latin scripts */
  :is(.lang-si,.lang-ta) :is(h1,h2,h3,.font-display) { letter-spacing: 0; line-height: 1.3; }
  :is(.lang-si,.lang-ta) :is(h1,h2,h3,.font-display,.font-semibold,.font-bold) { font-weight: 600; }
  ```
- Bottom-of-page language pills (`English / සිංහල / தமிழ்`) mirror the header switch.

---

## 9. Rebuild checklist (porting this theme)

1. Define the palette + ink scale + `line` + `go` in the Tailwind theme (v4 `@theme` or v3
   `theme.extend.colors`), and add the three custom shadows + `--shadow-brand`.
2. Add the display font (Clash Grotesk or a close grotesk: Space Grotesk / Sora) and the
   `text-display-{xl,lg,hero}` clamp utilities.
3. Add the four custom utilities: `hairline`, `lift`, `text-gradient`, `grid-lines`
   (+ dark variants), and the `.aurora` / `.marquee` / `.circuit` blocks with their keyframes.
4. Build the primitives: sticky blurred header, `<dialog>` sheets, full/ghost pill buttons,
   `gap-px bg-line` card grid, chip, badge, `details` accordion, key/value table, terminal block,
   `figure`/`figcaption` panel, ranked-bar comparison row.
5. Apply the rhythm: `max-w-6xl px-5`, alternating white / `#f6f8fa` bands, `py-16`–`py-24`,
   `max-w-2xl` prose, `leading-relaxed text-ink-700` body.
6. Wire the behaviours: `y`-translated hover lift (3 px), paused-on-hover marquee,
   45°-rotating `+`, focus-within input ring, `tabular-nums` figures, animated bar widths.
7. Gate everything decorative behind `prefers-reduced-motion` and keep the global brand focus ring.
8. Add the `bc_*` analytics class conventions on CTAs and the `/llms.txt` + plain-text pricing
   surface if the site should be agent-readable.
