# Page Override — Product Listing (`#product-listing`)

> Overrides `MASTER.md` for this specific page. Other rules from Master apply unless
> explicitly overridden below.

---

**Route:** `#product-listing`
**Components in scope:** `FilterSidebar`, `SortDropdown`, `ProductGrid`, `ProductCard`, `Pagination`, `MobileFilterSheet`
**Last updated:** 2026-08-05

---

## Page-specific rules

### Page layout
- Max-width: **1400px** (wider than Master default 1200 — listing needs more cards per row)
- Layout: **Sidebar 240px + Content fluid** on desktop ≥1024px
- Sidebar collapses to bottom-sheet on mobile <1024px
- Page padding-top: **96px** (below sticky header)
- Page padding-bottom: **64px** (above mobile sticky filter bar)

### Sticky filter bar
- Position: `sticky top: 64px` (below main header), z-index `--z-sticky`
- Background: `--ink-0` with `--shadow-sm` on scroll
- Contains: result count (left) + sort dropdown + view toggle (grid 2x2 / 3x3 / list) (right)
- Height: 56px

### Filter sidebar (desktop ≥1024px)
- Width: **240px fixed** (not Master's 280px default)
- Sections (collapsible accordions, all expanded by default):
  1. **Danh mục** (Categories) — radio list with count badge
  2. **Thương hiệu** (Brands) — checkboxes with count
  3. **Giá** (Price range VND) — dual-thumb slider, min 0 / max 50tr
  4. **CPU** — checkboxes (i3/i5/i7/i9/Ryzen 3/5/7/9)
  5. **RAM** — checkboxes (4/8/16/32 GB)
  6. **Ổ cứng** (Storage) — checkboxes (SSD 128/256/512GB, HDD 1TB)
  7. **Màn hình** (Screen size) — checkboxes (13"/14"/15"/17")
  8. **Card đồ họa** (GPU) — checkboxes (Integrated/NVIDIA/AMD)
- Footer button: **"Xóa bộ lọc"** (clear all) + **"Áp dụng"** (apply)
- All filters persist in URL query params (shareable link)

### Sort dropdown
- Options (Vietnamese labels):
  - Mới nhất (default) — by created_at desc
  - Giá tăng dần — price asc
  - Giá giảm dần — price desc
  - Tên A-Z — title asc
  - Bán chạy — by sales_count desc
- Lucide `ChevronDown` icon, 16px
- Open: dropdown with `--shadow-lg`, max-height 320px, scrollable

### Product grid
- Desktop ≥1280px: **4 columns** (`grid-template-columns: repeat(4, 1fr)`)
- Desktop 1024–1279px: **3 columns**
- Tablet 768–1023px: **2 columns**
- Mobile <768px: **2 columns** (compact cards, 160px gap)
- Grid gap: **20px** (not Master's 24px — 4-col needs tighter spacing)
- Gap uses **vertical alignment: stretch** (cards same height)

### Product card (laptop-specific)
- **Image:** 4:3 aspect ratio, max-height 220px, object-fit cover
  - Hover: scale 1.05, 300ms `--motion-duration-base`
  - Lazy load all product images (use Intersection Observer)
- **Warranty badge:** absolute top-left of image, pill `px-2 py-1`, `--brand-50` bg, `--brand-700` text, `--fs-xs`
  - Example: "Bảo hành 12 tháng"
- **Stock state:** absolute top-right, Lucide `Circle` 8px dot + text
  - In stock: `--success` (also "Còn hàng")
  - Low stock (≤3): `--warning` + "Sắp hết"
  - Out of stock: `--danger`, card opacity 0.6 + disable hover
- **Title:** 2 lines max, `--fs-base`, `--fw-medium`, `--ink-900`, line-clamp via `-webkit-line-clamp: 2`
- **Price:** `--fs-lg`, `--fw-bold`, `--brand-700`, format VND (1.290.000 ₫)
- **Original price (if discounted):** strikethrough `--ink-400`, `--fs-sm`
- **Card hover:** `--shadow-md` + image scale, 200ms
- **Padding:** 12px (compact, not Master's 16px)
- **Background:** `--ink-0`
- **Border:** 1px `--ink-200`, radius `--radius-md`
- **Vertical info height:** 88px fixed (image height + info = card consistent)

### Category chips (mobile + horizontal scroll)
- Horizontal scroll pills, 12px gap
- Pill inactive: `--ink-100` bg, `--ink-700` text
- Pill active: `--brand-500` bg, `--ink-0` text
- Height: 36px, padding-x: 16px, radius: 999px (full round)
- Active category scrolls into view on load

### Pagination
- Show: numbered pages + prev/next + first/last
- Layout: centered below grid, margin-top 48px
- Button inactive: 40px × 40px, `--ink-100` bg, `--ink-700` text
- Button active: same dim, `--brand-500` bg, `--ink-0` text
- Button disabled: opacity 0.4, cursor not-allowed
- Gap: 4px
- **No infinite scroll** — Boss prefers deterministic pagination (per P1 convention)
- Show "Trang X / Y" above pagination, `--fs-sm`, `--ink-500`

### Empty state
- Centered, vertical layout, 200px height
- Lucide `PackageX` icon, 64px, `--ink-300`
- Heading: "Không tìm thấy sản phẩm" `--fs-lg`, `--ink-700`
- Subtext: "Thử điều chỉnh bộ lọc hoặc từ khóa" `--fs-base`, `--ink-500`
- CTA: "Xóa bộ lọc" button secondary

### Loading skeleton
- Card skeleton: same dimensions as real card
- Image: `--ink-100` shimmer, 600ms loop
- Text lines: 3 bars, 80%/60%/40% widths, `--ink-100`
- Show 12 skeletons (one full grid)
- Shimmer: `@keyframes shimmer { 0% { bg-position: -200px } 100% { bg-position: +200px } }`

### Mobile filter bottom sheet (<1024px)
- Trigger: sticky bottom bar with "Bộ lọc" button + "Sắp xếp" button
- Bar height: 64px, full width, `--ink-0` bg, `--shadow-xl` top
- Filter sheet: full-height slide-up from bottom, drag-down-to-dismiss
- Header: "Bộ lọc" title (left) + close X (right)
- Same accordion sections as desktop sidebar
- Footer: "Xóa" (ghost) + "Áp dụng (N kết quả)" (primary)
- Safe-area-inset-bottom for iPhone home indicator

---

## Page-specific components

| Component | Status | Notes |
|---|---|---|
| `FilterSidebar` | To build | Accordion pattern, URL sync via `useSearchParams` |
| `SortDropdown` | To build | Popover, closes on outside click + Esc |
| `ProductCard` | To build | Hover scale, warranty badge, stock dot |
| `Pagination` | To build | Reusable, accepts page + total pages |
| `MobileFilterSheet` | To build | Vaul or custom slide-up, drag dismiss |
| `CategoryChips` | To build | Horizontal scroll, auto-scroll active into view |

---

## Page anti-patterns

- ❌ **Modal filter** (use sidebar / bottom sheet — modal blocks too much)
- ❌ **Auto-apply filter on change** (always require explicit "Áp dụng" click on mobile)
- ❌ **Infinite scroll** (Boss rule: deterministic pagination for SEO + UX)
- ❌ **Hide cards behind "Load more" without showing total count** (always show "X sản phẩm")
- ❌ **Sort by "popularity" without definition** (use sales_count or view_count, document which)
- ❌ **Filter chips duplicate sidebar** (one source of truth, chips only on mobile for category)
- ❌ **Card click anywhere = PDP** (only image + title are clickable, buttons not nested)
- ❌ **"New" badge without expiry date** (max 30 days from created_at)
- ❌ **Discount % badge** (we show original price strikethrough only, no % off — Boss 2026-07-28)

---

## URL structure (SEO)

```
/san-pham                              → all products, default sort
/san-pham?category=laptop-cu           → filtered by category
/san-pham?brand=dell&price=5-15        → multiple filters
/san-pham?sort=price-asc&page=2        → + sort + pagination
/san-pham/<slug>                       → redirects to /#product-detail?slug=...
```

- Canonical URL: include ONLY category filter (not brand/price, those change more)
- `robots.txt`: allow `?page=` (pagination discovery), disallow `?sort=` (duplicate content)
- Meta title: "Laptop cũ giá rẻ - maytinhthuduc.com" (default) + suffix on filtered (e.g. "Laptop Dell")

---

## Performance budget

| Metric | Target | Why |
|---|---|---|
| LCP | <2.5s | First product card visible |
| Cards in initial viewport | 8 max | Above-fold products only |
| Image format | WebP | All product images, AVIF for hero only |
| Filter apply latency | <300ms | Perceived as instant |
| Total JS for this page | <80KB | Exclude main bundle |
| API: products list | Cached 5min | `wp-json/oscar/v1/products?page=N` |

---

## Components not yet designed

These will get a page-specific override when built:
- `ProductListing/compare/` (side-by-side comparison, future)
- `ProductListing/saved/` (wishlist, future)
- `ProductListing/quick-view/` (modal preview from card, P4)
