# Fresh deploy — Spin-up một server mới y hệt prod hiện tại

> Checklist này dành cho khi Boss cần restore lại toàn bộ stack trên một server mới
> (hoặc reset hoàn toàn server cũ). Repo này **chỉ chứa code**, không có DB content,
> uploads, hay tokens — phải dump/restore riêng từ prod.

## TL;DR

Repo + Docker image + Coolify service config + DB dump + uploads tarball + Nhanh
credentials = server mới y hệt prod.

Không có 1 file nào trong repo chứa DB content hay uploads. Repo chỉ là blueprint.

## 0. Trước khi bắt đầu — verify môi trường

```bash
# Server mới cần:
docker --version            # >= 24.x (Coolify yêu cầu)
docker compose version      # >= v2.20
git --version
ssh -V
# Kết nối tới Coolify host cũ (để dump data):
ssh -i /tmp/coolify-prod-key root@100.80.205.76 echo OK
```

## 1. Pull code

```bash
git clone https://github.com/tuanmobiledev/old-laptop-shop-wordpress.git
cd old-laptop-shop-wordpress

# Verify state khớp với prod
git log --oneline -1     # expect: 96ea57d (v14 — Dockerfile bump + SPA fetch WP posts)
git status               # expect clean
```

## 2. Chuẩn bị credentials trên server mới

```bash
# .env không commit; phải tạo thủ công trên server mới
cp .env.example .env

# Điền 3 password bằng giá trị đang chạy trên prod hiện tại (xem DEPLOY.md "Trạng thái"):
# - WORDPRESS_DB_PASSWORD=wordpress
# - MARIADB_ROOT_PASSWORD=somewordpress
# - WP_ADMIN_PASSWORD=<giá trị WORDPRESS_OSCAR_PASSWORD trong /root/.secrets/user-secrets.env>

# Nếu muốn tự động inject từ Hermes:
set -a; source /root/.secrets/user-secrets.env; set +a
sed -i "s|^WORDPRESS_DB_PASSWORD=.*|WORDPRESS_DB_PASSWORD=wordpress|" .env
sed -i "s|^MARIADB_ROOT_PASSWORD=.*|MARIADB_ROOT_PASSWORD=somewordpress|" .env
sed -i "s|^WP_ADMIN_PASSWORD=.*|WP_ADMIN_PASSWORD=${WORDPRESS_OSCAR_PASSWORD}|" .env

# Nhanh: bỏ trống các biến NHANH_*, wp-init.sh sẽ tự source từ /root/.secrets/user-secrets.env
# trên Coolify host. Local docker-compose thì KHÔNG có file đó, nên phải điền tay nếu muốn
# test local — lấy giá trị từ secrets file.
```

> ⚠️ **Trên prod thật, sau khi rotate passwords, phải update cả Coolify service config
> + `.env` ở repo (local dev) để khỏi drift.** Xem DEPLOY.md §"Security notes".

## 3. Build image từ Dockerfile

```bash
# LABEL trong Dockerfile đã được bump sẵn về v14-blog-posts-2026-07-31.
# Khi build mới, bump cả LABEL lẫn tag push cho khớp.
docker buildx build \
  --tag ghcr.io/tuanmobiledev/wordpress-oscar:v14-blog-posts-2026-07-31 \
  --progress=plain \
  --load .

# Sanity check: image có chứa theme + plugins không
docker run --rm ghcr.io/tuanmobiledev/wordpress-oscar:v14-blog-posts-2026-07-31 \
  ls /usr/src/wordpress/wp-content/themes/oscar-shop/
# expect: index.php style.css functions.php ... (vài chục file PHP + assets/)

docker push ghcr.io/tuanmobiledev/wordpress-oscar:v14-blog-posts-2026-07-31
```

## 4. Dump data từ prod cũ

```bash
# 4a. DB dump (~50-200 MB tùy số products + post meta)
ssh -i /tmp/coolify-prod-key root@100.80.205.76 '
  docker exec db-xqiz39ffoqvqos41xrggpb1h \
    mysqldump -uwordpress -pwordpress wordpress \
    --single-transaction --quick --lock-tables=false \
    > /tmp/oscar-db-dump.sql
'
scp -i /tmp/coolify-prod-key root@100.80.205.76:/tmp/oscar-db-dump.sql .

# 4b. Uploads tarball (~1.4 GB)
ssh -i /tmp/coolify-prod-key root@100.80.205.76 '
  cd /var/lib/docker/volumes/xqiz39ffoqvqos41xrggpb1h_wp-data/_data/wp-content/uploads
  tar -cf - . | gzip > /tmp/uploads.tar.gz
'
scp -i /tmp/coolify-prod-key root@100.80.205.76:/tmp/uploads.tar.gz .
```

> 💡 **Bandwidth tip:** nếu server mới cùng datacenter với prod, dùng `rsync -e ssh`
> thay `scp` để resume nếu bị đứt. Uploads 1.4 GB thường mất 5-15 phút qua mạng nội bộ.

## 5. Tạo Coolify service mới (hoặc dùng lại service cũ)

### 5a. Option A — Tạo service mới trên Coolify

```bash
# Qua UI: Coolify → Project "maytinhthuduc" → "+ New" → "Application"
#   Image: ghcr.io/tuanmobiledev/wordpress-oscar:v14-blog-posts-2026-07-31
#   Port: 80
#   Env: giống prod (xem DEPLOY.md §"Trạng thái")
#   Persistent Volume: /var/www/html → tên volume mới (vd: xqiz39ffoqvqos41xrggpb1h_wp-data)

# Sau khi tạo, gắn DB service `mariadb:10.6.4-focal` riêng (UUID riêng, không share
# với DB cũ để tránh xung đột volume name).
```

### 5b. Option B — PATCH service hiện tại để trỏ sang tag mới

```bash
set -a; source /root/.secrets/user-secrets.env; set +a

# Tạo payload JSON với image tag mới, base64-encode docker_compose_raw
python3 -c "
import base64, json
compose = open('docker-compose.yml').read()
payload = {'docker_compose_raw': base64.b64encode(compose.encode()).decode()}
print(json.dumps(payload, indent=2))
" > compose-payload.json

# ⚠️ docker-compose.yml local dùng để BUILD IMAGE, không phải để PATCH service.
# Coolify cần compose riêng với 2 named volumes + Coolify-managed labels.
# Dùng template từ DEPLOY.md §"Architecture" hoặc lấy từ service cũ qua API:
curl -s -H "Authorization: Bearer \$COOLIFY_TOKEN" \
  "\$COOLIFY_BASE_URL/api/v1/services/\$COOLIFY_APP_UUID" | jq -r .docker_compose_raw \
  | base64 -d > /tmp/prod-compose.yaml

# Edit image tag trong /tmp/prod-compose.yaml nếu cần (vd: bump lên v13)
# Rồi encode lại và PATCH:
python3 -c "
import base64, json, sys
raw = open('/tmp/prod-compose.yaml').read()
print(json.dumps({'docker_compose_raw': base64.b64encode(raw.encode()).decode()}))
" > compose-payload.json

curl -X PATCH -H "Authorization: Bearer \$COOLIFY_TOKEN" \
  -H 'Content-Type: application/json' \
  "\$COOLIFY_BASE_URL/api/v1/services/\$COOLIFY_APP_UUID" \
  -d @compose-payload.json

curl -X POST -H "Authorization: Bearer \$COOLIFY_TOKEN" \
  "\$COOLIFY_BASE_URL/api/v1/services/\$COOLIFY_APP_UUID/restart"
```

> ⚠️ **Coolify QUINTUPLE volume bug** — xem DEPLOY.md §"Volumes". Trước khi restart,
> verify 2 named volumes ở cuối `docker_compose_raw` khớp với 2 volumes đang mount.
> Nếu thấy nhiều bản duplicate → restore từ backups hoặc re-import DB + uploads.

## 6. Restore data lên server mới

```bash
# 6a. Import DB (chạy SAU khi MariaDB container healthy)
ssh -i /tmp/coolify-prod-key root@100.80.205.76 '
  cat /tmp/oscar-db-dump.sql | docker exec -i db-xqiz39ffoqvqos41xrggpb1h \
    mysql -uwordpress -pwordpress wordpress
'

# 6b. Extract uploads vào volume
ssh -i /tmp/coolify-prod-key root@100.80.205.76 '
  cd /var/lib/docker/volumes/xqiz39ffoqvqos41xrggpb1h_wp-data/_data/wp-content/uploads
  rm -rf ./* 2>/dev/null
  tar -xzf /tmp/uploads.tar.gz
  chown -R 33:33 .
'

# 6c. Restart WordPress container để re-mount + clear opcache
curl -X POST -H "Authorization: Bearer \$COOLIFY_TOKEN" \
  "\$COOLIFY_BASE_URL/api/v1/services/\$COOLIFY_APP_UUID/restart"
```

## 7. Cấu hình Nhanh credentials (nếu chưa có trong DB dump)

DB dump đã chứa `wp_options.oscar_nhanh_settings` nên thường không cần bước này.
Nếu vì lý do gì mà option trống (vd dump từ DB dev khác prod):

```bash
# Qua REST, cần WP admin user/pass:
curl -X POST -u "admin:\${WORDPRESS_OSCAR_PASSWORD}" \
  -H 'Content-Type: application/json' \
  -d @nhanh-config.json \
  https://maytinhthuduc.com/wp-json/oscar/v1/nhanh/config

# nhanh-config.json:
# {
#   "appId": "<NHANH_APP_ID>",
#   "businessId": "<NHANH_BUSINESS_ID>",
#   "depotId": "<NHANH_DEPOT_ID>",
#   "token": "<NHANH_API_TOKEN>"
# }
```

> Nguồn token: `/root/.secrets/user-secrets.env` → `NHANH_APP_ID`,
> `NHANH_BUSINESS_ID`, `NHANH_API_TOKEN`, `NHANH_DEPOT_ID`.

## 8. Setup Hermes cron trigger (BẮT BUỘC — WP-Cron không tự fire)

> ⚠️ **Vấn đề:** WordPress cron (`oscar_nhanh_inventory_sync` mỗi 15min,
> `oscar_nhanh_product_sync` mỗi giờ) là **pseudo-cron** — chỉ fire khi có request
> hit `/wp-cron.php`. Server mới không có traffic → cron không bao giờ chạy → stock
> + giá cũ, không sync được từ Nhanh.
>
> WP-Cron phụ thuộc vào traffic — không thể "sửa" plugin để fix. Cách duy nhất là
> trigger từ bên ngoài.

Trên Hermes host (cái máy chạy chat agent này), đảm bảo có 2 job:

```bash
# 8a. Kiểm tra cron job sync trigger đang chạy
hermes cron list | grep -E 'oscar-prod-sync-trigger|maytinhthuduc-wp-cron-ping'

# Expect thấy:
#   maytinhthuduc-wp-cron-ping   every 15m   enabled   wp-cron-ping.sh
```

Nếu KHÔNG thấy job (vd fresh server chưa có ~/.hermes/scripts/), tạo mới:

```bash
# 8b. Tạo script tại ~/.hermes/scripts/wp-cron-ping.sh
# (xem scripts/wp-cron-ping.sh trong repo để copy nội dung)
# Hoặc tự viết: login wp-admin → lấy nonce → POST /oscar/v1/nhanh/sync&limit=0

# 8c. Đăng ký Hermes cron job
hermes cron create \
  --name "maytinhthuduc-wp-cron-ping" \
  --schedule "every 15m" \
  --no-agent \
  --deliver telegram \
  --script wp-cron-ping.sh

# 8d. Verify
hermes cron run <job-id>     # manual trigger
curl -s -b /tmp/c.txt -d "log=admin&pwd=\$WORDPRESS_OSCAR_PASSWORD&wp-submit=Log+In&redirect_to=https%3A%2F%2Fmaytinhthuduc.com%2Fwp-admin%2F&testcookie=1" \
     https://maytinhthuduc.com/wp-login.php > /dev/null
NONCE=$(curl -s -b /tmp/c.txt https://maytinhthuduc.com/wp-admin/admin-ajax.php?action=rest-nonce)
curl -s -b /tmp/c.txt -H "X-WP-Nonce: $NONCE" \
  https://maytinhthuduc.com/index.php?rest_route=/oscar/v1/nhanh/status \
  | jq -r '.lastInventorySync + " / " + .lastProductSync + " / updated=" + (.lastResult.updated|tostring)'
# Expect: thời gian ~vừa trigger + updated=85
```

**Pitfalls:**
- Cookies login có lifetime ~14 ngày; script tự re-login mỗi tick.
- Nếu `WORDPRESS_OSCAR_PASSWORD` rotate → script alert qua Telegram → update secrets.
- Cron `every 15m` parse của Hermes có thể không strict 15 phút; check `next_run_at` ở list output.
- Nếu server mới KHÔNG có Hermes agent, dùng cron-job.org hoặc UptimeRobot gọi
  `https://maytinhthuduc.com/wp-cron.php?doing_wp_cron=NONCE` mỗi 15 min — nhưng
  WP-Cron vẫn có thể không fire event (verified 2026-07-30: HTTP 200 trả về nhưng
  events KHÔNG chạy). Nếu đi route này, **phải dùng REST endpoint** thay vì wp-cron.

> 💡 Nguồn gốc vấn đề: skill `devops/oscar-nhanh-sync/references/wp-cron-pseudo-cron.md`

## 9. Verify

```bash
# 9a. Site load
curl -sI https://maytinhthuduc.com/                          # expect 200

# 9b. Products count
curl -s 'https://maytinhthuduc.com/wp-json/oscar/v1/products' \
  | python3 -c "import sys,json; print(len(json.load(sys.stdin)))"   # expect 85

# 9c. WP REST header xác nhận count khớp
curl -s -D /tmp/h.txt 'https://maytinhthuduc.com/wp-json/wp/v2/product?per_page=1' \
  -o /dev/null && grep -i 'x-wp-total' /tmp/h.txt            # expect "x-wp-total: 85"

# 9d. Upload load được
curl -sI 'https://maytinhthuduc.com/wp-content/uploads/2026/07/<some-thumb>.webp'  # expect 200

# 9e. SPA bundle load
curl -sI 'https://maytinhthuduc.com/wp-content/themes/oscar-shop/assets/index-ByyzmtfA.js'  # expect 200

# 9f. Cron trigger (đã setup ở §8) — wait ~16 phút rồi:
curl -s -b /tmp/c.txt -H "X-WP-Nonce: $(curl -s -b /tmp/c.txt https://maytinhthuduc.com/wp-admin/admin-ajax.php?action=rest-nonce)" \
  https://maytinhthuduc.com/index.php?rest_route=/oscar/v1/nhanh/status
# Expect: lastProductSync + lastInventorySync mới cách < 30 min, updated=85
```

## 10. Smoke test thủ công

- Mở https://maytinhthuduc.com → home page render SPA, banner "Laptop OSCAR Thủ Đức"
- Click vào 1 sản phẩm → trang chi tiết có gallery ảnh
- Search "Dell Latitude" → filter ra list đúng
- Mở https://maytinhthuduc.com/wp-admin → đăng nhập với `admin` / `WORDPRESS_OSCAR_PASSWORD`
- WooCommerce > Products → đếm = 85, không có sản phẩm rác

## Phụ lục: những thứ KHÔNG nằm trong repo

| Item | Kích thước | Nguồn trên prod |
|---|---|---|
| DB content (85 products + meta + options) | ~50-200 MB | MariaDB volume `xqiz39ffoqvqos41xrggpb1h_db-data` |
| `wp-content/uploads/` (gallery ảnh) | ~1.4 GB | Docker volume `xqiz39ffoqvqos41xrggpb1h_wp-data` |
| WP admin password | — | `/root/.secrets/user-secrets.env` → `WORDPRESS_OSCAR_PASSWORD` |
| Nhanh API token | — | `/root/.secrets/user-secrets.env` → `NHANH_API_TOKEN` |
| Coolify service config (UUID, env, compose) | — | Coolify host, UUID `xqiz39ffoqvqos41xrggpb1h` |
| SSH key tới Coolify host | — | `/tmp/coolify-prod-key` |

## Phụ lục: thời gian ước tính

| Bước | Thời gian |
|---|---|
| Pull code + setup .env | 2 phút |
| Build image (Docker) | 3-5 phút |
| Push image lên GHCR | 1-2 phút |
| Dump DB + uploads từ prod | 5-10 phút |
| Transfer qua mạng nội bộ | 5-15 phút |
| Tạo Coolify service mới | 2 phút |
| Import DB + extract uploads | 3-5 phút |
| Restart + smoke test | 2 phút |
| **Tổng** | **~25-45 phút** nếu mạng nội bộ OK |
## 11. Trạng thái prod hiện tại (snapshot 2026-07-30, sau khi sync xong Nhanh)

### 11a. Snapshot data

| Metric | Value | Source |
|---|---|---|
| Tổng sản phẩm | 85 | `wp wc product list --format=count` |
| Có featured image | 85 (100%) | `wp/v2/product/{id}.featured_media` |
| Có gallery | 85 (100%) | `wp_postmeta._product_image_gallery` |
| Có đầy đủ `_oscar_*` meta | 85 (100%) | DB scan |
| On sale (regular > sale) | 85 | `wp_postmeta._regular_price` vs `_sale_price` |
| BatteryWh > 0 | 75 / 85 | `_oscar_battery_wh` |
| Badge.vi populate | 85 | `_oscar_badge_vi` |
| Total image attachments | ~504 (351 sản phẩm + ~153 orphan từ CLI import cũ) | `wp/v2/media?per_page=100` |
| Blog posts (`post_type=post`) | ≥ 1 | SPA bundle v14 fetch qua `/wp-json/wp/v2/posts` |

### 11b. Endpoints còn hoạt động (HEAD `96ea57d` — v14)

Auth: `manage_woocommerce` (cookie + `X-WP-Nonce`).

Còn lại:
- `POST /oscar/v1/admin/fetch-image` — download 1 URL external → WP media library. Dùng `media_handle_sideload()`, returns `{attachment_id, url, filename, mime, size}`. Status 201. Timeout 60s. Lỗi 502 nếu `download_url()` fail, 422 nếu URL invalid.
- `POST /oscar/v1/admin/attach-product-images` — set featured + gallery cho 1 product. Input: `{woo_id, image_id, gallery_ids[]}` (cả 2 optional, có 1 trong 2 cũng OK). Output: 200. Clear `_nhanh_image_urls` meta để SPA fallback sang WP attachment.

Đã xóa (commit `aea003b` — parser thay thế bằng Nhanh data trực tiếp):
- ~~`POST /oscar/v1/specs/apply`~~ — thay thế bởi parser-driven Nhanh sync (5a99b69). Data flow mới: cron `/nhanh/sync` fetch Nhanh → parse bullets ngay lập tức → upsert `_oscar_*` meta.
- ~~`POST /oscar/v1/upgradeability/apply`~~ — dead code, không SPA consumer.

### 11c. Vấn đề chưa giải quyết (cần lưu ý khi restore)

1. **10 sản phẩm thiếu `_oscar_battery_wh` (Wh=0, runtime=rỗng → SPA hiển thị "Đang cập nhật"):**
   - OSCAR-1083, 1024, 1015, 1011, 1010, 1009, 1008, 1007, 1003, 1002
   - Root cause: Nhanh `content` không có bullet Wh cho 10 sp này (chỉ có runtime text).
   - Plan đã có ở `/root/old-laptop-shop-wordpress/data/specs-fix-2026-07-30/missing-battery.json` (10 entries) — nhưng `/specs/apply` endpoint đã bị xóa. Cần apply qua `wp eval` hoặc `wp post meta update` cho từng sp.

2. **`/nhanh/sync` cron đã được fix** (commit `66769eb`: imagick timeout). Hiện chạy mỗi 15 phút qua Hermes cron trigger (`maytinhthuduc-wp-cron-ping`). Verifier `/wp-json/oscar/v1/nhanh/status` trả JSON đầy đủ `lastProductSync`/`lastInventorySync`/`updated`.

3. **153 orphan attachments** cũ (id 104-353) từ CLI import cũ + dry-run tests. Không ảnh hưởng SPA, nhưng tốn disk. Xóa thủ công qua `wp post delete <id> --force` nếu muốn reclaim.

### 11d. Verify endpoints sau khi restore

```bash
# Login + lấy nonce
curl -s -c /tmp/c.txt -d "log=admin&pwd=\$WORDPRESS_OSCAR_PASSWORD&wp-submit=Log+In&redirect_to=https%3A%2Fmaytinhthuduc.com%2Fwp-admin%2F&testcookie=1" \
     https://maytinhthuduc.com/wp-login.php > /dev/null
NONCE=$(curl -s -b /tmp/c.txt https://maytinhthuduc.com/wp-admin/admin-ajax.php?action=rest-nonce)

# Endpoint có tồn tại không (expect 401 because no auth)
curl -s -X POST https://maytinhthuduc.com/index.php?rest_route=/oscar/v1/admin/fetch-image \
     -H 'Content-Type: application/json' -d '{}' | jq -r '.code'
# expect: "rest_forbidden"

# Endpoint admin/fetch-image có hoạt động không (test với 1 URL)
curl -s -b /tmp/c.txt -H "X-WP-Nonce: $NONCE" \
     -X POST https://maytinhthuduc.com/index.php?rest_route=/oscar/v1/admin/fetch-image \
     -H 'Content-Type: application/json' \
     -d '{"url":"https://example.com/test.jpg"}'
# expect: 201 {attachment_id:..., url:...} hoặc 422 nếu URL invalid

# Sync status (cron /nhanh/sync, expect JSON đầy đủ)
curl -s -b /tmp/c.txt -H "X-WP-Nonce: $NONCE" \
     https://maytinhthuduc.com/index.php?rest_route=/oscar/v1/nhanh/status
# expect: {lastProductSync: "...", lastInventorySync: "...", updated: 85}

# Blog posts feed (v14 SPA reads via /wp-json/wp/v2/posts)
curl -s 'https://maytinhthuduc.com/wp-json/wp/v2/posts?per_page=10' | jq 'length'
# expect: ≥ 1
```
