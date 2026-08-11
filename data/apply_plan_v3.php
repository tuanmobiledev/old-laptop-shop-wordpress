<?php
/**
 * Apply v3 specs plan to WooCommerce products via direct DB writes.
 * Boss 2026-08-11: replaces specs-fix-2026-07-27/apply_plan.php (uses REST).
 * Direct write is faster + doesn't need auth cookies for fresh deploy.
 *
 * Usage:
 *   wp eval-file /var/www/html/wp-content/uploads/apply_plan_v3.php /tmp/final_plan_v3.json --user=admin --allow-root
 *
 * Or via REST (production):
 *   curl -X POST .../wp-json/oscar/v1/specs/apply -d @plan.json --cookie "..." -H "X-WP-Nonce: ..."
 */

if (php_sapi_name() !== 'cli' && !defined('ABSPATH')) {
    define('ABSPATH', '/var/www/html/');
    require ABSPATH . 'wp-load.php';
}

$plan_path = $argv[1] ?? '/tmp/final_plan_v3.json';
if (!file_exists($plan_path)) { fwrite(STDERR, "Plan not found: $plan_path\n"); exit(1); }
$plan = json_decode(file_get_contents($plan_path), true);
if (!is_array($plan)) { fwrite(STDERR, "Invalid plan JSON\n"); exit(1); }

// Boss 2026-08-11 Q1-C: default badge "3 tháng" for all products
$default_badge = '3 tháng';

$stats = [
    'products'          => 0,
    'accessories_skipped' => 0,
    'meta_written'      => 0,
    'meta_skipped_same' => 0,
    'not_found'         => [],
    'errors'            => [],
];

global $wpdb;

foreach ($plan as $item) {
    $code = $item['code'] ?? '';
    if (!$code) continue;

    // Skip accessories
    if (!empty($item['is_accessory'])) {
        $stats['accessories_skipped']++;
        continue;
    }

    $stats['products']++;

// Boss 2026-08-11: race-free + skip-trash SKU lookup (matches sync plugin fix).
    // Trash filter prevents ghost meta from wp_trash_post dups returning wrong post_id.
    $post_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT pm.post_id FROM {$wpdb->postmeta} pm
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
           AND p.post_status IN ('publish', 'draft', 'private')
         LIMIT 1",
        $code
    ));

    if (!$post_id) {
        $stats['not_found'][] = $code;
        continue;
    }

    // Boss 2026-08-11: sync plugin skips upsert when _nhanh_updated_at matches,
    // so categoryId/brandId are never written on subsequent syncs. Apply writes them here.
    if (!empty($item['category_id'])) {
        $cur = (string) get_post_meta($post_id, '_nhanh_category_id', true);
        $val = (string) (int) $item['category_id'];
        if ($cur !== $val) {
            if (update_post_meta($post_id, '_nhanh_category_id', $val)) $stats['meta_written']++;
        } else {
            $stats['meta_skipped_same']++;
        }
    }
    if (!empty($item['brand_id'])) {
        $cur = (string) get_post_meta($post_id, '_nhanh_brand_id', true);
        $val = (string) (int) $item['brand_id'];
        if ($cur !== $val) {
            if (update_post_meta($post_id, '_nhanh_brand_id', $val)) $stats['meta_written']++;
        } else {
            $stats['meta_skipped_same']++;
        }
    }

    // Build writes with default badge
    $writes = $item['writes'] ?? [];
    $writes['_oscar_badge_vi'] = $default_badge;

    foreach ($writes as $key => $value) {
        $value = (string) $value;
        $cur = (string) get_post_meta($post_id, $key, true);
        if ($cur === $value) {
            $stats['meta_skipped_same']++;
            continue;
        }
        $r = update_post_meta($post_id, $key, $value);
        if ($r === false) {
            $stats['errors'][] = "$code $key: update_post_meta failed";
        } else {
            $stats['meta_written']++;
        }
    }
}

echo "=== APPLY COMPLETE ===\n";
echo "Products processed:         {$stats['products']}\n";
echo "Accessories skipped:        {$stats['accessories_skipped']}\n";
echo "Meta values written:        {$stats['meta_written']}\n";
echo "Meta values same (skip):    {$stats['meta_skipped_same']}\n";
if (!empty($stats['not_found'])) {
    echo "Not found: " . implode(', ', $stats['not_found']) . "\n";
}
if (!empty($stats['errors'])) {
    echo "Errors:\n";
    foreach ($stats['errors'] as $e) echo "  - $e\n";
}
