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
ssh -i /tmp/coolify_key root@100.80.205.76 echo OK
```

## 1. Pull code

```bash
git clone https://github.com/tuanmobiledev/old-laptop-shop-wordpress.git
cd old-laptop-shop-wordpress

# Verify state khớp với prod (post-cleanup: -638 lines dead code)
git log --oneline -1     # expect: 2b9d91e (chore(cleanup): remove dead code)
git status               # expect clean
```

## 2. Chuẩn bị credentials trên server mới

```bash
# .env không commit; phải tạo thủ công trên server mới
cp .env.example .env

# Điền 3 password bằng giá trị đang chạy trên prod hiện tại (xem DEPLOY.md "Trạng thái"):
# - WORDPRESS_DB_PASSWORD=wordpress
# - MARIADB_ROOT_PASSWORD (auto-generated, không cần điền — compose set MARIADB_RANDOM_ROOT_PASSWORD=yes)
# - WP_ADMIN_PASSWORD=<giá trị WORDPRESS_OSCAR_PASSWORD trong /root/.secrets/user-secrets.env>

# Nhanh: bỏ trống các biến NHANH_*, wp-init.sh sẽ tự source từ /root/.secrets/user-secrets.env
# trên Coolify host. Local docker-compose thì KHÔNG có file đó, nên phải điền tay nếu muốn
# test local — lấy giá trị từ secrets file.
```

> ⚠️ **Trên prod thật, sau khi rotate passwords, phải update cả Coolify service config
> + `.env` ở repo (local dev) để khỏi drift.** Xem DEPLOY.md §"Security notes".

## 3. Build image từ Dockerfile

```bash
# LABEL trong Dockerfile.wp đã được bump sẵn về v15-cleanup-2026-08-11.
# Khi build mới, bump cả LABEL lẫn tag push cho khớp.
docker buildx build \
  --tag ghcr.io/tuanmobiledev/wordpress-oscar:v15-cleanup-2026-08-11 \
  --progress=plain \
  --load .

# Sanity check: image có chứa theme + plugins không
docker run --rm ghcr.io/tuanmobiledev/wordpress-oscar:v15-cleanup-2026-08-11 \
  ls /usr/src/wordpress/wp-content/themes/oscar-shop/
# expect: index.php style.css functions.php ... (vài chục file PHP + assets/)

docker push ghcr.io/tuanmobiledev/wordpress-oscar:v15-cleanup-2026-08-11
```

## 4. Dump data từ prod cũ

```bash
# 4a. DB dump (~50-200 MB tùy số products + post meta)
ssh -i /tmp/coolify_key root@100.80.205.76 '
  docker exec db-xqiz39ffoqvqos41xrggpb1h \
    mysqldump -uwordpress -pwordpress wordpress \
    --single-transaction --quick --lock-tables=false \
    > /tmp/oscar-db-dump.sql
'
scp -i /tmp/coolify_key root@100.80.205.76:/tmp/oscar-db-dump.sql .

# 4b. Uploads tarball (~1.4 GB)
ssh -i /tmp/coolify_key root@100.80.205.76 '
  cd /var/lib/docker/volumes/xqiz39ffoqvqos41xrggpb1h_wp-data/_data/wp-content/uploads
  tar -cf - . | gzip > /tmp/uploads.tar.gz
'
scp -i /tmp/coolify_key root@100.80.205.76:/tmp/uploads.tar.gz .
```

> 💡 **Bandwidth tip:** nếu server mới cùng datacenter với prod, dùng `rsync -e ssh`
> thay `scp` để resume nếu bị đứt. Uploads 1.4 GB thường mất 5-15 phút qua mạng nội bộ.

## 5. Tạo Coolify service mới (hoặc dùng lại service cũ)

### 5a. Option A — Tạo service mới trên Coolify

```bash
# Qua UI: Coolify → Project "maytinhthuduc" → "+ New" → "Application"
#   Image: ghcr.io/tuanmobiledev/wordpress-oscar:v15-cleanup-2026-08-11
#   Port: 80
#   Env: giống prod (xem DEPLOY.md §"Trạng thái")
#   Persistent Volume: /var/www/html → tên volume mới (vd: xqiz39ffoqvqos41xrggpb1h_wp-data)

# Sau khi tạo, gắn DB service `mariadb:10.6.4-focal` riêng (UUID riêng, không share
# với DB cũ để tránh xung đột volume name).

# Source of truth cho compose: docker-compose.wp.yml trong repo root. Coolify API có thể
# nhận compose này qua field `docker_compose_raw` (base64-encode trước khi POST).
```

### 5b. Option B — PATCH service hiện tại để trỏ sang tag mới

```bash
set -a; source /root/.secrets/user-secrets.env; set +a

# Tạo payload JSON với image tag mới, base64-encode docker_compose_raw
python3 -c "
import base64, json
compose = open('docker-compose.wp.yml').read()
payload = {'docker_compose_raw': base64.b64encode(compose.encode()).decode()}
print(json.dumps(payload, indent=2))
" > compose-payload.json

# ⚠️ docker-compose.wp.yml là SOURCE OF TRUTH cho prod stack. Coolify cần compose
# riêng với 2 named volumes + Coolify-managed labels. Nếu service hiện tại có compose
# drift, lấy từ service cũ qua API và patch chỉ phần cần đổi:
curl -s -H "Authorization: Bearer *** \
  "\$COOLIFY_BASE_URL/api/v1/services/\$COOLIFY_APP_UUID" | jq -r .docker_compose_raw \
  | base64 -d > /tmp/prod-compose.yaml

# Edit image tag trong /tmp/prod-compose.yaml nếu cần (vd: bump lên v15)
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
ssh -i /tmp/coolify_key root@100.80.205.76 '
  cat /tmp/oscar-db-dump.sql | docker exec -i db-xqiz39ffoqvqos41xrggpb1h \
    mysql -uwordpress -pwordpress wordpress
'

# 6b. Extract uploads vào volume
ssh -i /tmp/coolify_key root@100.80.205.76 '
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
Nếu vì lý do gì mà option trống (vd dump từ DB dev khác prod), dùng `wp option update`
qua `docker exec` (route `/oscar/v1/nhanh/config` đã bị xóa 2026-08-11 cleanup):

```bash
set -a; source /root/.secrets/user-secrets.env; set +a
ssh -i /tmp/coolify_key root@100.80.205.76 "docker exec wordpress-xqiz39ffoqvqos41xrggpb1h wp eval '
\$creds = [
  \"appId\"      => getenv(\"NHANH_APP_ID\"),
  \"businessId\" => getenv(\"NHANH_BUSINESS_ID\"),
  \"depotId\"    => getenv(\"NHANH_DEPOT_ID\"),
  \"token\"      => getenv(\"NHANH_API_TOKEN\"),
];
update_option(\"oscar_nhanh_settings\", \$creds, false);
echo \"Updated: \" . json_encode(get_option(\"oscar_nhanh_settings\"));
' --allow-root"
```

> Nguồn token: `/root/.secrets/user-secrets.env` → `NHANH_APP_ID`,
> `NHANH_BUSINESS_ID`, `NHANH_API_TOKEN`, `NHANH_DEPOT_ID`.

## 7.5. Phase 2 — Populate `_oscar_*` specs (BẮT BUỘC nếu DB dump trống)

> ⚠️ **Vấn đề:** Plugin `oscar-nhanh-sync` chỉ ghi `_nhanh_*` + `_oscar_source_id`. SPA đọc
> `_oscar_brand`, `_oscar_cpu`, `_oscar_ram`, `_oscar_ssd`, `_oscar_screen`, `_oscar_gpu`,
> `_oscar_battery_wh`, `_oscar_battery_runtime`, `_oscar_demand`, `_oscar_condition_vi`,
> `_oscar_badge_vi`, `_oscar_warranty_months` — và `_nhanh_category_id`, `_nhanh_brand_id`.
>
> Nếu DB dump từ prod có đầy đủ các field này → SKIP bước này (đã có sẵn).
> Nếu DB dump là fresh / dev / không có → CHẠY Phase 2 theo workflow dưới.

**Source-of-truth flow:**
1. **Phase 1 (sync)**: `/oscar/v1/nhanh/sync?limit=0` → tạo products + ghi `_nhanh_*` + download images
2. **Phase 2 (specs apply)**:
   - **2a.** Fetch all Nhanh products qua `/v3.0/product/list` + `/v3.0/product/detail` → `nhanh-detail.jsonl`
   - **2b.** Run `data/compute_plan_v3.py` để parse `• CPU:`, `• RAM:`, `• Pin:`, etc. bullets từ Nhanh `content` → `final_plan_v3.json`
   - **2c.** Run `wp eval-file data/apply_plan_v3.php /tmp/final_plan_v3.json` → ghi 11 fields `_oscar_*` + `_nhanh_category_id` + `_nhanh_brand_id` + default badge `_oscar_badge_vi = "3 tháng"`
3. **Phase 3 (manual, Boss)**: Adjust `_oscar_badge_vi` từng SP qua `/oscar/v1/specs/apply` nếu khác default

**Scripts (đã commit trong repo `data/`):**
- `data/compute_plan_v3.py` — parse Nhanh content bullets + brandId lookup + demand inference (no carrier comment needed)
- `data/apply_plan_v3.php` — wpdb direct write (race-free + skip-trash) thay vì REST batch

**Nhanh API requirements:**
- `/v3.0/product/list` — paginated, 50/page
- `/v3.0/product/detail` — full data với `description` + `content` (chứa `• CPU:` bullets)
- `/v3.0/product/brand` — brandId → brand name mapping

**Tại sao cần Phase 2 riêng (không gộp vào sync plugin):**
- Sync plugin không được phép tự infer fields (Boss rule: "NEVER infer fields — ASK Boss if no Nhanh source")
- `_oscar_badge_vi` Boss kiểm soát thủ công (không tự động)
- `compute_plan_v3.py` chạy ngoài-host (Python) → không pollute PHP plugin với regex parsing
- Có thể re-run Phase 2 bất kỳ lúc nào để refresh specs từ Nhanh (idempotent)

**Run trên server mới:**

```bash
# 2a. Fetch Nhanh products via token từ secrets
set -a; source /root/.secrets/user-secrets.env; set +a
mkdir -p /tmp/oscar-sync
cd /tmp/oscar-sync

# Pull all products (paginated)
PAGE=1
> nhanh-detail.jsonl
while true; do
  RESP=$(curl -s -X POST "https://pos.open.nhanh.vn/v3.0/product/list?appId=$NHANH_APP_ID&businessId=$NHANH_BUSINESS_ID" \
    -H "Authorization: $NHANH_API_TOKEN" \
    -d "page=$PAGE")
  if [ "$(echo "$RESP" | jq '.code')" != "0" ]; then break; fi
  echo "$RESP" | jq -c '.data[]' >> nhanh-list.jsonl
  TOTAL=$(echo "$RESP" | jq '.data|length')
  if [ "$TOTAL" -lt 50 ]; then break; fi
  PAGE=$((PAGE+1))
  sleep 0.3
done

# Fetch detail per product (cần content+description)
while read -r item; do
  ID=$(echo "$item" | jq '.id')
  curl -s -X POST "https://pos.open.nhanh.vn/v3.0/product/detail?appId=$NHANH_APP_ID&businessId=$NHANH_BUSINESS_ID" \
    -H "Authorization: $NHANH_API_TOKEN" \
    -H "Content-Type: application/json" \
    -d "{\"filters\":{\"id\":$ID}}" | jq -c '.data[0]' >> nhanh-detail.jsonl
  sleep 0.2
done < nhanh-list.jsonl

# 2b. Compute plan
cd /root/old-laptop-shop-wordpress/data
python3 compute_plan_v3.py /tmp/oscar-sync/nhanh-detail.jsonl /tmp/oscar-sync/final_plan_v3.json

# 2c. Apply (chạy TRONG container vì cần wpdb)
ssh -i /tmp/coolify_key root@100.80.205.76 "
  docker cp /tmp/oscar-sync/final_plan_v3.json wordpress-xqiz39ffoqvqos41xrggpb1h:/tmp/
  docker cp /root/old-laptop-shop-wordpress/data/apply_plan_v3.php wordpress-xqiz39ffoqvqos41xrggpb1h:/var/www/html/wp-content/uploads/
  docker exec wordpress-xqiz39ffoqvqos41xrggpb1h bash -c '
    chown www-data:www-data /var/www/html/wp-content/uploads/apply_plan_v3.php
    wp eval-file /var/www/html/wp-content/uploads/apply_plan_v3.php /tmp/final_plan_v3.json --user=admin --allow-root
  '
"
# expect: "Products processed: 87, Meta values written: 1000+"
```

**Verify Phase 2:**
```bash
ssh -i /tmp/coolify_key root@100.80.205.76 "docker exec wordpress-xqiz39ffoqvqos41xrggpb1h wp eval '
\$post = wc_get_product(get_product_id_by_sku(\"OSCAR-1029\"));
echo json_encode([
  \"brand\"   => get_post_meta(\$post->get_id(), \"_oscar_brand\", true),
  \"cpu\"     => get_post_meta(\$post->get_id(), \"_oscar_cpu\", true),
  \"ram\"     => get_post_meta(\$post->get_id(), \"_oscar_ram\", true),
  \"badge\"   => get_post_meta(\$post->get_id(), \"_oscar_badge_vi\", true),
  \"cat_id\"  => get_post_meta(\$post->get_id(), \"_nhanh_category_id\", true),
], JSON_UNESCAPED_UNICODE);
' --allow-root"
# expect: {"brand":"Lenovo","cpu":"i5-1135G7","ram":"16GB","badge":"3 tháng","cat_id":"9"}
```

## 8. Setup Hermes cron trigger (BẮT BUỘC — WP-Cron không tự fire)

> ⚠️ **Vấn đề:** WordPress cron (`oscar_nhanh_product_sync` mỗi giờ) là **pseudo-cron** —
> chỉ fire khi có request hit `/wp-cron.php`. Server mới không có traffic → cron không bao giờ
> chạy → giá + meta cũ, không sync được từ Nhanh.
>
> Stock sync (`oscar_nhanh_inventory_sync`) đã bị xóa 2026-08-11 — Boss quyết định website không
> quan tâm số lượng tồn kho, show hết sản phẩm từ Nhanh bất kể `_stock`.
>
> WP-Cron phụ thuộc vào traffic — không thể "sửa" plugin để fix. Cách duy nhất là
> trigger từ bên ngoài.

Trên Hermes host (cái máy chạy chat agent này), đảm bảo có 1 job:

```bash
# 8a. Kiểm tra cron job sync trigger đang chạy
hermes cron list | grep -E 'maytinhthuduc-wp-cron-ping'

# Expect thấy:
#   maytinhthuduc-wp-cron-ping   every 15m   enabled   wp-cron-ping.sh
```

Nếu KHÔNG thấy job (vd fresh server chưa có `~/.hermes/scripts/`), tạo mới:

```bash
# 8b. Tạo script tại ~/.hermes/scripts/wp-cron-ping.sh
# (xem scripts/wp-cron-ping.sh trong repo để copy nội dung)
# Hoặc tự viết: login wp-admin → lấy nonce → POST /oscar/v1/nhanh/sync?limit=0

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
  https://maytinhthuduc.com/wp-json/oscar/v1/admin/session | jq -r '.authenticated'
# Expect: true
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
  | python3 -c "import sys,json; print(len(json.load(sys.stdin)))"   # expect 88

# 9c. WP REST header xác nhận count khớp
curl -s -D /tmp/h.txt 'https://maytinhthuduc.com/wp-json/wp/v2/product?per_page=1' \
  -o /dev/null && grep -i 'x-wp-total' /tmp/h.txt            # expect "x-wp-total: 88"

# 9d. Upload load được
curl -sI 'https://maytinhthuduc.com/wp-content/uploads/2026/07/<some-thumb>.webp'  # expect 200

# 9e. SPA bundle load (version cụ thể kiểm tra trong DEPLOY.md "Image" section)
curl -sI 'https://maytinhthuduc.com/wp-content/themes/oscar-shop/assets/index-*.js'  # expect 200

# 9f. Cron trigger (đã setup ở §8) — wait ~16 phút rồi verify sync response
curl -s -X POST -H "X-WP-Nonce: $(curl -s -b /tmp/c.txt https://maytinhthuduc.com/wp-admin/admin-ajax.php?action=rest-nonce)" \
  -b /tmp/c.txt \
  https://maytinhthuduc.com/wp-json/oscar/v1/nhanh/sync?limit=0
# Expect: {"created":0,"updated":0,"skipped":88,"errors":[]} — chứng tỏ cron tick đã sync
```

## 10. Smoke test thủ công

- Mở https://maytinhthuduc.com → home page render SPA, banner "Laptop OSCAR Thủ Đức"
- Click vào 1 sản phẩm → trang chi tiết có gallery ảnh
- Search "Dell Latitude" → filter ra list đúng
- Mở https://maytinhthuduc.com/wp-admin → đăng nhập với `admin` / `WORDPRESS_OSCAR_PASSWORD`
- WooCommerce > Products → đếm = 88, không có sản phẩm rác

## Phụ lục: những thứ KHÔNG nằm trong repo

| Item | Kích thước | Nguồn trên prod |
|---|---|---|
| DB content (88 products + meta + options) | ~50-200 MB | MariaDB volume `xqiz39ffoqvqos41xrggpb1h_db-data` |
| `wp-content/uploads/` (gallery ảnh) | ~1.4 GB | Docker volume `xqiz39ffoqvqos41xrggpb1h_wp-data` |
| WP admin password | — | `/root/.secrets/user-secrets.env` → `WORDPRESS_OSCAR_PASSWORD` |
| Nhanh API token | — | `/root/.secrets/user-secrets.env` → `NHANH_API_TOKEN` |
| Coolify service config (UUID, env, compose) | — | Coolify host, UUID `xqiz39ffoqvqos41xrggpb1h` |
| SSH key tới Coolify host | — | `/tmp/coolify_key` (note: đổi tên từ `coolify-prod-key` 2026-08-11) |

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
| Phase 2 (compute + apply specs) | 5-10 phút |
| Restart + smoke test | 2 phút |
| **Tổng** | **~30-55 phút** nếu mạng nội bộ OK |

## 11. Trạng thái prod hiện tại (snapshot 2026-08-11, sau khi cleanup xong)

### 11a. Snapshot data

| Metric | Value | Source |
|---|---|---|
| Tổng sản phẩm | 88 | `wp wc product list --format=count` |
| Có featured image | 88 (100%) | `wp/v2/product/{id}.featured_media` |
| Có gallery | 88 (100%) | `wp_postmeta._product_image_gallery` |
| Có đầy đủ `_oscar_*` meta | 88 (100%) | DB scan |
| On sale (regular > sale) | 88 | `wp_postmeta._regular_price` vs `_sale_price` |
| BatteryWh > 0 | 75 / 88 | `_oscar_battery_wh` |
| Badge.vi populate | 88 | `_oscar_badge_vi` |
| Total image attachments | ~504 (351 sản phẩm + ~153 orphan từ CLI import cũ) | `wp/v2/media?per_page=100` |
| Blog posts (`post_type=post`) | ≥ 1 | SPA bundle fetch qua `/wp-json/wp/v2/posts` |

### 11b. Endpoints còn hoạt động (HEAD `2b9d91e` — v15 post-cleanup)

Auth: `manage_woocommerce` (cookie + `X-WP-Nonce`).

**Public (no auth):**
- `GET /oscar/v1/products` — main storefront feed
- `GET /oscar/v1/addons` — RAM/SSD addons
- `POST /oscar/v1/newsletter` — newsletter signup
- `POST /oscar/v1/orders` — order create

**Admin (manage_woocommerce):**
- `GET /oscar/v1/admin/session` — check auth status (returns `{authenticated: bool}`)
- `POST /oscar/v1/admin/media` — file upload
- `POST /oscar/v1/admin/fetch-image` — download 1 URL external → WP media library (dùng `media_handle_sideload()`)
- `POST /oscar/v1/admin/attach-product-images` — set featured + gallery cho product

**Sync:**
- `POST /oscar/v1/nhanh/sync?limit=N` — pull N products from Nhanh + upsert
- `POST /oscar/v1/specs/apply` — manual spec writes (skill oscar-specs-sync)

**Helper routes (skill-managed):**
- `GET /wp/v2/posts` — blog post feed (SPA)
- `GET /helper/v1/cron` — cron management
- `POST /helper/v1/deploy-v2/write` — atomic plugin deploy
- `GET|POST /helper/v1/blog-meta-v2/*` — EN blog translation

Đã xóa (commit `2b9d91e` — cleanup 2026-08-11):
- ~~`/settings`~~ — public, no consumer
- ~~`/upgradeability/apply`~~ — admin, no consumer (output field `upgradeability` vẫn trong product JSON)
- ~~`/launch`~~ — wp-entrypoint-wrapper only, not for prod traffic
- ~~`/search`~~ `/filter` `/facets`~~ — oscar-product-specs mu-plugin, no consumer
- ~~`/nhanh/config` `/nhanh/status` `/nhanh/_debug_cron`~~ — sync debug, no consumer
- 3 dead helper plugins: `blog-asset-deploy`, `blog-meta-helper`, `blog-meta-inspector`

### 11c. Vấn đề chưa giải quyết (cần lưu ý khi restore)

1. **11/88 sản phẩm thiếu `_oscar_battery_wh` (và 12/88 thiếu runtime):** Wh=0, runtime=rỗng
   → SPA hiển thị "Đang cập nhật" cho 2 field này. Nguyên nhân: Nhanh `content` không có bullet
   `• Pin: XWh` cho các SP này (Precision workstations + few ThinkPads without Pin bullet).
   Phase 2 `apply_plan_v3.php` đã cover tối đa — SP nào Nhanh có data thì ghi, SP nào không
   có thì để trống (chấp nhận được, chỉ ảnh hưởng display).
   - List 11 SP thiếu Wh: xem `python3 data/compute_plan_v3.py /tmp/nhanh-detail.jsonl | grep battery_wh`
   - Fix manually qua `wp post meta update <post_id> _oscar_battery_wh <value>` nếu cần.

2. **`/nhanh/sync` cron hoạt động** qua Hermes cron trigger `maytinhthuduc-wp-cron-ping` (every 15m).
   Verify bằng `POST /wp-json/oscar/v1/nhanh/sync?limit=0` → `{"created":0,"updated":0,"skipped":88,"errors":[]}`.

3. **153 orphan attachments** cũ (id 104-353) từ CLI import cũ + dry-run tests. Không ảnh hưởng SPA, nhưng tốn disk. Xóa thủ công qua `wp post delete <id> --force` nếu muốn reclaim.

4. **Sync duplicate bug đã fix 2026-08-11** (commit sẽ push khi PR ready):
   - Trước: `wc_get_product_id_by_sku()` false-negative trong bulk sync → tạo duplicate products
   - Sau: direct wpdb query với JOIN wp_posts skip trashed → race-free + skip ghost meta
   - Detail xem commit message hoặc file `wp-content/plugins/oscar-nhanh-sync/oscar-nhanh-sync.php:188-203`

5. **Mouse accessory (1/88) hiển thị "Đang cập nhật"** cho CPU + Màn hình (đúng behavior — chuột không có CPU/screen).
   Ngoài ra SPA có fallback bug: hiển thị "8GB / 256 GB" cứng khi `ram + ssd` empty (cosmetic only).
   Sửa trong SPA bundle khi có thời gian.

### 11d. Verify endpoints sau khi restore

```bash
# Login + lấy nonce (env var đúng tên là WORDPRESS_OSCAR_USER, không có NAME suffix)
set -a; source /root/.secrets/user-secrets.env; set +a
rm -f /tmp/c.txt
curl -s -c /tmp/c.txt -d "log=$WORDPRESS_OSCAR_USER&pwd=$WORDPRESS_OSCAR_PASSWORD&wp-submit=Log+In&redirect_to=https%3A%2F%2Fmaytinhthuduc.com%2Fwp-admin%2F&testcookie=1" \
     https://maytinhthuduc.com/wp-login.php > /dev/null
NONCE=$(curl -s -b /tmp/c.txt https://maytinhthuduc.com/wp-admin/admin-ajax.php?action=rest-nonce)

# Session auth check
curl -s -b /tmp/c.txt -H "X-WP-Nonce: $NONCE" \
  https://maytinhthuduc.com/wp-json/oscar/v1/admin/session | jq -r '.authenticated'
# expect: true

# Products count (public, no auth)
curl -s 'https://maytinhthuduc.com/wp-json/oscar/v1/products' \
  | python3 -c "import sys,json; print(len(json.load(sys.stdin)))"   # expect 88

# Sync trigger (admin)
curl -s -X POST -H "X-WP-Nonce: $NONCE" -b /tmp/c.txt \
  https://maytinhthuduc.com/wp-json/oscar/v1/nhanh/sync?limit=0
# expect: {"created":0,"updated":0,"skipped":88,"errors":[]}

# Admin fetch-image (admin)
curl -s -b /tmp/c.txt -H "X-WP-Nonce: $NONCE" \
  -X POST https://maytinhthuduc.com/wp-json/oscar/v1/admin/fetch-image \
  -H 'Content-Type: application/json' \
  -d '{"url":"https://example.com/test.jpg"}'
# expect: 201 {attachment_id:..., url:...} hoặc 422 nếu URL invalid

# Blog posts feed
curl -s 'https://maytinhthuduc.com/wp-json/wp/v2/posts?per_page=10' | jq 'length'
# expect: ≥ 1
```
