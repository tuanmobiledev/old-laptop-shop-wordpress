# Đồng bộ ngược: PROD → GIT
**Date:** 2026-07-31  
**Trigger:** Test site (port 38080, fresh deploy từ image v14) không có 1 số routes của prod  

## 1. State comparison

| | PROD (maytinhthuduc.com) | TEST (port 38080) | GIT (v14) |
|---|---|---|---|
| WP generator (header) | 7.0.2 | 6.9.4 | 6.9.4 (Dockerfile) |
| Server | cloudflare | Apache/2.4.67 (Debian) | Apache |
| `/wp-json/` permalink | 200 OK | 404 (need `index.php?rest_route=`) | n/a (rewrite) |
| Theme | oscar-shop | oscar-shop | oscar-shop |
| SPA bundle JS | `index-C3-zq1DJ.js` | `index-C3-zq1DJ.js` ✅ | `index-C3-zq1DJ.js` |
| SPA bundle CSS | `index-B0pdhb1P.css` | `index-B0pdhb1P.css` ✅ | `index-B0pdhb1P.css` |
| Total WP routes | 693 | 679 | n/a (routes runtime) |
| Oscar routes | 20 | 18 (+legacy_cleanup) | 17 (cleaned) |

> **Update 2026-08-05:** Hashes ở bảng trên đã outdated. Active bundles hiện tại (sau cleanup 22 old bundles, commit `0a76eb5` + `1ad135e`):
> - JS: `index-QnStEE5a.js`
> - CSS: `index-lVXjSW02.css`
> - Lazy: `AdminProductsPage-BbHTIpP2.js`, `SalesPolicyPage-BdYF1olY.js`

## 2. Routes chỉ có trong PROD (KHOẢN CÁCH CẦN ĐỒNG BỘ)

### 2.1. Helper plugin — hoàn toàn CHƯA có trong git
Namespace: `helper/v1` (0 routes trong test, 10 routes trong prod)

| Route | Method | Auth | Note |
|---|---|---|---|
| `/helper/v1/blog-asset/check` | GET→401, POST? | yes | blog asset deploy check |
| `/helper/v1/blog-asset/deploy` | POST | yes | blog asset deploy |
| `/helper/v1/blog-meta` | POST | yes | blog-meta (no GET) |
| `/helper/v1/blog-meta-v2/list` | POST | yes | blog meta v2 list |
| `/helper/v1/blog-meta-v2/seed` | POST | yes | blog meta v2 seed |
| `/helper/v1/blog-meta/inspect` | POST | yes | inspect existing meta |
| `/helper/v1/blog-meta/verify` | POST | yes | verify after seed |
| `/helper/v1/cron` | ? | yes | run cron |
| `/helper/v1/deploy-v2/write` | POST | yes | deploy v2 writer |
| `/helper/v1` | GET | public | namespace index |

### 2.2. Oscar extensions — git có plugin nhưng thiếu routes
| Route | Method | Plugin in git | Note |
|---|---|---|---|
| `/oscar/v1/nhanh/_debug_cron` | GET | oscar-nhanh-sync | git has config/sync/status/_legacy_cleanup, missing _debug_cron |
| `/oscar/v1/specs/apply` | POST | oscar-product-specs (mu-plugin) | git has search/filter/facets, missing specs/apply |
| `/oscar/v1/upgradeability/apply` | POST | unknown — không có trong plugins/mu-plugins git source | needs separate file |

### 2.3. WP core extra
- `/wp/v2/icons` + `/wp/v2/icons/(?P<name>[a-z][a-z0-9-]*/[a-z][a-z0-9-]*)` — site icons endpoint, WP 6.5+ feature
- Wp 7.0.2 generator (prod) vs 6.9.4 (git). PROBABLY Oscar relabels generator via mu-plugin — not actual WP version. Need to verify by checking source.

## 3. Critical ENV state differences (KHOẢN CÁCH KHÔNG THUỘC CODE)

| | PROD | TEST |
|---|---|---|
| Pretty permalinks | ✅ enabled (`/wp-json/`, `/wp/v2/posts` work) | ❌ disabled (must use `index.php?rest_route=`) |
| `/oscar/v1/nhanh/_legacy_cleanup` | ❌ not registered (cleanup ran) | ✅ registered (fresh install) |
| `wp_post_count=10` (prod posts) vs `wp_post_count=2` (test posts, just fresh) | content not in scope here |  |

## 4. CẦN GÌ ĐỂ HOÀN THÀNH SYNC

Để pull code 4 routes/prod source về git, cần Boss cung cấp 1 trong:

**Option A (preferred):** Re-issue Coolify API token + URL → dùng Docker exec hoặc WP admin app-password gọi `wp plugin get` / `wp eval-file` trên prod container để extract source code 3 file PHP.

**Option B:** SSH access to prod container (even if temporary).

**Option C:** Boss chạy manual command trên prod server rồi paste output:
```bash
# Trong prod container
cat /var/www/html/wp-content/plugins/oscar-*/**/*.php > /tmp/oscar_plugins.txt
cat /var/www/html/wp-content/mu-plugins/*.php > /tmp/mu_plugins.txt
ls -la /var/www/html/wp-content/plugins/ /var/www/html/wp-content/mu-plugins/
```
→ paste `/tmp/oscar_plugins.txt` + `/tmp/mu_plugins.txt` + `ls -la` output.

## 5. Sau khi có source, plan:
1. Apply patches to git:
   - `/root/old-laptop-shop-wordpress/wp-content/plugins/oscar-nhanh-sync.php` — thêm route `register_rest_route('oscar/v1', '/nhanh/_debug_cron', ...)`
   - `/root/old-laptop-shop-wordpress/wp-content/mu-plugins/oscar-product-specs.php` — thêm route `register_rest_route('oscar/v1', '/specs/apply', ...)`
   - `/root/old-laptop-shop-wordpress/wp-content/plugins/oscar-upgradeability/oscar-upgradeability.php` — file mới cho `register_rest_route('oscar/v1', '/upgradeability/apply', ...)`
   - Helper plugin: nếu Option C → tạo mới `/root/old-laptop-shop-wordpress/wp-content/plugins/oscar-helper/` với code từ prod
2. Update `wp-init.sh`:
   - `wp rewrite set --allow-root ...` (set permalink to `/%postname%/`)
   - `wp rewrite flush --allow-root`
   - `wp theme activate oscar-shop --allow-root`
   - Conditional: skip `wp plugin activate ...` if already active
3. Update Dockerfile to COPY plugin source + add `composer install` if needed
4. Rebuild image as `v15-helper-plugin-sync-2026-07-31`
5. Redeploy on test site (port 38080)
6. Verify route counts now match prod: helper=10, oscar=20 (test+legacy_cleanup, prod-cleaned), all matches.

## 6. Out of scope (ghi để ý nhưng không diff):
- Database content (posts, products, options) — prod có Nhanh sync state, test fresh. KHÔNG đồng bộ DB về git vì git chỉ code.
- Env vars (`NHANH_APP_ID`, `NHANH_BUSINESS_ID`, `NHANH_DEPOT_ID`) — test dùng placeholder 103786, prod dùng thật. Boss tự cấu hình khi deploy từ `.env` riêng.
- wp_options (`blogname`, `siteurl`, `admin_email`) — config runtime, không thuộc git.

---

## 7. SPA bundle cleanup — 2026-08-05

**Trigger:** `wp-content/themes/oscar-shop/assets/` tích lũy 22 old bundle files (~3.6MB) từ các deploy trước, không còn ai reference.

### 7.1. Files deleted (22)
| Type | Count | Active sau cleanup |
|---|---|---|
| Main JS (`index-*.js`) | 7 → 1 | `index-QnStEE5a.js` |
| Main CSS (`index-*.css`) | 3 → 1 | `index-lVXjSW02.css` |
| Lazy chunk (`AdminProductsPage-*.js`) | 6 → 1 | `AdminProductsPage-BbHTIpP2.js` |
| Lazy chunk (`SalesPolicyPage-*.js`) | 6 → 1 | `SalesPolicyPage-BdYF1olY.js` |
| Fonts (`*.woff2`) | 9 (giữ) | referenced by active CSS |
| Other (`crawled-products/`, `images/`) | giữ | data dirs |

### 7.2. Verification
- **`functions.php`** chỉ reference 2 active hashes (`index-QnStEE5a.js`, `index-lVXjSW02.css`) — không bao giờ load files cũ.
- **No references** in any PHP plugin (`oscar-shop-core`, `oscar-nhanh-sync`, `blog-asset-deploy`, `deploy-v2`, `cron-helper`, `blog-meta-*`) — checked toàn bộ.
- **No DB references** — `get_option()` / `update_option()` keys không chứa bundle hash.
- **No cron references** — `wp_schedule_event` không reference bundle hashes.
- **WP REST API** (`/wp/v2/posts?search=...`) — 0 posts/pages reference old hashes.

### 7.3. Deploy
- Local git: WP tree `0a76eb5`, src tree `1ad135e` (last commit thêm `dist-test/` vào `.gitignore`).
- Production server: deploy qua plugin `blog-asset-deploy` + `deploy-v2` (REST endpoints, dùng WP app password).
- Test environment: `http://192.168.11.74:4173/` (vite preview local) đã verify HTTP routes + bundle loads.

### 7.4. Files sẽ xóa trên server (chưa deploy)
22 old bundle files (giống local). Plugin `blog-asset-deploy` chỉ có `write` endpoint, không có `delete`. Cần:
- **Option A:** Upload one-shot PHP script qua `deploy-v2` rồi gọi `curl` để chạy.
- **Option B:** Bỏ qua — files vẫn còn trên server nhưng không ai load, total impact: 3.6MB disk + 22 entries trong directory listing. KHÔNG ảnh hưởng runtime/SEO/page speed.
