# Deploy OSCAR Shop WordPress to production

> **📘 Fresh deploy?** Đọc [`FRESH_DEPLOY.md`](./FRESH_DEPLOY.md) trước — đó là checklist step-by-step từ clone repo đến server mới giống 100% prod.

## Trạng thái tính 2026-08-11 (HEAD `2b9d91e`, post-cleanup -638 lines dead code)

| Thành phần | Giá trị thực tế trên prod | Ghi chú |
|---|---|---|
| Image | `ghcr.io/tuanmobiledev/wordpress-oscar:v15-cleanup-2026-08-11` | Tag = LABEL trong Dockerfile.wp (đồng bộ 2026-08-11). Khi build mới phải bump cả LABEL lẫn tag push theo cùng pattern `vN-prod-YYYY-MM-DD-mô tả`. |
| DB image | `mariadb:10.6.4-focal` | Repo **CŨNG** dùng `mariadb:10.6.4-focal` từ 2026-07-30 (trước: `mariadb:11.4`) |
| DB credentials | `MYSQL_PASSWORD=wordpress` (user `wordpress`) | Coolify default placeholder — **CHƯA rotate**. Xem "Security notes" bên dưới. |
| WP env vars | `WORDPRESS_DB_*=wordpress`, `WORDPRESS_DB_NAME=wordpress` | Mount từ Coolify service config |
| DB volume | `xqiz39ffoqvqos41xrggpb1h_db-data` | Named volume (Coolify tự tạo) |
| Uploads volume | `xqiz39ffoqvqos41xrggpb1h_wp-data` | ~1.46 GB, **không** trong repo |
| Coolify service UUID | `xqiz39ffoqvqos41xrggpb1h` | Lưu trong `COOLIFY_APP_UUID` |
| Coolify host | `100.80.205.76` (internal Hermes network) | SSH key: `/tmp/coolify_key` (đổi tên từ `coolify-prod-key` 2026-08-11) |
| WP admin user/pass | `admin` / `WORDPRESS_OSCAR_PASSWORD` (lưu trong `/root/.secrets/user-secrets.env`) | env var đúng tên `WORDPRESS_OSCAR_USER` (không có NAME suffix). `.env` local dùng giá trị này để mirror prod |
| Active plugins | blog-meta-v2, cron-helper, deploy-v2, oscar-nhanh-sync, oscar-shop-core | 5 plugin (giảm từ 8 sau cleanup 2026-08-11). WooCommerce + akismet/hello là WP default. |
| Products | 88 | Sync từ Nhanh qua Hermes cron 15 phút (parser-driven, 5a99b69). Stock KHÔNG sync (Boss quyết định 2026-08-11: show hết Nhanh products). |
| Blog posts (`post_type=post`) | ≥ 1 | SPA bundle fetch qua `/wp-json/wp/v2/posts` |

## Architecture

```
┌─────────────────────────────────────────┐
│  ghcr.io/tuanmobiledev/wordpress-oscar   │  ← image (theme + plugins + WooCommerce)
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│  Coolify service xqiz39ffoqvqos41xrggpb1h│
│  - WordPress container (image)          │
│  - MariaDB container (db_data volume)   │
│  - wp_data volume (wp-content/uploads)  │
└─────────────────────────────────────────┘
                    ↓
┌─────────────────────────────────────────┐
│  Cloudflare proxy → maytinhthuduc.com   │
└─────────────────────────────────────────┘
```

## Image

- Repo: `ghcr.io/tuanmobiledev/wordpress-oscar`
- Base: `wordpress:6.9-php8.3-apache`
- Includes: theme `oscar-shop` (SPA bundle fetch qua `/wp-json/oscar/v1/products` + `/wp-json/wp/v2/posts`), plugins `woocommerce`, `oscar-shop-core`, `oscar-nhanh-sync`, `deploy-v2`, `cron-helper`, `blog-meta-v2`, mu-plugins `oscar-seo.php` + `oscar-product-specs.php` + `oscar-slug-redirects.php` (prod-only, chưa commit)
- Build context: 200MB (excluding `uploads/` và `crawled-products/`)
- Image size: ~700MB

| LABEL `org.opencontainers.image.version` | `v15-cleanup-2026-08-11` (đồng bộ với GHCR tag) | Đã đồng bộ 2026-08-11 trong commit `2b9d91e`. Lịch sử tag: v11 → v13 (4cd0d21) → v14 (96ea57d) → v15 (2b9d91e). Khi build mới phải bump cả LABEL và tag push theo cùng pattern `vN-prod-YYYY-MM-DD-mô tả`. |

## Volumes

- `xqiz39ffoqvqos41xrggpb1h_db-data` — MariaDB data (persistent, Coolify quản lý)
- `xqiz39ffoqvqos41xrggpb1h_wp-data` — wp-content (theme + plugins + uploads; nhưng theme/plugins được COPY từ image vào volume lúc boot bởi `wp-init.sh`)

Volume `wp-data` được Coolify khai báo qua `docker_compose_raw` với mount `/var/www/html`. Nếu thiếu dòng này thì Coolify tạo anonymous volume mới mỗi lần restart và mất sạch uploads + theme overrides.

> **⚠️ Cảnh báo volume name "QUINTUPLE".** Khi PATCH service qua Coolify API không chuẩn, Coolify đôi khi thêm 1 lần prefix `xqiz39ffoqvqos41xrggpb1h_` vào tên volume, tạo ra dạng `xqiz39ffoqvqos41xrggpb1h_xqiz39ffoqvqos41xrggpb1h-xqiz39ffoqvqos41xrggpb1h-…-wp-data` (đã xảy ra 5 lần liên tiếp, mất data mỗi lần). Khi đổi volume name trong `docker_compose_raw`, **LUÔN verify dòng `volumes:` ở cuối compose khớp với 2 named volumes đang mount hiện tại** trước khi restart.

## Build & push

```bash
# 1. Bump Dockerfile.wp LABEL trước (sửa 2 chỗ: header comment + LABEL org.opencontainers.image.version)
#    pattern: vN-prod-YYYY-MM-DD-mô tả ngắn. Tag GHCR push phải trùng với LABEL.
docker buildx build \
  --tag ghcr.io/tuanmobiledev/wordpress-oscar:v16-prod-YYYY-MM-DD-mota \
  --progress=plain \
  --load .

docker push ghcr.io/tuanmobiledev/wordpress-oscar:v16-prod-YYYY-MM-DD-mota
```

Tag trên prod hiện tại là `v15-cleanup-2026-08-11`. Build mới phải bump version (vd: `v16-prod-YYYY-MM-DD-...`) và PATCH service trỏ sang tag mới.

Source of truth cho service config: `docker-compose.wp.yml` trong repo root. Coolify nhận qua field `docker_compose_raw` (base64-encode trước khi POST).

## Deploy to Coolify

Service config (qua API) cần `docker_compose_raw` với:
- Image: `ghcr.io/tuanmobiledev/wordpress-oscar:<tag>`
- Volume: `xqiz39ffoqvqos41xrggpb1h_wp-data:/var/www/html` (named volume khai báo ở cuối compose)
- DB image: `mariadb:10.6.4-focal` (KHÔNG dùng `mariadb:11.4` để khớp với prod)

```bash
# Source: /root/.secrets/user-secrets.env (COOLIFY_BASE_URL, COOLIFY_TOKEN, COOLIFY_APP_UUID)
curl -X PATCH -H "Authorization: Bearer ***" \
  -H 'Content-Type: application/json' \
  "$COOLIFY_BASE_URL/api/v1/services/$COOLIFY_APP_UUID" \
  -d @compose-payload.json

curl -X POST -H "Authorization: Bearer ***" \
  "$COOLIFY_BASE_URL/api/v1/services/$COOLIFY_APP_UUID/restart"
```

> **⚠️ `docker_compose_raw` yêu cầu base64-encode.** `{"docker_compose_raw": "<yaml>"}` sẽ bị từ chối với `should be base64 encoded`. Phải dùng `base64.b64encode(raw.encode()).decode()` trước khi gửi.

Xem `compose-payload.json` (sinh lúc deploy) cho cấu trúc đầy đủ.

## Fresh deploy (từ zero → y hệt prod hiện tại)

Repo không chứa DB content + uploads + tokens — repo chỉ chứa code. Để spin-up một server mới y hệt prod cần:

1. **Pull code**: `git clone https://github.com/tuanmobiledev/old-laptop-shop-wordpress.git`
2. **Build image** từ Dockerfile.wp (xem "Build & push")
3. **Tạo Coolify service mới** (hoặc PATCH service hiện tại) trỏ vào tag mới nhất. Compose template ở `docker-compose.wp.yml` repo root.
4. **Restore DB** từ dump prod (`docker exec db-xqiz… mysqldump … > oscar-db-dump.sql` rồi import ngược). Bước này phải chạy thủ công.
5. **Restore uploads** từ volume prod — xem "Uploads sync" bên dưới
6. **Cấu hình Nhanh credentials** qua `wp option update oscar_nhanh_settings` (route `/wp-json/oscar/v1/nhanh/config` đã xóa 2026-08-11). Xem FRESH_DEPLOY.md §7.
7. **wp-init.sh tự động**: nếu DB trống (fresh install), entrypoint sẽ activate plugins, set permalink, set Nhanh credentials (từ `/root/.secrets/user-secrets.env` source tự động), trigger 1 lần sync. Xem `scripts/wp-init.sh`.
8. **Verify**: curl `/wp-json/oscar/v1/products`, đếm products (expect 88), check uploads load 200.

## Uploads sync (từ local dev hoặc từ prod hiện tại)

`wp-content/uploads/` không track trong repo (xem `.gitignore`). Nằm trong named volume `xqiz39ffoqvqos41xrggpb1h_wp-data`. Để populate lại sau fresh deploy:

```bash
# Trên host có uploads (vd prod cũ hoặc local dev đang chạy):
# Prod volume mount: /var/lib/docker/volumes/xqiz39ffoqvqos41xrggpb1h_wp-data/_data/wp-content/uploads/
# Local dev volume:  /var/lib/docker/volumes/old-laptop-shop-wordpress_wordpress_data/_data/wp-content/uploads/

cd /var/lib/docker/volumes/<volume-name>/_data/wp-content/uploads
tar -cf - . | gzip > /tmp/uploads.tar.gz

# Stream sang host Coolify (đường SSH qua Hermes: scp hoặc dd over ssh)
ssh -i /tmp/coolify_key root@100.80.205.76 \
  "dd of=/tmp/uploads.tar.gz bs=4096" < /tmp/uploads.tar.gz

# Trên Coolify host: extract vào volume mới
ssh -i /tmp/coolify_key root@100.80.205.76 '
  cd /var/lib/docker/volumes/xqiz39ffoqvqos41xrggpb1h_wp-data/_data/wp-content/uploads
  mkdir -p .
  tar -xzf /tmp/uploads.tar.gz
  chown -R 33:33 .
'
```

> SSH key location hiện tại: `/tmp/coolify_key` (đổi tên từ `coolify-prod-key` 2026-08-11).

## Verification

```bash
curl -sI https://maytinhthuduc.com/
curl -s 'https://maytinhthuduc.com/wp-json/oscar/v1/products' | python3 -c "import sys,json; print(len(json.load(sys.stdin)))"  # expect 88
curl -s -D /tmp/h.txt 'https://maytinhthuduc.com/wp-json/wp/v2/product?per_page=1' -o /dev/null && grep -i 'x-wp-total' /tmp/h.txt  # expect "x-wp-total: 88"
curl -sI 'https://maytinhthuduc.com/wp-content/uploads/2026/07/<some-thumb>.webp'  # expect 200
```

## Troubleshooting

- **503 từ `/wp-json/oscar/v1/*`** — WooCommerce chưa load. Check `wp_options.active_plugins` có `woocommerce/woocommerce.php`.
- **Image 404** — uploads chưa trong volume. Re-run "Uploads sync".
- **Container fail/start liên tục** — thường do permission issue với wp-content (`chown -R 33:33 /var/www/html`). Entry wrapper đã chown lúc boot nhưng nếu volume mới trống có thể fail; check `docker logs wordpress-xqiz…`.
- **Anonymous volume tạo mới (mất data)** — thiếu `volumes:` block named volume trong compose. Re-PATCH với `docker_compose_raw` đúng format.
- **Volume name bị "QUINTUPLE" prefix** — Coolify API đôi khi thêm prefix gấp 5 lần. So sánh `docker_compose_raw` gửi đi với `docker volume ls | grep xqiz`. Nếu thấy nhiều bản duplicate → restore từ `backups/` hoặc re-import DB + uploads. Tránh PATCH compose không cần thiết.
- **Theme files read-only** — volume mount override làm toàn bộ `wp-content/themes/oscar-shop/` không ghi được. Để sửa hash asset: edit qua `wp-admin/plugin-editor.php` (plugin writable) HOẶC hook `wp_enqueue_scripts` trong mu-plugin. KHÔNG dùng `wp-admin/theme-editor.php`.
- **`docker_compose_raw` bị reject** — phải base64-encode toàn bộ YAML trước khi gửi. Plain JSON trả về `should be base64 encoded`.
- **Plugin `oscar-blog` xuất hiện lại trong prod** — code rác từ volume cũ. Không active. Blog thật (≥ 1 post) chạy qua WP posts (`post_type=post`) + SPA bundle fetch từ `/wp-json/wp/v2/posts`. Bỏ qua file `oscar-blog` rác.
- **`/wp-json/oscar/v1/blog-posts` trả 404** — đúng, plugin này đã bị xóa khỏi repo (commit `ba7981a`). Blog thật dùng `/wp-json/wp/v2/posts` (chuẩn WP, không custom).
- **SPA không hiển thị blog mới đăng** — clear cache Cloudflare hoặc hard reload. SPA bundle fetch `/wp-json/wp/v2/posts` mỗi page load; không cần redeploy. Nếu vẫn không thấy → kiểm tra post status = `publish` và `post_type=post`.

## Security notes

> **⚠️ DB passwords trên prod hiện đang là Coolify default placeholder** (`somewordpress` / `wordpress` / `wordpress`). Chỉ an toàn vì Coolify host nằm sau Hermes internal network (`100.80.205.76`) chứ DB port **không public**. Khi rotate, cần update đồng thời 4 chỗ:
>
> 1. Coolify service env vars (`MYSQL_ROOT_PASSWORD`, `MYSQL_PASSWORD`, `WORDPRESS_DB_PASSWORD`)
> 2. MariaDB container shell: `docker exec db-xqiz… mysql -uroot -p"$NEW" -e "ALTER USER 'wordpress'@'%' IDENTIFIED BY '$NEW'; FLUSH PRIVILEGES;"`
> 3. WordPress container env restart để nó re-read `WORDPRESS_DB_PASSWORD`
> 4. Local `.env` (nếu muốn dev match prod)
>
> Sau rotate, **commit ngay** giá trị mới vào Coolify service config (xem `scripts/` nếu có script rotate) và update DEPLOY.md để khỏi quên.

> **📌 Lưu ý quan trọng:** `.env` ở repo **không commit** (xem `.gitignore`), và giá trị trong `.env` được đồng bộ từ `/root/.secrets/user-secrets.env` + Coolify service config. Nếu Boss thay đổi passwords trên prod mà quên update `.env`, local dev sẽ drift khỏi prod.