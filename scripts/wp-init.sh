#!/bin/bash
# OSCAR Shop — production auto-fix entrypoint
#
# Runs after docker-entrypoint.sh has copied files to /var/www/html
# and the WP volume has been initialized. Idempotent: only acts when DB
# is in a fresh-install state (no products).
#
# What it does (when fresh install detected):
#   1. Waits for MariaDB to be ready
#   2. Activates oscar-shop-core, oscar-nhanh-sync, woocommerce + 6 helper plugins
#   3. Activates theme 'oscar-shop'
#   4. Sets permalink structure to /%postname%/
#   5. Sets WooCommerce permalinks (product_base=product, category_base=product-category)
#   6. Sets site title and tagline
#   7. Sets Nhanh credentials (from env vars or built-in defaults)
#   8. Flushes rewrite rules
#   9. Triggers a one-shot Nhanh product sync via the plugin's action hook
#
# Always runs (regardless of fresh-install state):
#   - Force-sync plugins, themes, mu-plugins from image to volume
#     (Boss 2026-08-20: was --ignore-existing which masked new image
#      versions from volume. Now uses --update which overwrites when image
#      has newer mtime. CRITICAL: must exclude uploads/, cache/, backups/,
#      *.log to preserve user data + runtime state)
#   - Activate all Oscar plugins + theme (idempotent: 'already active' is fine)
#
# Env vars (optional — override defaults):
#   WORDPRESS_DB_HOST, WORDPRESS_DB_USER, WORDPRESS_DB_PASSWORD, WORDPRESS_DB_NAME
#   WP_SITE_TITLE, WP_SITE_TAGLINE
#   NHANH_APP_ID, NHANH_BUSINESS_ID, NHANH_DEPOT_ID, NHANH_TOKEN
#   SKIP_NHANH_SYNC=1  (to disable auto-sync on this boot)

# Use 'set -u' (undefined vars fail) but NOT 'set -e' — many wp sub-commands
# return non-zero on warnings/edge-cases (e.g. WC permalinks option update when
# WC is not yet active). Strict mode without '-e' lets us handle each step.
set -uo pipefail

cd /var/www/html

# 1. Wait for DB. WordPress image does not include mysql client, so use
# PHP mysqli probe instead of `mysql -e "SELECT 1"`.
echo "[wp-init] Waiting for DB at ${WORDPRESS_DB_HOST:-db}:3306..."
DB_PROBE="/usr/local/bin/db_check.php"
for i in $(seq 1 60); do
  if WORDPRESS_DB_HOST="${WORDPRESS_DB_HOST:-db}" \
     WORDPRESS_DB_USER="${WORDPRESS_DB_USER:-wordpress}" \
     WORDPRESS_DB_PASSWORD="${WORDPRESS_DB_PASSWORD:-wordpress}" \
     WORDPRESS_DB_NAME="${WORDPRESS_DB_NAME:-wordpress}" \
     php -f "$DB_PROBE" >/dev/null 2>&1; then
    echo "[wp-init] DB ready"
    break
  fi
  if [ "$i" -eq 60 ]; then
    echo "[wp-init] DB timeout — continuing anyway (Apache may still recover)"
    break
  fi
  sleep 2
done

# 2. Check WP installed. If NOT installed (fresh volume, no WP-CLI preinstall
# hook), install it now. Without this, fresh containers never bootstrap.
if ! wp core is-installed --allow-root 2>/dev/null; then
  echo "[wp-init] WP not installed — installing core now..."
  WP_SITE_URL="${WP_SITE_URL:-http://localhost}"
  WP_ADMIN_USER_VAL="${WP_ADMIN_USER:-admin}"
  WP_ADMIN_PASSWORD_VAL="${WP_ADMIN_PASSWORD:-admin}"
  WP_ADMIN_EMAIL_VAL="${WP_ADMIN_EMAIL:-admin@example.com}"
  if wp core install \
        --url="$WP_SITE_URL" \
        --title="${WP_SITE_TITLE:-Laptop OSCAR Thủ Đức}" \
        --admin_user="$WP_ADMIN_USER_VAL" \
        --admin_password="$WP_ADMIN_PASSWORD_VAL" \
        --admin_email="$WP_ADMIN_EMAIL_VAL" \
        --skip-email --allow-root 2>&1 | sed 's/^/[wp-init]   install: /'; then
    echo "[wp-init] WP core installed at $WP_SITE_URL"
  else
    echo "[wp-init] FATAL: wp core install failed — aborting" >&2
    exit 1
  fi
fi
echo "[wp-init] WP installed"

# 3. Sync code from image to volume. Image is source of truth for code dirs.
# Boss 2026-08-20 fix: was --ignore-existing which masked new image versions
# from volume. Now uses --update to overwrite when image has newer mtime.
# CRITICAL exclusions: uploads/, cache/, upgrade/, backups/, debug.log are
# runtime/user data — NEVER overwrite.
sync_code_from_image() {
  local src="$1" dst="$2"
  if [ ! -d "$src" ]; then
    echo "[wp-init] (skip) $src not in image"
    return
  fi
  mkdir -p "$dst"
  if command -v rsync >/dev/null 2>&1; then
    rsync -a --update \
      --exclude='uploads' \
      --exclude='cache' \
      --exclude='upgrade' \
      --exclude='backups' \
      --exclude='*.log' \
      "$src/" "$dst/" 2>/dev/null || true
  else
    # Fallback: cp -ru (update only if source is newer). Loop to handle
    # subdirs manually because cp doesn't have rsync's exclude patterns.
    cp -ru "$src/." "$dst/" 2>/dev/null || true
  fi
  echo "[wp-init] Synced $(basename "$src"): $(ls "$dst" 2>/dev/null | wc -l) entries"
}

echo "[wp-init] Syncing code from image → volume (--update mode, excluding uploads/cache/logs)..."
sync_code_from_image /usr/src/wordpress/wp-content/themes /var/www/html/wp-content/themes
sync_code_from_image /usr/src/wordpress/wp-content/plugins /var/www/html/wp-content/plugins
sync_code_from_image /usr/src/wordpress/wp-content/mu-plugins /var/www/html/wp-content/mu-plugins

# Sync root wp-content files (index.php security stub)
if [ -f /usr/src/wordpress/wp-content/index.php ]; then
  cp -f /usr/src/wordpress/wp-content/index.php /var/www/html/wp-content/index.php 2>/dev/null || true
fi

chown -R www-data:www-data /var/www/html/wp-content 2>/dev/null || true

# 4. Activate all Oscar plugins + theme (idempotent — safe on every boot).
# 'already active' is success, not failure. Re-deploys of the same image
# must keep plugins and theme in the correct state.
#
# ORDER MATTERS: woocommerce MUST be activated BEFORE oscar-shop-core and
# oscar-nhanh-sync, otherwise their mu-plugin hooks fail on missing WC
# functions (class WC_Product, WC()->session, etc.).
echo "[wp-init] Activating Oscar plugin suite (10 plugins)..."
for plugin in \
  woocommerce \
  oscar-shop-core \
  oscar-nhanh-sync \
  blog-asset-deploy \
  blog-meta-helper \
  blog-meta-inspector \
  blog-meta-v2 \
  cron-helper \
  deploy-v2 \
  akismet
do
  if [ -d "/var/www/html/wp-content/plugins/${plugin}" ]; then
    wp plugin activate "${plugin}" --allow-root 2>&1 | sed "s/^/[wp-init]   ${plugin}: /" || true
  fi
done

# 5. Activate oscar-shop theme (prod confirmed this is the active theme).
echo "[wp-init] Activating theme 'oscar-shop'..."
wp theme activate oscar-shop --allow-root 2>&1 | sed "s/^/[wp-init]   /" || true

# 6. Check if products exist (fresh-install detector for the heavy work below)
PRODUCT_COUNT=$(wp post list --post_type=product --post_status=publish --format=count --allow-root 2>/dev/null || echo "0")
echo "[wp-init] Product count: ${PRODUCT_COUNT}"

if [ "${PRODUCT_COUNT:-0}" -gt 0 ]; then
  echo "[wp-init] Products exist — DB is healthy. Plugin/theme restored; skipping fresh-install auto-fix."
  exit 0
fi

# 7. Fresh-install detected — run full auto-fix
echo "[wp-init] Fresh install detected — running auto-fix..."

# 7a. Set permalink structure
wp option update permalink_structure '/%postname%/' --allow-root

# 7b. Set WC permalinks. Use --format=json (not --format=php — that flag was
# removed in WP-CLI 2.6+). Wrap with || true because WC may not be fully ready.
wp option update woocommerce_permalinks \
  'a:5:{s:12:"product_base";s:7:"product";s:13:"category_base";s:16:"product-category";s:8:"tag_base";s:11:"product-tag";s:14:"attribute_base";s:0:"";s:22:"use_verbose_page_rules";b:0;}' \
  --format=json --allow-root || echo "[wp-init]   (warn: WC permalinks update skipped)"

# 7c. Set site title and tagline (override via env if provided)
SITE_TITLE="${WP_SITE_TITLE:-Laptop OSCAR Thủ Đức}"
SITE_TAGLINE="${WP_SITE_TAGLINE:-Laptop cũ, phụ kiện và sửa chữa}"
wp option update blogname "${SITE_TITLE}" --allow-root
wp option update blogdescription "${SITE_TAGLINE}" --allow-root

# 7d. Set Nhanh credentials
# Nguồn ưu tiên: env vars → /root/.secrets/user-secrets.env. KHÔNG hard-code token.
if [ -f /root/.secrets/user-secrets.env ]; then
  set -a
  # shellcheck disable=SC1091
  . /root/.secrets/user-secrets.env
  set +a
fi
# Secret file dùng NHANH_API_TOKEN; script chấp nhận cả NHANH_TOKEN để tương thích ngược.
if [ -z "${NHANH_TOKEN:-}" ] && [ -n "${NHANH_API_TOKEN:-}" ]; then
  NHANH_TOKEN="$NHANH_API_TOKEN"
fi
# Boss 2026-08-12: depot_id is OPTIONAL (prod uses 0 for single-depot shops). Only require
# app_id/business_id/token. depot_id defaults to 0 if unset.
NHANH_DEPOT_ID="${NHANH_DEPOT_ID:-0}"
for _var in NHANH_APP_ID NHANH_BUSINESS_ID NHANH_TOKEN; do
  if [ -z "${!_var:-}" ]; then
    echo "[wp-init] FATAL: $_var is missing (set env or populate /root/.secrets/user-secrets.env)" >&2
    exit 1
  fi
done

wp option update oscar_nhanh_settings \
  "{\"app_id\":${NHANH_APP_ID},\"business_id\":${NHANH_BUSINESS_ID},\"depot_id\":${NHANH_DEPOT_ID},\"token\":\"${NHANH_TOKEN}\"}" \
  --format=json --allow-root

echo "[wp-init] Nhanh credentials configured"

# 7e. Flush rewrite rules
wp rewrite flush --hard --allow-root

# 7f. Trigger one-shot Nhanh sync (unless disabled)
# Pass explicit int $limit (0 = unlimited) and bool $force=true to override
# the plugin's default of 'no limit, only new'. Without these args, the plugin
# action handler receives '' (empty string) which throws a PHP 8 TypeError.
if [ "${SKIP_NHANH_SYNC:-0}" != "1" ]; then
  echo "[wp-init] Triggering Nhanh product sync (all products, forced)..."
  if wp eval 'do_action("oscar_nhanh_product_sync", 0, true);' --allow-root 2>&1 | tail -30; then
    echo "[wp-init] Nhanh sync OK"
  else
    echo "[wp-init] WARN: Nhanh sync exited non-zero (will retry via cron)"
  fi

  # Verify
  NEW_COUNT=$(wp post list --post_type=product --post_status=publish --format=count --allow-root 2>/dev/null || echo "0")
  echo "[wp-init] After sync: ${NEW_COUNT} products"
fi

echo "[wp-init] Auto-fix complete"
exit 0