# Design System Master File — OSCAR Laptop Shop Blog

> **LOGIC:** When building a specific page, first check `design-system/oscar-laptop-shop-blog/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** OSCAR Laptop Shop Blog
**Generated:** 2026-08-22 10:51:40
**Category:** Magazine/Blog
**Brand Context Override (2026-08-22):** Skill returned `--color-accent: #EC4899` (pink) and fonts `Libre Bodoni + Public Sans` from generic magazine pattern. **Rejected** because:
1. Oscar brand color is orange `#f15a24` (used on CTA buttons, hero, badges, hover state).
2. SPA bundle already loads `IBM Plex Sans` + `Archivo` — adding 2 more Google Fonts triggers CLS and 200ms+ LCP regression.
3. Site is retail-vertical (Vietnamese laptops) with established brand identity — pink editorial aesthetic misroutes user perception.

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable | Notes |
|------|-----|--------------|-------|
| Brand primary | `#f15a24` | `--oscar-orange-500` | CTA bg, hover, badge — 3.37:1 on white (UI only) |
| Brand dark | `#c2410c` | `--oscar-orange-700` | **Text/icon on white** — 5.18:1 ✓ AA-body |
| Brand deeper | `#7c2d12` | `--oscar-orange-900` | Headline accent, link hover |
| Brand soft | `#fff5ec` | `--oscar-orange-50` | Tag bg, badge bg, highlight |
| Brand border | `#ffe0b6` | `--oscar-orange-100` | Subtle border, divider |

| Role | Hex | CSS Variable | Contrast |
|------|-----|--------------|----------|
| Ink primary | `#0f172a` | `--oscar-ink-900` | 17.85:1 ✓ AAA body |
| Ink secondary | `#334155` | `--oscar-ink-700` | 10.31:1 ✓ AAA |
| Ink muted | `#64748b` | `--oscar-ink-500` | 4.76:1 ✓ AA body |
| Ink subtle | `#94a3b8` | `--oscar-ink-400` | 2.56:1 ✗ (DECORATIVE ONLY — do NOT use for body) |
| Surface | `#ffffff` | `--oscar-surface` | |
| Surface alt | `#f8fafc` | `--oscar-surface-alt` | |
| Surface warm | `#fff7ec` | `--oscar-surface-warm` | Used by category nav |
| Border | `#d9e4ee` | `--oscar-border` | |
| Border soft | `#e2e8f0` | `--oscar-border-soft` | |
| Blue | `#0b5eb8` | `--oscar-blue` | Hotline, link, search border |
| Success | `#10b981` | `--oscar-success` | |
| Danger | `#dc2626` | `--oscar-danger` | |

### Typography

- **Body Font:** IBM Plex Sans (already loaded in SPA bundle — do NOT add new font)
- **Display Font:** Archivo (already loaded — used in brand)
- **Mood:** editorial, trustworthy, readable, professional, Vietnamese-friendly (IBM Plex Sans has Vietnamese diacritic support)
- **Weights used:** 400, 500, 600, 700 (max — DO NOT exceed 4 weights)
- **Base size:** 16px (1rem)
- **Article body size:** 18px (1.125rem) — optimal for Vietnamese reading
- **Line height:** 1.5–1.7 (relaxed for body, tighter for headings)
- **Letter spacing:** `-0.01em` for h1/h2 (slight tightening)

**Type scale (8 sizes, modular scale 1.25):**

| Token | Size | Line-height | Use case |
|-------|------|-------------|----------|
| `--fs-xs` | 12px | 1.5 | Caption, badges |
| `--fs-sm` | 14px | 1.5 | Footer meta, microcopy |
| `--fs-base` | 16px | 1.5 | UI text, buttons |
| `--fs-md` | 18px | 1.7 | **Article body** |
| `--fs-lg` | 20px | 1.4 | Lead paragraph |
| `--fs-xl` | 24px | 1.3 | H3 |
| `--fs-2xl` | 30px | 1.25 | H2 |
| `--fs-3xl` | 38px | 1.2 | H1 (mobile) |
| `--fs-4xl` | 48px | 1.15 | H1 (desktop) |

### Spacing Variables

| Token | Value | Usage |
|-------|-------|-------|
| `--space-1` | 4px | Tight gaps |
| `--space-2` | 8px | Icon gaps, inline spacing |
| `--space-3` | 12px | Small padding |
| `--space-4` | 16px | Standard padding |
| `--space-5` | 20px | Card padding |
| `--space-6` | 24px | Section gap |
| `--space-8` | 32px | Article section gap |
| `--space-10` | 40px | Large gaps |
| `--space-12` | 48px | Section padding |
| `--space-16` | 64px | Hero padding |
| `--space-24` | 96px | Hero hero padding |

### Radius Scale (4 levels)

| Token | Value | Use |
|-------|-------|-----|
| `--radius-sm` | 8px | Inputs, buttons, small chips |
| `--radius-md` | 12px | Cards |
| `--radius-lg` | 16px | Hero, article card |
| `--radius-xl` | 24px | Featured cards |
| `--radius-full` | 9999px | Pills, avatars |

### Shadow Depths (4 levels)

| Token | Value | Use |
|-------|-------|-----|
| `--shadow-sm` | `0 1px 2px rgba(13,24,40,.06)` | Subtle lift |
| `--shadow-md` | `0 4px 14px rgba(13,24,40,.06)` | Cards, buttons |
| `--shadow-lg` | `0 10px 30px rgba(13,24,40,.10)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 50px rgba(13,24,40,.18)` | Hover lift |

### Container

- `.shell` = `width: min(1180px, 100% - 32px); margin: 0 auto;`
- Article prose = `max-width: 65ch` (≈ 700px at 18px) for optimal line length (65-75 chars)
- Article hero width = `.shell` (1180px max)

### Motion

| Token | Value | Use |
|-------|-------|-----|
| `--motion-fast` | 150ms | Hover, color |
| `--motion-base` | 220ms | Transform, opacity |
| `--motion-slow` | 320ms | Layout shift |
| Easing | `cubic-bezier(.4,0,.2,1)` (ease-out) | Default |
| Respect | `prefers-reduced-motion: reduce` | Disable transforms, keep opacity |

---

## Component Specs

### Buttons

```css
.btn {
  display: inline-flex; align-items: center; gap: 8px;
  height: 44px; padding: 0 20px;
  font-family: inherit; font-weight: 600; font-size: 16px;
  border: 0; border-radius: 9999px;
  cursor: pointer;
  transition: background-color var(--motion-fast) ease-out, transform var(--motion-fast) ease-out;
}
.btn-primary { background: var(--oscar-orange-500); color: #fff; }
.btn-primary:hover { background: var(--oscar-orange-700); }
.btn-primary:active { transform: scale(.97); }
.btn-secondary { background: var(--oscar-surface); color: var(--oscar-orange-700); border: 2px solid var(--oscar-orange-500); }
.btn-ghost { background: transparent; color: var(--oscar-ink-700); }
.btn-ghost:hover { background: var(--oscar-surface-alt); }
```

### Inputs

```css
.input {
  width: 100%; height: 44px;
  padding: 0 16px;
  font: inherit; font-size: 16px;
  color: var(--oscar-ink-900);
  background: var(--oscar-surface);
  border: 1px solid var(--oscar-border);
  border-radius: var(--radius-md);
  transition: border-color var(--motion-fast) ease-out, box-shadow var(--motion-fast) ease-out;
}
.input:focus, .input:focus-visible {
  outline: none;
  border-color: var(--oscar-blue);
  box-shadow: 0 0 0 3px rgba(11,94,184,.16);
}
```

### Cards

```css
.card {
  background: var(--oscar-surface);
  border: 1px solid var(--oscar-border-soft);
  border-radius: var(--radius-md);
  padding: var(--space-5);
  box-shadow: var(--shadow-sm);
  transition: transform var(--motion-base) ease-out, box-shadow var(--motion-base) ease-out;
}
.card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
}
```

### Focus Ring (a11y)

```css
:focus-visible {
  outline: 2px solid var(--oscar-orange-500);
  outline-offset: 2px;
  border-radius: 4px;
}
```

### Skip Link (a11y)

```css
.skip-link {
  position: absolute; top: -100px; left: 16px;
  z-index: 9999;
  background: var(--oscar-orange-500);
  color: #fff;
  padding: 12px 20px;
  border-radius: 0 0 12px 12px;
  font-weight: 600;
  text-decoration: none;
  transition: top var(--motion-fast) ease-out;
}
.skip-link:focus { top: 0; }
```

---

## Style Guidelines

**Style:** Swiss Modernism 2.0 + Editorial (override of generic magazine pink)
**Layout:** 12-column grid, mathematical spacing (8px base), single accent color
**Tone:** Trustworthy retail content, professional, Vietnamese voice
**Best For:** Blog articles, product reviews, laptop buying guides, tech tutorials

### Key Effects

- `display: grid; grid-template-columns: repeat(12, 1fr); gap: 1rem` for layouts
- `max-width: 65ch` for prose (line length 65-75 chars)
- `line-height: 1.7` for body (relaxed for Vietnamese)
- Smooth transitions 150-300ms (motion-fast, motion-base)

---

## Anti-Patterns (Do NOT Use)

- ❌ Pink editorial palette (`#EC4899`) — not Oscar brand
- ❌ Libre Bodoni / Public Sans fonts — already loaded IBM Plex Sans + Archivo
- ❌ Emojis as icons (use SVG: Heroicons / Lucide)
- ❌ Missing `cursor: pointer` on clickable
- ❌ Layout-shifting hovers (avoid `scale()` that shifts neighbors)
- ❌ Low contrast text (must hit 4.5:1 for body)
- ❌ Instant state changes (always 150-300ms transition)
- ❌ Invisible focus states
- ❌ Body text < 16px avg
- ❌ Body line-height < 1.5
- ❌ Body line-length > 80ch (max 75ch ideal)
- ❌ Decorative motion (only animation that conveys meaning)

---

## Pre-Delivery Checklist

- [ ] Skip-link to content present (a11y)
- [ ] All interactive elements have `:focus-visible` ring
- [ ] All icon-only buttons have `aria-label`
- [ ] Body text contrast ≥ 4.5:1 (use `--oscar-ink-900` or `--oscar-orange-700` for accent)
- [ ] Article body line-length ≤ 75ch (use `max-width: 65ch` on prose)
- [ ] Article body line-height ≥ 1.6
- [ ] Body font ≥ 16px (article ≥ 18px)
- [ ] Touch targets ≥ 44×44px (buttons, icon buttons, links in cards)
- [ ] Breadcrumb uses `<nav aria-label="Breadcrumb">`
- [ ] Headings semantic order (h1 once, h2, h3 etc.)
- [ ] Images have `alt` text
- [ ] Decorative SVGs have `aria-hidden="true"`
- [ ] `prefers-reduced-motion: reduce` respected
- [ ] Responsive: 375, 768, 1024, 1440 verified
- [ ] No horizontal scroll on mobile
- [ ] Sticky header offset for anchor links (`scroll-padding-top`)

---

## Token Reference (CSS)

```css
:root {
  /* Brand */
  --oscar-orange-50: #fff5ec;
  --oscar-orange-100: #ffe0b6;
  --oscar-orange-500: #f15a24;
  --oscar-orange-700: #c2410c;
  --oscar-orange-900: #7c2d12;

  /* Ink */
  --oscar-ink-900: #0f172a;
  --oscar-ink-700: #334155;
  --oscar-ink-500: #64748b;
  --oscar-ink-400: #94a3b8;

  /* Surface */
  --oscar-surface: #ffffff;
  --oscar-surface-alt: #f8fafc;
  --oscar-surface-warm: #fff7ec;

  /* Border */
  --oscar-border: #d9e4ee;
  --oscar-border-soft: #e2e8f0;

  /* Semantic */
  --oscar-blue: #0b5eb8;
  --oscar-success: #10b981;
  --oscar-danger: #dc2626;

  /* Type scale */
  --fs-xs: .75rem;  --fs-sm: .875rem; --fs-base: 1rem;
  --fs-md: 1.125rem; --fs-lg: 1.25rem; --fs-xl: 1.5rem;
  --fs-2xl: 1.875rem; --fs-3xl: 2.375rem; --fs-4xl: 3rem;

  /* Spacing scale (8px base, with 4px addition) */
  --space-1: 4px; --space-2: 8px; --space-3: 12px;
  --space-4: 16px; --space-5: 20px; --space-6: 24px;
  --space-8: 32px; --space-10: 40px; --space-12: 48px;
  --space-16: 64px; --space-24: 96px;

  /* Radius */
  --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px;
  --radius-xl: 24px; --radius-full: 9999px;

  /* Shadow */
  --shadow-sm: 0 1px 2px rgba(13,24,40,.06);
  --shadow-md: 0 4px 14px rgba(13,24,40,.06);
  --shadow-lg: 0 10px 30px rgba(13,24,40,.10);
  --shadow-xl: 0 20px 50px rgba(13,24,40,.18);

  /* Motion */
  --motion-fast: 150ms;
  --motion-base: 220ms;
  --motion-slow: 320ms;
}
```
