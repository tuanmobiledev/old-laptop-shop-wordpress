# Lịch sử dự án maytinhthuduc.com / OSCAR — 05/2026 → 11/2026

Nguồn: export Telegram `ChatExport_2026-11-08` (24 file HTML, ~24k tin nhắn, 12/05/2026–08/11/2026),
đọc toàn bộ và đối chiếu với git repo `old-laptop-shop-wordpress` (HEAD `d29f2e7`, 07/11/2026).

Lưu ý: số liệu dưới đây là bot Hermes tự báo trong chat; chưa kiểm chứng độc lập trên server.

---

## 1. Hạ tầng chốt

| Thành phần | Giá trị |
|---|---|
| Domain PROD | `https://maytinhthuduc.com` (Cloudflare, tunnel `a77d4be4…`) |
| Host | Coolify `100.80.205.76` = `coolify.tailb4338f.ts.net`, SSH `coolify-localhost@100.80.205.76` |
| PROD WP | container `wordpress-xqiz39ffoqvqos41xrggpb1h`, port 20080→80, DB `mariadb:10.6.4-focal` |
| TEST WP | container `wordpress-v23-test-wp` / `qp9fqz6loi1f8cojo3i3hkuc`, port 20089 |
| Registry | `ghcr.io/tuanmobiledev/wordpress-oscar` (public, GitHub Actions build) |
| Nguồn dữ liệu | Nhanh.vn v3 (`pos.open.nhanh.vn/v3.0`), appId 77885, businessId 226682 |
| Nguồn code | 2 tree: `old-laptop-shop-wordpress` (WP/theme/plugin) + `old-laptop-shop-src` (React SPA) |
| Plugin chính | `oscar-shop-core`, `oscar-nhanh-sync`, WooCommerce 10.9.4, Yoast 28.4, Site Kit |
| Mu-plugin | `oscar-seo.php`, `oscar-seo-front.php`, `oscar-whitelist-redirect.php`, `oscar-yoast-fix.php`, `oscar-blog-auto-poster.php`, `oscar-html-sitemap.php` |
| n8n | `coder-tuan-n8n` (WF04 blog auto-poster, đã chuyển sang bash + host cron) |

Luồng dữ liệu: Nhanh.vn → `oscar-nhanh-sync` (cron ~15 phút) → WooCommerce/meta `_nhanh_*` → `apply-specs` sinh `_oscar_*` → REST `/wp-json/oscar/v1/products` → React SPA.

---

## 2. Timeline

### 05/2026 — Khởi tạo
- Yêu cầu: web bán laptop cũ + phụ kiện + sửa chữa, mở rộng được sang điện thoại.
- Tạo React + Vite tại `old-laptop-shop`, đóng gói Docker + nginx, deploy `laprevive-web` port 18080.
- Đổi nhận diện: bỏ nhấn mạnh "cũ", màu `#1457B5`, rồi đổi tên TTP Solutions → **Laptop OSCAR Thủ Đức**.
- Cào đối thủ nextgold.vn (WooCommerce Store API, 67 SP), hienhuy.vn (KiotViet, không lấy được).
- Thêm PostgreSQL nhưng frontend vẫn static (chưa nối API) → sau bỏ DB khỏi compose.

### 06/2026 — WordPress + Nhanh.vn
- Dựng `old-laptop-shop-wordpress` (WP + MariaDB + WP-CLI), port 18081; nâng WP 6.8 → 6.9 cho WooCommerce.
- Deploy `abc.maytinhthuduc.com`, theme `oscar-shop` giữ bundle React cũ để giống 100% giao diện.
- Kết nối Nhanh.vn business "Laptop Oscar", depot 236102; plugin `oscar-nhanh-sync`; 82/82 SP gắn ID Nhanh.
- Đẩy ảnh WooCommerce → Nhanh (82 SP, 146 URL).
- Audit nâng cấp 82 SP (51 RAM thay được / 24 hàn / 6 hàn một phần), publish 23 addon (11 RAM-SSD, 8 dịch vụ, 4 bảo hành).
- Đổi domain `abc.` → `maytinhthuduc.com` (SQL siteurl + replace 892 URL), xoá 105 SP cũ.
- **Sự cố 26/07: PATCH compose Coolify làm recreate container → mất DB volume (167 SP).** Phục hồi từ local + script `recover-production.sh`. Bài học: không PATCH compose trên PROD, phải build image → push GHCR → update service.

### 07/2026 — Nguồn dữ liệu Nhanh là duy nhất
- Chốt: sản phẩm chỉ lấy từ Nhanh API, bỏ JSON cache, cron 15 phút (list → so `updatedAt` → detail → upsert).
- Parser spec (cpu/ram/ssd/screen/gpu/demand/battery/warranty) từ content Nhanh; `apply-specs` ghi meta `_oscar_*`.
- Blog: tách DB riêng `oscar-blog-db` (MySQL 8, port 3307) + plugin `oscar-blog`; sau đó **xoá hẳn OSCAR Blog** vì ngoài git và từng gây 500.
- Hardening Cloudflare: Always HTTPS, Brotli, 5 security header, WAF 6 path, rate limit `/wp-login.php`, cache rule (TTFB 1300ms → ~200ms).
- Deploy bằng GitHub Actions vì VPS bị cap bandwidth outbound khi push layer lớn.
- Sự cố lặp: Coolify PATCH thêm 1 prefix UUID vào volume mỗi lần (DOUBLE → TRIPLE → QUADRUPLE → QUINTUPLE) → volume rỗng → `install.php`. Khắc phục: rsync volume cũ sang volume mới.
- 27/07: image `v3-prod`, bundle v8 → v13; SPA `productMediaMap` chứa URL `abc.` chết.

### 08/2026 — SPA, SEO, GA4, n8n
- Refactor `main.jsx` thành component (`ProductCard`, `ProductDetailPage`, `SearchAutocomplete`, `utils.js`), 1118 → 659 dòng.
- GA4 qua Site Kit (`G-F3JDDL0G2P`), GTM `GT-MKBFQLQ3`; SPA tracking qua `window.gtag` (không load gtag.js lần 2).
- SEO: `oscar-seo-front.php` (meta unique, canonical, Organization/LocalBusiness/BreadcrumbList), sitemap 6 sub, scrub số điện thoại cũ 0523 → 0984 trên DB + Nhanh.
- Image v14 → v24 (deploy qua Coolify API), sau đó v30 → v47.6.
- Blog: `single.php` riêng, TOC, share, related, 4 category (Đánh giá / Tư vấn / Kiến thức / Sử dụng), 12 bài stub được viết lại.
- WF04 (n8n → bash): 1 bài/ngày + share Facebook, picker tránh lặp 14 ngày, prompt cấm chê máy/so sánh, ảnh lấy từ gallery sản phẩm.
- UI/UX: token hoá CSS (697 replacement), sửa radius 9999px, CLS, bottom nav, FAB stack, filter mobile.
- **Bug kinh điển:** 2 `<main>` do `?ver=` tách module React 19 → React mount 2 lần; fix bỏ `?ver=`.

### 09/2026 — Google index, canonical, GSC
- Root cause index kém: mỗi PDP phát 3 canonical (1 self + 2 về `/`), `og:url` sai, `/product/<slug>/` 301 ngược.
- Fix 3 tầng trong `functions.php` + `oscar-seo.php` + `oscar-whitelist-redirect.php`; sau fix: 8/8 PDP còn 1 canonical.
- `formatTel()` thiếu `tel:` → CTA "Gọi ngay" thành URL tương đối, 302 về home (Googlebot crawl 25 lần).
- Product card là `<article role="button">` → 0 internal link; thêm `<a href>` thật.
- Sự cố: PATCH Coolify tạo volume mới → mất 24 blog + 89 product; phục hồi bằng rsync volume cũ.
- Indexing API chứng minh vô dụng (200 nhưng 404 metadata) → chỉ dùng sitemap + internal link.
- Audit 60 render: 63% multi-H1; fix scope H1 `sr-only` theo route.

### 10/2026 — Nội dung + SEO
- Chuẩn hoá format Nhanh: `description` = prose + 10 bullet, `content` = HTML bài viết; 99/99 SP.
- Thêm bài viết vào PDP (route `product-article`), sửa parser `extract_specs()` đọc cả content + description.
- Sitemap sạch: 96 PDP, loại taxonomy chết, `lastmod` cũ (10 SP tháng 10 index 50%, 86 SP tháng 9 index 2,3%).
- Phát hiện: `/san-pham/` chỉ 12/96 SP, phân trang là `<button>` → Googlebot không bò được; 84 SP chỉ có trong sitemap.
- TEST bị user `phuong` xoá qua Coolify API 04/11 23:06 (cùng n8n, portainer, mattermost…).

### 11/2026 — Release, 100 bài viết, SEO cuối
- 5/11: TEST-first, sửa share button (`navigator.clipboard` undefined khi HTTP), drawer lọc mobile, ảnh PDP, sync 101 SP.
- 5/11 22:31 deploy PROD: `index-CRvLNl_x.js` + `index-T142lIHk.css` + plugin parser.
- 6/11: viết **99 bài** cho 100 SP (1.876–3.226 từ, 13–14 H2, bảng, 2 ảnh, 5–10 internal link), publish PROD (posts 46 → 145).
- 6/11: sort "Nổi bật" ưu tiên còn tồn kho; sync đối chiếu SKU; content rỗng → draft.
- 7/11: `/blog/` thành trang thật (146 bài), `/tat-ca-trang/` hub 246 link, footer trỏ hub, merchant listing schema, bỏ author sitemap, 1 meta description/trang.
- 7/11: quyết định bỏ tối ưu ảnh/CWV (dựa Cloudflare cache), bỏ dải link trước footer, không thêm `aggregateRating` giả.

---

## 3. Sự cố lớn & bài học

1. **Wipe volume do PATCH Coolify** (26/07 và 20/09): PATCH `docker_compose_raw` tạo volume mới có prefix dài hơn. Luôn build image → push GHCR → update image tag; snapshot compose trước; `wp db export` trước mọi thao tác.
2. **Deploy nhầm host** (26/07, 22/07): sửa/test trên local dev hoặc container khác trong khi PROD ở `100.80.205.76`. Phải verify container ID trước khi `docker cp`.
3. **Deploy PROD không xin phép** (25/08): vi phạm quy trình; từ đó chốt rule TEST → test → báo Boss → duyệt → PROD.
4. **Mất fix do volume che image**: volume `/var/www/html` thắng image → `wp-init.sh` phải `rsync` không `--update` hoặc bump hash bundle.
5. **Cloudflare cache stale**: HTML cache 1h/4h, không có CF API token → đổi hash bundle để bypass, hoặc purge tay.
6. **Lazy chunk thiếu** → ErrorBoundary "Đã xảy ra lỗi": mọi lần đổi route phải ship đủ chunk.
7. **Nhanh `product/edit` là replace**: thiếu `name` hoặc `code` → field kia NULL. Luôn gửi combo `{id, name, code}` và fetch detail trước.

---

## 4. Bug & root cause đáng nhớ

| Triệu chứng | Nguyên nhân gốc | Fix |
|---|---|---|
| Nút chia sẻ im lặng | `navigator.clipboard` undefined trên HTTP | fallback `textarea + execCommand` |
| 2 `<main>`, trang render 2 lần | `?ver=` tách module identity React 19 | bỏ query string khỏi asset |
| Không scroll được PDP mobile | body lock + CSS override `overflow:visible` | media query scroll trong card, bỏ lock |
| Ảnh thumb vỡ | `loading="lazy"` + srcset WP sai size | bỏ srcset, priority mặc định |
| Phụ kiện hiện spec laptop | category legacy + fallback `8GB/256GB` | heuristic `!cpu && !ram && !ssd && !screen` |
| Sản phẩm lệch ảnh | OSCAR-1021 thiếu trong DB → off-by-one | re-map từ REST |
| `_oscar_ssd=32GB` | parser bắt "32GB RAM" trước "512GB SSD" | regex yêu cầu token SSD/NVMe/M.2 |
| Canonical trùng 3 thẻ | Yoast + theme + mu-plugin cùng in | suppress theo route |
| 0 internal link | card là `role=button` | thêm `<a href>` |
| Drawer lọc hở 18% | 4 khối `@media` override chồng nhau | grid 3 hàng, `inset:0`, `100dvh` |
| Bài viết dồn 1 cục | content dùng `<br>` thay HTML chuẩn | convert sang h2/h3/p/ul |
| Blog lặp đề tài | picker skip theo slug, không theo dòng máy | group theo product line (chưa làm) |
| Sync ảnh 0 | uploads do root sở hữu | `chown -R www-data:www-data uploads` |

---

## 5. Quyết định kiến trúc / kinh doanh

- Nhanh.vn là **nguồn duy nhất** cho sản phẩm; web chỉ nhận, map, hiển thị.
- Không tạo biến thể RAM/SSD trong Nhanh (tồn linh kiện chuyển đổi được) → máy nền + linh kiện rời.
- Mọi sản phẩm hiển thị kể cả tồn 0; khách chốt qua Zalo/hotline.
- Giữ Yoast (không tự sinh schema trùng), giữ Site Kit/GTM hiện có.
- Không bịa `aggregateRating`/`review` khi chưa có đánh giá thật.
- Bỏ tối ưu ảnh/CWV (dựa Cloudflare cache); bỏ soft-404 fix, `<h1>` server-side, alt text ảnh bài.
- `/blog/` là trang thật (không 301 về `/#blog`); hub `/tat-ca-trang/` 246 link.
- TEST-first bắt buộc; mọi `docker cp` PROD phải có "ok" rõ ràng.

---

## 6. Trạng thái 07/11/2026

Đã xong trên PROD: 100 SP, 146 bài viết, `/blog/` + `/tat-ca-trang/`, merchant listing schema, sitemap 4 sub, 1 meta description/trang, sort theo tồn kho, PDP có bài viết.

Việc còn treo:
1. Chờ Google index 146 bài + hub (3–7 ngày); Indexing API vô dụng.
2. Phân trang `/san-pham/` vẫn là `<button>` → Googlebot không bò; 84/96 PDP chỉ có trong sitemap.
3. `lastmod` sitemap cũ (Yoast dùng `post_modified`); filter `wpseo_sitemap_entry` chưa áp.
4. Soft 404: URL lạ 302 → `/` (quyết định cũ 25/08).
5. `<h1>` thiếu trong HTML server ở home/PDP; alt ảnh bài viết thiếu.
6. CSP mới report-only, chưa enforce.
7. 183 ảnh bài viết còn hotlink `pos.nvncdn.com`.
8. Dữ liệu Nhanh lệch: OSCAR-1074 slug/ảnh, 4 ThinkPad thiếu "Gen N", OSCAR-1099 slug `1tb-ssd`, OSCAR-1101 còn `13.3 inch`.
9. OSCAR-1073 mồ côi; OSCAR-854 (chuột) layout cũ.
10. Không có CF API token trên máy → purge tay.

---

## 7. Rủi ro bảo mật (cần xử lý)

- Nhiều secret từng dán vào chat và bị log: Coolify token, Nhanh token, WP app password, GitHub PAT, Cloudflare token, Figma token, Google OAuth. **Chưa xác nhận đã rotate hết.**
- Coolify token "root key" id 9 vẫn active, có quyền xoá PROD; user `phuong` (hoangphuong072@gmail.com) đã xoá TEST WP + n8n + portainer + mattermost ngày 04/11 23:06 → cần xác minh danh tính và thu hồi token.
- `wp-content/debug.log` từng trả 200; đã chặn bằng `.htaccess` 403.
- TEST admin dùng chung mật khẩu với PROD ở một thời điểm.
- Không có backup tự động DB + uploads cho PROD (đề xuất nhiều lần, chưa chốt).

---

## 8. Cách đọc lại nguồn

- Export gốc: `ChatExport_2026-11-08.rar` (24 file `messages*.html`, 12/05–08/11/2026).
- Bản text đã lọc nhiễu: `tg-clean/part01..part24.txt` (~7,2 MB).
- Repo code: `old-laptop-shop-wordpress` (HEAD `d29f2e7`) + `old-laptop-shop-src`.
