# Page Override — Product Detail (`#product-detail`)

> Overrides `MASTER.md` for this specific page. Other rules from Master apply unless
> explicitly overridden below.

---

**Route:** `#product-detail`
**Components in scope:** `ProductDetail`, `ImageGallery`, `SpecMatrix`, `StickyATC` (mobile)
**Last updated:** 2026-08-05

---

## Page-specific rules

### Image gallery
- Aspect ratio: **4:3** (laptop product shots — landscape, not square)
- Main image: max-width 720px, centered
- Thumbnail strip: 5 thumbs × 80×60px, horizontal scroll on mobile
- Active thumb: 2px `--brand-500` border, others `--ink-200`
- Lazy load below-fold thumbs; preload first 2
- Placeholder bg: `--ink-50`, Lucide `ImageOff` icon (24px, `--ink-300`)

### Sticky info panel (desktop ≥1024px)
- Right column sticky at `top: 80px` (below header)
- Width: 480px (not full content width)
- Contains: title, price, warranty badge, variant picker, ATC button
- Background: `--ink-0` with `--shadow-md` on scroll

### Spec matrix
- 2-column table (label | value)
- Label: `--ink-500`, `--fs-sm`, `--fw-medium`
- Value: `--ink-900`, `--fs-base`, `--fw-regular`
- Row hover: `--ink-50` bg
- Border-bottom: 1px `--ink-200`
- Group header (CPU, RAM, SSD): full-width, `--ink-100` bg, `--fw-semibold`

### Mobile sticky ATC (375–767px)
- Fixed bottom: 0, full width, `--ink-0` bg, `--shadow-xl`
- 2 buttons side-by-side: "Gọi tư vấn" (secondary) + "Thêm giỏ hàng" (primary)
- Height: 64px
- Safe-area-inset-bottom padding for iPhone home indicator

### Trust elements
- Warranty badge above price: pill, `--brand-50` bg, `--brand-700` text
- Stock state: dot (8px) + text
  - In stock: `--success`
  - Low stock (≤3): `--warning`
  - Out of stock: `--danger`, ATC disabled
- Store pickup: line of text with Lucide `MapPin` icon, link to contact page

---

## Page anti-patterns

- ❌ "Mua ngay" CTA at top of page (premature commitment, violates P1 P3 rule)
- ❌ Hide price behind "Đăng nhập để xem giá" (we're B2C, price must be public)
- ❌ Auto-play video specs (annoying + bandwidth on mobile)
- ❌ Gallery zoom-on-hover (iOS Safari flaky; use tap-to-zoom modal)
- ❌ Infinite scroll on related products (pagination only — Boss prefers deterministic)

---

## Components not yet designed

These will get a page-specific override when built:

- `ProductDetail/reviews/` (currently stub)
- `ProductDetail/qna/` (Q&A accordion, not in current scope)
- `ProductDetail/bundle-deal/` (cross-sell, future)
