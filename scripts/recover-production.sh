#!/bin/bash
# OSCAR Shop — Production Recovery Script
# Boss chạy script này qua SSH để restore production WP từ local dev
#
# Prerequisites:
#   - Boss có SSH key đến Coolify VM (100.80.205.76) — đã confirm
#   - Local dev (coder-tuan) đang chạy với 85 products intact
#   - DB credentials: local = wordpress/replace-with-a-strong-password, prod = wordpress/wordpress
#
# Timeline:
#   1. SSH Coolify
#   2. Verify state
#   3. Dump local DB (CHẠY TRÊN coder-tuan, không phải Coolify)
#   4. Transfer dump to Coolify
#   5. Import dump vào production DB
#   6. Update siteurl/home
#   7. Activate plugins (PHP — image không có wp-cli)
#   8. Set permalinks
#   9. Set WC permalinks
#   10. Flush rewrite rules
#   11. Set Nhanh credentials
#   12. Verify

set -e

PROD_HOST="100.80.205.76"
LOCAL_WP_CONTAINER="old-laptop-shop-wordpress-wordpress-1"
LOCAL_DB_PASS="replace-with-a-strong-password"
PROD_WP_CONTAINER="wordpress-xqiz39ffoqvqos41xrggpb1h"
PROD_DB_CONTAINER="db-xqiz39ffoqvqos41xrggpb1h"
PROD_DB_USER="wordpress"
PROD_DB_PASS="wordpress"
PROD_DB_NAME="wordpress"

echo "═══════════════════════════════════════════════"
echo "BƯỚC 1: Verify local dev còn intact"
echo "═══════════════════════════════════════════════"
docker exec $LOCAL_WP_CONTAINER mysql -h db -u wordpress -p"$LOCAL_DB_PASS" $PROD_DB_NAME -e "
SELECT
  (SELECT COUNT(*) FROM wp_posts WHERE post_type='product' AND post_status='publish') AS products,
  (SELECT option_value FROM wp_options WHERE option_name='siteurl') AS siteurl,
  (SELECT option_value FROM wp_options WHERE option_name='home') AS home;
" 2>&1

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 2: Dump local DB"
echo "═══════════════════════════════════════════════"
docker exec $LOCAL_WP_CONTAINER mysqldump -h db -u wordpress -p"$LOCAL_DB_PASS" \
  --single-transaction --routines --triggers --add-drop-table \
  --default-character-set=utf8mb4 \
  $PROD_DB_NAME > /tmp/oscar-db-dump.sql

ls -lh /tmp/oscar-db-dump.sql
echo "Tables in dump:"
grep -c "^CREATE TABLE" /tmp/oscar-db-dump.sql

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 3: Verify SSH tới Coolify"
echo "═══════════════════════════════════════════════"
ssh -o StrictHostKeyChecking=no root@$PROD_HOST "echo 'SSH OK from Coolify' && hostname && docker ps | grep -E 'wordpress|db-xqiz' || echo 'No containers yet'"

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 4: Transfer dump tới Coolify"
echo "═══════════════════════════════════════════════"
scp /tmp/oscar-db-dump.sql root@$PROD_HOST:/tmp/
echo "Transferred. Verify:"
ssh root@$PROD_HOST "ls -lh /tmp/oscar-db-dump.sql"

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 5: Verify production state trước khi import"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
echo "Production containers:"
docker ps | grep -E "xqiz39ffoqvqos41xrggpb1h"
echo
echo "Production DB state:"
docker exec db-xqiz39ffoqvqos41xrggpb1h mysql -uwordpress -pwordpress wordpress -e "
SELECT COUNT(*) AS products FROM wp_posts WHERE post_type='product';
SELECT option_value FROM wp_options WHERE option_name='siteurl';
SELECT option_value FROM wp_options WHERE option_name='blogname';
" 2>&1 | grep -v "Using a password"
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 6: Backup production DB trước khi import (safety)"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
docker exec db-xqiz39ffoqvqos41xrggpb1h mysqldump -uwordpress -pwordpress \
  --single-transaction --routines --triggers --add-drop-table \
  --default-character-set=utf8mb4 \
  wordpress > /tmp/oscar-db-prod-backup-$(date +%Y%m%d-%H%M).sql 2>/dev/null
ls -lh /tmp/oscar-db-prod-backup-*.sql | tail -1
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 7: Import dump vào production DB"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
docker exec -i db-xqiz39ffoqvqos41xrggpb1h mysql -uwordpress -pwordpress \
  --default-character-set=utf8mb4 wordpress < /tmp/oscar-db-dump.sql 2>&1 | grep -v "Using a password"
echo "Import done. Verify:"
docker exec db-xqiz39ffoqvqos41xrggpb1h mysql -uwordpress -pwordpress wordpress -e "
SELECT COUNT(*) AS products FROM wp_posts WHERE post_type='product';
SELECT option_value FROM wp_options WHERE option_name='blogname';
SELECT option_value FROM wp_options WHERE option_name='siteurl';
SELECT option_value FROM wp_options WHERE option_name='home';
" 2>&1 | grep -v "Using a password"
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 8: Update siteurl/home thành maytinhthuduc.com (nếu sai)"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
docker exec db-xqiz39ffoqvqos41xrggpb1h mysql -uwordpress -pwordpress wordpress -e "
UPDATE wp_options SET option_value='https://maytinhthuduc.com' WHERE option_name='siteurl';
UPDATE wp_options SET option_value='https://maytinhthuduc.com' WHERE option_name='home';
SELECT option_name, option_value FROM wp_options WHERE option_name IN ('siteurl','home');
" 2>&1 | grep -v "Using a password"
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 9: Verify wp-content files trong production"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
echo "Plugins in volume:"
docker exec wordpress-xqiz39ffoqvqos41xrggpb1h ls /var/www/html/wp-content/plugins/ 2>&1
echo
echo "Themes in volume:"
docker exec wordpress-xqiz39ffoqvqos41xrggpb1h ls /var/www/html/wp-content/themes/ 2>&1
echo
echo "Image baked plugins (in /usr/src/wordpress/):"
docker exec wordpress-xqiz39ffoqvqos41xrggpb1h ls /usr/src/wordpress/wp-content/plugins/ 2>&1
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 10: Activate plugins + set permalinks + flush rewrites"
echo "(dùng PHP vì image không có wp-cli)"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
docker exec wordpress-xqiz39ffoqvqos41xrggpb1h php -r "
define('WP_USE_THEMES', false);
require '/var/www/html/wp-load.php';

// Activate plugins
\$plugins = ['oscar-shop-core/oscar-shop-core.php', 'oscar-nhanh-sync/oscar-nhanh-sync.php', 'woocommerce/woocommerce.php'];
foreach (\$plugins as \$p) {
  if (!is_plugin_active(\$p)) {
    \$result = activate_plugin(\$p);
    echo 'Activated: ' . \$p . PHP_EOL;
  } else {
    echo 'Already active: ' . \$p . PHP_EOL;
  }
}

// Set permalink structure
global \$wp_rewrite;
\$wp_rewrite->set_permalink_structure('/%postname%/');
\$wp_rewrite->set_permalink_structure('/%postname%/');
update_option('permalink_structure', '/%postname%/');

// Set WC permalinks
update_option('woocommerce_permalinks', [
  'product_base' => 'product',
  'category_base' => 'product-category',
  'tag_base' => 'product-tag',
  'attribute_base' => '',
  'use_verbose_page_rules' => 0,
]);

// Set blogname
update_option('blogname', 'Laptop OSCAR Thủ Đức');
update_option('blogdescription', 'Laptop cũ, phụ kiện và sửa chữa');

// Flush rewrites
\$wp_rewrite->flush_rules(true);
echo 'Rewrites flushed' . PHP_EOL;

echo 'Active plugins: ' . print_r(get_option('active_plugins'), true) . PHP_EOL;
"
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 11: Set Nhanh credentials (giống local dev)"
echo "═══════════════════════════════════════════════"
ssh root@$PROD_HOST << 'EOF'
docker exec db-xqiz39ffoqvqos41xrggpb1h mysql -uwordpress -pwordpress wordpress -e "
UPDATE wp_options SET option_value='a:4:{s:6:\"app_id\";i:77885;s:11:\"business_id\";i:226682;s:8:\"depot_id\";i:236102;s:5:\"token\";s:132:\"VsP5ckHEoj2Emhd8j4QdWRNmJ1kivWQDebis9zqYnSJLMtjZuYIYG2OSauHxDT594IEllorPLUFjE1WmPLcjv72BVTdyXwZhavrM59hRuUg20cS47pvj9eOWaIFc481pPNUR\";}' WHERE option_name='oscar_nhanh_settings';
SELECT option_value FROM wp_options WHERE option_name='oscar_nhanh_settings';
" 2>&1 | grep -v "Using a password"
EOF

echo
echo "═══════════════════════════════════════════════"
echo "BƯỚC 12: Verify production từ bên ngoài"
echo "═══════════════════════════════════════════════"
echo "Title:"
curl -s "https://maytinhthuduc.com/" | grep -E "<title>" | head -1
echo
echo "Namespaces:"
curl -s "https://maytinhthuduc.com/wp-json/" | python3 -c "
import json, sys
ns = sorted(json.load(sys.stdin).get('namespaces', []))
print(f'  Total: {len(ns)}')
for n in ns: print(f'  - {n}')
"
echo
echo "Product count (via /oscar/v1/products):"
curl -s -D /tmp/h.txt "https://maytinhthuduc.com/wp-json/oscar/v1/products?per_page=1" -o /tmp/b.json
grep -i "x-wp-total" /tmp/h.txt | head -1
echo "Product count (via /wp/v2/product):"
curl -s -D /tmp/h2.txt "https://maytinhthuduc.com/wp-json/wp/v2/product?per_page=1" -o /dev/null
grep -i "x-wp-total" /tmp/h2.txt | head -1
echo
echo "Test product URL:"
curl -sI "https://maytinhthuduc.com/san-pham/dell-xps-15-9500-i7-10750h-32gb-gtx-1650ti-4k-touch-mong-nhe-i7-10750h-32gb-ram-512gb-ssd-man-hinh-15-6-4k-touch-p1001/" | head -3

echo
echo "═══════════════════════════════════════════════"
echo "DONE"
echo "═══════════════════════════════════════════════"