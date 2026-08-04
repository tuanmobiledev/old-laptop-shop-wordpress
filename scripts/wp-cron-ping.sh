#!/bin/bash
# Boss 2026-07-30 — Hermes-side prod sync trigger
# WHY: WP-Cron pseudo-cron does NOT fire on maytinhthuduc.com even when
# wp-cron.php is hit (verified 2026-07-30 10:30 UTC: HTTP 200 returned,
# inventory_next_due did NOT move forward). Scheduled events stay stuck.
#
# FIX: bypass WP-Cron entirely. Login → get nonce → POST /nhanh/sync?limit=0.
# This calls sync_products() which upserts all 85 products (incl. stock).
#
# Watchdog pattern (no_agent=True cron):
# - silent on success
# - ALERT message + exit 1 on HTTP != 200 OR login failure

set -u

SECRETS="/root/.secrets/user-secrets.env"
COOKIES="/tmp/hermes_wp_cookies.txt"
SITE="https://maytinhthuduc.com"
RESULT_FILE="/tmp/hermes_sync_result.json"
NOW_UTC=$(date -u +%FT%TZ)

if [ ! -f "$SECRETS" ]; then
  echo "ALERT ${NOW_UTC}: secrets file missing: $SECRETS"
  exit 1
fi

ADMIN_PW=$(grep -E '^WORDPRESS_OSCAR_PASSWORD=' "$SECRETS" | cut -d= -f2 | tr -d "'\"")

if [ -z "$ADMIN_PW" ]; then
  echo "ALERT ${NOW_UTC}: WORDPRESS_OSCAR_PASSWORD empty in $SECRETS"
  exit 1
fi

# Step 1 — wp-login.php form auth (cookies persist ~14d; saving is harmless)
LOGIN_HTTP=$(curl -sS -m 30 -c "$COOKIES" -b "$COOKIES" \
  -o /dev/null -w '%{http_code}' \
  --data-urlencode "log=admin" \
  --data-urlencode "pwd=${ADMIN_PW}" \
  --data-urlencode "wp-submit=Log In" \
  --data-urlencode "redirect_to=${SITE}/wp-admin/" \
  --data-urlencode "testcookie=1" \
  "${SITE}/wp-login.php")

case "$LOGIN_HTTP" in
  302|200) ;;
  *)
    echo "ALERT ${NOW_UTC}: wp-login.php HTTP=$LOGIN_HTTP"
    rm -f "$COOKIES"
    exit 1
    ;;
esac

# Step 2 — fresh nonce
NONCE=$(curl -sS -m 10 -b "$COOKIES" \
  "${SITE}/wp-admin/admin-ajax.php?action=rest-nonce")

if [ -z "$NONCE" ]; then
  echo "ALERT ${NOW_UTC}: empty rest-nonce (cookies may be stale)"
  rm -f "$COOKIES"
  exit 1
fi

# Step 3 — POST /oscar/v1/nhanh/sync?limit=0 (full sync)
SYNC_HTTP=$(curl -sS -m 90 -X POST -b "$COOKIES" -H "X-WP-Nonce: ${NONCE}" \
  -o "$RESULT_FILE" -w '%{http_code}' \
  "${SITE}/index.php?rest_route=/oscar/v1/nhanh/sync&limit=0")

if [ "$SYNC_HTTP" = "200" ]; then
  UPDATED=$(python3 -c "import json,sys; d=json.load(open('$RESULT_FILE')); print(d.get('products',{}).get('updated','?'))" 2>/dev/null || echo "?")
  ERRORS=$(python3 -c "import json,sys; d=json.load(open('$RESULT_FILE')); print(len(d.get('products',{}).get('errors',[])))" 2>/dev/null || echo "?")
  if [ "$ERRORS" != "0" ] && [ -n "$ERRORS" ]; then
    echo "ALERT ${NOW_UTC}: sync HTTP=200 but errors=$ERRORS (updated=$UPDATED)"
    exit 1
  fi
  # Silent on success — watchdog pattern
  exit 0
fi

echo "ALERT ${NOW_UTC}: POST /nhanh/sync HTTP=$SYNC_HTTP body=$(head -c 200 "$RESULT_FILE" 2>/dev/null)"
exit 1
