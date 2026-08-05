# Design System Master File — OSCAR Laptop Shop

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** OSCAR Laptop Shop (`https://maytinhthuduc.com`)
**Site:** B2C e-commerce — laptop & consumer electronics retail
**Locale:** Vietnamese (primary), English (secondary)
**Audience:** Working professionals, students, IT buyers in HCMC
**Trust posture:** Mid-market, value-for-money specialist; warranty + after-sales is key differentiator
**Generated:** 2026-08-05 (ui-ux-pro-max v2.1.0)
**Override note:** Persisted output was overridden manually to match OSCAR's actual brand
(`#f15a24` orange) instead of skill's recommended gold `#A16207`. Rationale: brand identity
exists and is established in production (theme-color meta, social, print). Don't rename.

**Design Dials:** Variance 3/10 (Centered / Minimal) | Motion 3/10 (Subtle) | Density 5/10 (Standard+)

---

## Brand Context

| Attribute | Value |
|---|---|
| Brand name | Laptop OSCAR Thủ Đức |
| Brand orange (CTA bg, badge) | `#f15a24` |
| Brand orange (text/icon — WCAG AA 5.18:1) | `#c2410c` |
| Tone | Professional, trustworthy, value-focused (not luxury, not playful) |
| Differentiator | 12-month warranty badge, B2C retail expertise, Thủ Đức locality |

---

## 1. Color Palette

### 1.1. Brand scale (orange)
| Token | Hex | Usage | WCAG on white |
|---|---|---|---|
| `--brand-50` | `#fff5ed` | hover bg, subtle surface | — |
| `--brand-100` | `#ffe6d4` | soft badge bg | — |
| `--brand-300` | `#ffb180` | icon disabled | — |
| `--brand-500` | `#f15a24` | **primary CTA bg, brand badge, link hover** | 3.37:1 ❌ UI only |
| `--brand-700` | `#c2410c` | **text/icon/link — WCAG AA 5.18:1 ✓** | 5.18:1 ✓ |
| `--brand-900` | `#7c2d12` | pressed state, deep brand | 9.95:1 ✓ AAA |

> **Rule:** `--brand-500` for backgrounds only (CTA buttons, badges). Use `--brand-700` for any
> text/icon ≥14px. Never `--brand-500` for body text. Never `--brand-300` for text.

### 1.2. Neutral (ink)
| Token | Hex | Usage | WCAG on white |
|---|---|---|---|
| `--ink-0` | `#ffffff` | surface | — |
| `--ink-50` | `#f8fafc` | page bg, subtle surface | — |
| `--ink-100` | `#f1f5f9` | hover bg, card bg | — |
| `--ink-200` | `#e2e8f0` | divider | — |
| `--ink-300` | `#cbd5e1` | border, disabled | — |
| `--ink-400` | `#94a3b8` | placeholder text | 2.56:1 ❌ |
| `--ink-500` | `#64748b` | muted text (AA-body ✓) | 4.76:1 ✓ |
| `--ink-700` | `#334155` | secondary text | 10.39:1 ✓ AAA |
| `--ink-900` | `#0f172a` | primary text | 17.85:1 ✓ AAA |

### 1.3. Semantic
| Token | Hex | Usage | WCAG on white |
|---|---|---|---|
| `--success` | `#10b981` | warranty badge, in-stock | 2.84:1 ❌ UI only |
| `--success-bg` | `#ecfdf5` | success banner bg | — |
| `--info` | `#0ea5e9` | info banner, link | 3.05:1 ❌ UI only |
| `--info-700` | `#0369a1` | info text | 5.94:1 ✓ AA |
| `--warning` | `#f59e0b` | sale tag, low-stock | 2.39:1 ❌ UI only |
| `--warning-700` | `#b45309` | warning text | 4.78:1 ✓ AA |
| `--danger` | `#dc2626` | error, out-of-stock | 4.83:1 ✓ AA |
| `--danger-bg` | `#fef2f2` | error bg | — |

### 1.4. Color anti-patterns (from audit 2026-07-26)
- ❌ **Color drift:** bundle `#d9480f` vs theme-meta `#f15a24` — pick `#f15a24` for all uses
- ❌ **Mixed accent colors:** teal `#0f766e` + cyan `#0891b2` + blue `#0b5eb8` co-existing → pick `--brand-700` as the single accent
- ❌ **Yellow accent `#ffd166`** used as label bg → low contrast, replace with `--ink-700` or remove

---

## 2. Typography

### 2.1. Font families
| Role | Family | Weights loaded | Source |
|---|---|---|---|
| Primary (UI + body) | **Inter** | 400, 500, 600, 700 | Google Fonts (already loaded — 9 woff2 files in `assets/01-k3kPo8...woff2`) |
| Display (hero, marketing) | **Inter** | 800, 900 | Same family — avoid mixing 2 fonts |

> **Rule:** Single font family (Inter). No fallback chain bloat. Already paid the bandwidth cost.

### 2.2. Type scale (modular 1.250, base 16px)
| Token | Size | Line-height | Usage |
|---|---|---|---|
| `--fs-xs` | 0.75rem (12px) | 1.4 | micro label, table cell |
| `--fs-sm` | 0.875rem (14px) | 1.5 | small caption, helper text |
| `--fs-base` | **1rem (16px)** | 1.5 | **body — minimum** |
| `--fs-md` | 1.125rem (18px) | 1.5 | lead paragraph |
| `--fs-lg` | 1.25rem (20px) | 1.4 | card title |
| `--fs-xl` | 1.5rem (24px) | 1.3 | section heading |
| `--fs-2xl` | 2rem (32px) | 1.2 | page heading |
| `--fs-3xl` | 2.5rem (40px) | 1.1 | hero heading |
| `--fs-4xl` | 3rem (48px) | 1.05 | hero (desktop only) |

### 2.3. Font-weight scale (4 levels only)
| Token | Weight | Usage |
|---|---|---|
| `--fw-regular` | 400 | body text |
| `--fw-medium` | 500 | UI labels |
| `--fw-semibold` | 600 | button text, card title |
| `--fw-bold` | 700 | page heading, emphasis |

> **Rule:** Drop `--fw-650/750/800/850/900/950` from bundle. Standardize to 4 levels. CSS variable only.

### 2.4. Typography anti-patterns (from audit)
- ❌ 8+ font-weight values → use the 4 above
- ❌ 5+ fallback families (Inter / Space Grotesk / IBM Plex Sans / Archivo / Arial) → Inter + system-ui fallback only
- ❌ Body text 0.68–0.9rem (10.9–14.4px) → minimum `--fs-base` (16px)
- ❌ H1 at 2rem (32px) for hero → use `--fs-3xl` (40px) or `--fs-4xl` (48px) on desktop

---

## 3. Spacing

### 3.1. 4px grid
| Token | Value | Usage |
|---|---|---|
| `--space-1` | 4px | hairline gap |
| `--space-2` | 8px | icon gap, tight inline |
| `--space-3` | 12px | small padding |
| `--space-4` | 16px | standard padding |
| `--space-5` | 20px | medium gap |
| `--space-6` | 24px | card padding, section gap |
| `--space-8` | 32px | large gap |
| `--space-10` | 40px | section margin (mobile) |
| `--space-12` | 48px | section margin (desktop) |
| `--space-16` | 64px | hero padding |
| `--space-24` | 96px | mega hero, footer top |

### 3.2. Anti-patterns (from audit)
- ❌ Odd values (2, 3, 10, 11, 13, 14, 18, 22, 26, 30, 34, 38, 42, 46, 54) → snap to nearest `--space-*`
- ❌ Mixing rem and px without `--space-*` indirection → always token

---

## 4. Radius

### 4.1. 5 values only
| Token | Value | Usage |
|---|---|---|
| `--radius-sm` | 8px | tag, badge, input |
| `--radius-md` | 12px | button, small card |
| `--radius-lg` | 16px | card, modal, panel |
| `--radius-xl` | 24px | featured card, hero panel |
| `--radius-full` | 9999px | pill, avatar, circular icon |

### 4.2. Anti-patterns (from audit)
- ❌ 9 distinct radius values (12, 14, 16, 18, 20, 22, 24, 26, 28) → collapse to the 5 above
- ❌ Inconsistent radius across same component type (cards mixing 16px and 22px) → always same token per component class

---

## 5. Container

### 5.1. 3 widths only
| Token | Value | Usage |
|---|---|---|
| `--w-prose` | 720px | blog post, terms text |
| `--w-content` | 1280px | page content (default) |
| `--w-wide` | 1440px | hero, full-bleed marketing |

### 5.2. Anti-patterns (from audit)
- ❌ 8 max-widths (390, 430, 650, 680, 720, 780, 820, 860, 880) → use one of the 3 above

---

## 6. Shadow

| Token | Value | Usage |
|---|---|---|
| `--shadow-sm` | `0 1px 2px rgba(15,23,42,0.05)` | subtle lift |
| `--shadow-md` | `0 4px 6px rgba(15,23,42,0.08)` | card, button |
| `--shadow-lg` | `0 10px 15px rgba(15,23,42,0.10)` | modal, dropdown |
| `--shadow-xl` | `0 20px 25px rgba(15,23,42,0.15)` | hero card |

> **Rule:** Shadows use `--ink-900` base (not pure black) for warmer feel.

---

## 7. Motion

| Token | Value | Usage |
|---|---|---|
| `--motion-fast` | 150ms | micro feedback (color shift) |
| `--motion-base` | 200ms | hover, button press |
| `--motion-slow` | 300ms | modal, drawer |
| `--motion-page` | 400ms | page transition |
| `--easing-standard` | `cubic-bezier(0.4, 0, 0.2, 1)` | default |
| `--easing-emphasize` | `cubic-bezier(0.2, 0, 0, 1)` | modal in, drawer in |

### 7.1. Anti-patterns
- ❌ Duration > 500ms (feels sluggish)
- ❌ `transform: scale()` on layout containers (causes shift)
- ❌ Always-on animation without `prefers-reduced-motion` fallback

---

## 8. Z-index scale

| Token | Value | Usage |
|---|---|---|
| `--z-base` | 0 | default |
| `--z-dropdown` | 100 | select, autocomplete |
| `--z-sticky` | 200 | sticky header |
| `--z-overlay` | 800 | modal backdrop |
| `--z-modal` | 900 | modal panel |
| `--z-toast` | 1000 | toast, notification |
| `--z-tooltip` | 1100 | tooltip, popover |

---

## 9. Component Specs (token-only — adapt to React/HTML)

### 9.1. Buttons
```css
.btn-primary {
  background: var(--brand-500);
  color: var(--ink-0);
  padding: var(--space-3) var(--space-6);
  border-radius: var(--radius-md);
  font: var(--fw-semibold) var(--fs-base) / 1 Inter;
  min-height: 44px; /* touch target */
  transition: background var(--motion-base) var(--easing-standard);
}
.btn-primary:hover { background: var(--brand-700); }
.btn-primary:active { background: var(--brand-900); }
```

### 9.2. Card
```css
.card {
  background: var(--ink-0);
  border: 1px solid var(--ink-200);
  border-radius: var(--radius-lg);
  padding: var(--space-6);
  box-shadow: var(--shadow-md);
  transition: box-shadow var(--motion-base) var(--easing-standard),
              transform var(--motion-base) var(--easing-standard);
}
.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}
```

### 9.3. Input
```css
.input {
  padding: var(--space-3) var(--space-4);
  border: 1px solid var(--ink-300);
  border-radius: var(--radius-sm);
  font: 400 var(--fs-base) / 1.5 Inter;
  background: var(--ink-0);
  min-height: 44px;
  transition: border-color var(--motion-base);
}
.input:focus-visible {
  outline: 2px solid var(--brand-700);
  outline-offset: 2px;
  border-color: var(--brand-700);
}
```

### 9.4. Product card (laptop-specific)
- Image: 4:3 aspect, `--ink-50` placeholder bg, lazy load
- Title: `--fs-lg`, `--fw-semibold`, `--ink-900`, line-clamp 2
- Price: `--fs-xl`, `--fw-bold`, `--brand-700`, VND format
- Old price (sale): `--fs-sm`, `--ink-500`, line-through
- Warranty badge: pill, `--brand-50` bg, `--brand-700` text, `--fs-xs`, `--fw-medium`
- Stock state: dot + text (green/red), `--fs-sm`, `--ink-500`

---

## 10. Style Guidelines

**Style:** Trustworthy Retail Minimalism (overridden from skill's "Exaggerated Minimalism")

**Best for:** Mid-market B2C e-commerce, electronics retail, warranty-led value proposition

**Avoid:** Fashion/agency aesthetics (oversized hero type, statement design), playful/energetic tones, luxury cues

**Key Effects:**
- `--fs-2xl` page headings, NOT `--fs-4xl` (avoid fashion/agency feel)
- 8/16/24/32 spacing rhythm, NOT massive 96px+ gaps
- Single accent (`--brand-700`), NOT multi-accent palette

---

## 11. Page Patterns

### 11.1. Homepage
1. Hero (search + featured laptop grid)
2. Trust bar (warranty badge, payment options, store location)
3. Category chips (Dell, HP, Lenovo, ASUS, Macbook, Gaming)
4. Featured products (8 cards, 4×2 grid)
5. Recently viewed (horizontal scroll)
6. Blog teasers (3 latest posts)
7. Footer (4-col: about, support, policies, contact)

### 11.2. Product listing
1. Breadcrumb
2. Filter drawer (mobile) / sidebar (desktop)
3. Sort + view-mode toggle
4. Product grid (3-col desktop, 2-col tablet, 1.5-col mobile)
5. Pagination

### 11.3. Product detail
1. Breadcrumb
2. Image gallery (left) + info (right, sticky on desktop)
3. Spec matrix (CPU, RAM, SSD, screen, battery)
4. Description tabs (overview, specs, warranty, reviews)
5. Related products
6. Recently viewed

### 11.4. Blog post
1. Breadcrumb
2. Title + author + date
3. Hero image
4. Body (prose-width `--w-prose`)
5. Related posts

---

## 12. Anti-Patterns (Project-Specific)

- ❌ Two orange hex codes (`#d9480f` + `#f15a24`) — pick `#f15a24`
- ❌ 4 different blue accents (teal + cyan + blue + navy) — pick `--brand-700` (orange)
- ❌ 10 font-weights — use the 4 in §2.3
- ❌ Body text 10.9–14.4px — minimum 16px (`--fs-base`)
- ❌ 9 distinct border-radius — use the 5 in §4.1
- ❌ 8 max-widths — use the 3 in §5.1
- ❌ Hard-coded hex inline (`style={{ color: '#f15a24' }}`) — use `var(--brand-500)`
- ❌ Mixed icon families (Heroicons + Lucide + emoji) — pick Lucide React (already in bundle)
- ❌ Text icons (emojis) in buttons — use SVG Lucide

---

## 13. Pre-Delivery Checklist

Before shipping any UI:

- [ ] No emojis used as icons (use Lucide SVG)
- [ ] All colors via `var(--*)` — no inline hex
- [ ] Body text ≥ 16px (`--fs-base`)
- [ ] Heading uses one of `--fs-xl` to `--fs-4xl` only
- [ ] All clickable elements have `cursor: pointer`
- [ ] Touch targets ≥ 44×44px
- [ ] Text contrast ≥ 4.5:1 (verify `--brand-500` text is forbidden)
- [ ] Focus-visible ring on all interactive (`outline: 2px solid var(--brand-700)`)
- [ ] `prefers-reduced-motion` respected (gate `--motion-*` tokens)
- [ ] Responsive breakpoints: 375px, 768px, 1024px, 1440px
- [ ] No horizontal scroll on mobile
- [ ] Lucide icons only (consistent set)
- [ ] Run `python3 audit-bundle.py assets/index-*.css` before deploy — fail if > 5 colors, > 4 font-weights

---

## 14. Migration Roadmap (from audit 2026-07-26)

| Phase | Scope | Effort | Risk |
|---|---|---|---|
| **M1 — Tokens** | Add `:root` block from §1–§8 to `src/styles/tokens.css`; replace hex literals with `var(--*)`; consolidate font-weight | 3 days | Low — visual unchanged |
| **M2 — A11y** | Global `:focus-visible` ring; `cursor-pointer` audit; touch-target check; skip-link to `#root`; aria-labels on icon-only buttons | 2 days | Low |
| **M3 — Components** | Rewrite Button, Card, Input using tokens; remove `outline: none`; standardize 44px touch targets | 3 days | Medium |
| **M4 — Layout** | Sticky header, hero, breadcrumb, footer (4-col); container widths unified | 3 days | Medium |
| **M5 — Polish** | Loading skeleton, micro-animations, mobile sticky ATC; WCAG full audit | 2 days | Low |

**Workflow per phase:** Codex plan → DS/OpenCode implement → Codex review → fix → commit → push → Boss deploy → visual verify on Coolify.

---

## 15. References

- Skill source: `~/.hermes/skills/frontend/ui-ux-pro-max/`
- Audit script: `~/.hermes/skills/frontend/ui-ux-pro-max/scripts/audit-bundle.py`
- Re-audit CSS: `python3 audit-bundle.py /path/to/assets/index-*.css`
- Re-generate this MASTER (with same overrides): re-run with `--force` and re-apply edits above
- Prior plan: `/root/.hermes/plans/old-laptop-shop-design-improvements.md` (T1–T5 / P1–P5 plan, partially reverted)
- Prior plan: `/root/.hermes/plans/old-laptop-shop-quality-pass.md` (SEO + perf, still relevant)
