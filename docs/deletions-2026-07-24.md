# Product deletions — 2026-07-24

**Operator:** Boss via Hermes
**Scope:** Xóa sạch 2 nhóm sản phẩm không phục vụ storefront:

## Nhóm 1 — Storefront hidden (publish + `_oscar_catalog_type`)

**Count:** 34
**IDs:** 994, 993, 991, 992, 988, 989, 990, 987, 986, 983, 984, 985, 981, 982, 979, 980, 977, 978, 975, 976, 972, 973, 974, 969, 970, 971, 966, 967, 968, 964, 965, 309, 169, 99

**Lý do:** Nhóm sản phẩm đã bị ẩn khỏi REST `/wp-json/oscar/v1/products` (filter `_oscar_catalog_type`). SKU pattern `STK-*` (manual catalog bổ sung) + `WAR-*` (warranty add-on) không còn phục vụ storefront.

## Nhóm 2 — Drafts (OSCAR-001 → 082)

**Count:** 73
**IDs:** 962, 960, 958, 956, 954, 952, 950, 948, 944, 940, 938, 936, 920, 906, 892, 878, 864, 850, 836, 822, 808, 794, 780, 766, 752, 738, 724, 696, 682, 668, 654, 645, 631, 617, 603, 589, 575, 561, 547, 533, 519, 505, 491, 477, 463, 449, 435, 421, 407, 393, 379, 365, 337, 323, 295, 281, 267, 253, 239, 225, 211, 197, 183, 141, 127, 113, 89, 80, 66, 52, 38, 24, 10

**Lý do:** Sản phẩm import từ `data/catalog.json` gốc (82 sp, OSCAR-001 → 082). Toàn bộ import dưới dạng draft, chưa bao giờ publish. Storefront hiện phục vụ 85 sp từ Nhanh.vn sync (OSCAR-1001 → 1086), không cần nhóm drafts này.

## Trước khi xóa

- Publish total: **119** (85 visible + 34 hidden)
- Drafts total: **73**
- Grand total: **192**

## Kỳ vọng sau khi xóa

- Publish: **85** (toàn bộ Nhanh-synced, visible trên REST)
- Drafts: **0**
- Grand total: **85**
- REST API `/wp-json/oscar/v1/products`: **85 items** (không đổi so với trước, vì hidden đã bị filter)

## Method

```bash
wp post delete <ID>... --force   # skip trash, xóa thẳng khỏi DB
```

Dùng `--force` vì Boss muốn xóa hết — không phục hồi qua trash.

## Post-delete verification

Sẽ verify:
1. `wp post list --post_type=product --post_status=any --format=count` → 85
2. REST API `/wp-json/oscar/v1/products` → 85 items, stock distribution giữ nguyên
3. `wp eval` kiểm tra không còn product có `_oscar_catalog_type` ở status=publish
4. `wp eval` kiểm tra không còn draft product
