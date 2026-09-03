<?php
/**
 * Plugin Name: OSCAR Nhanh.vn Sync
 * Description: Đồng bộ sản phẩm, giá, tồn kho, ảnh từ Nhanh.vn về WooCommerce (Nhanh API v3).
 * Version: 2.0.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 *
 * Migration notes (v2 → v3):
 * - Auth: accessToken in body → Authorization header
 * - Body: form-encoded JSON string → raw JSON body
 * - URL: append ?appId=&businessId=
 * - Endpoints: /api/product/search → /v3.0/product/list; /api/product/externalimage → /v3.0/product/externalimage
 * - Status: string ("New","Active","Inactive","Deleted") → int (1,2,3,4)
 * - Prices: price/oldPrice/importPrice top-level → prices.retail/old/import nested
 * - Inventory: structure unchanged (inventory.available, .remain)
 * - shippingWeight: top-level → shipping.weight
 * - Description + content giờ có sẵn trong /product/list (không cần /product/detail)
 * - Warranty: read-only qua API (field luôn trả [] nếu chưa set), v2/v3 đều không update được
 *
 * Update 2026-08-20 (R1+R2):
 * - R1: Added info-level logging for sync start/end + per-product batch, plus
 *      oscar_nhanh_last_sync_at Unix timestamp option (cron watchdog).
 * - R2: Fixed image-data loss in detail merge — array_merge($n, $detail) was
 *      clobbering $n['images'] when $detail['images'] was null. Replaced with
 *      a null-safe merge that only writes non-null values from $detail into
 *      the list payload.
 */

defined('ABSPATH') || exit;

final class Oscar_Nhanh_Sync {
    private const API_V3 = 'https://pos.open.nhanh.vn/v3.0';
    private const OPTION = 'oscar_nhanh_settings';
    private const LOG = 'oscar-nhanh';

    public static function boot(): void {
        add_action('rest_api_init', [self::class, 'routes']);
        add_action('oscar_nhanh_product_sync', [self::class, 'sync_products']);
        add_filter('cron_schedules', static function(array $s): array {
            $s['oscar_15_minutes'] = ['interval' => 900, 'display' => 'Moi 15 phut'];
            return $s;
        });
        add_action('init', [self::class, 'schedule']);
        // Boss 2026-07-31: persistent cron blocker.
        // Pre-update filter strips these hooks every time cron option is saved,
        // so re-spawned entries from WC/AS/WP-core get nuked before they persist.
        //   - action_scheduler_run_queue     (AS heartbeat, not needed)
        //   - wc_admin_process_orders_milestone, wc_admin_unsnooze_admin_notes (WC admin noise)
        //   - woocommerce_marketplace_cron_fetch_promotions (marketplace spam)
        //   - wp_privacy_delete_old_export_files (GDPR cleanup overkill)
        add_filter('pre_update_option_cron', [self::class, 'cron_block_update'], 999);
    }

    /** Hooks that should never persist in WP-Cron. Edit here to allow/disallow. */
    private static function blocked_cron_hooks(): array {
        return [
            'action_scheduler_run_queue',
            'wc_admin_process_orders_milestone',
            'wc_admin_unsnooze_admin_notes',
            'woocommerce_marketplace_cron_fetch_promotions',
            'wp_privacy_delete_old_export_files',
        ];
    }

    public static function cron_block_update($value, $old_value = null, $option = null) {
        if (!is_array($value)) return $value;
        $blocked = self::blocked_cron_hooks();
        foreach ($value as $ts => $hooks) {
            if (is_array($hooks)) {
                foreach ($blocked as $h) unset($value[$ts][$h]);
                if (empty($value[$ts])) unset($value[$ts]);
            }
        }
        return $value;
    }

    public static function schedule(): void {
        // Only product sync is scheduled automatically.
        if (!wp_next_scheduled('oscar_nhanh_product_sync')) wp_schedule_event(time() + 300, 'hourly', 'oscar_nhanh_product_sync');
    }

    public static function routes(): void {
        register_rest_route('oscar/v1', '/nhanh/sync', [
            'methods' => 'POST', 'callback' => [self::class, 'run_sync'],
            'permission_callback' => static fn(): bool => current_user_can('manage_woocommerce'),
        ]);
    }

    public static function run_sync(WP_REST_Request $r = null): WP_REST_Response {
        $limit = $r ? absint($r->get_param('limit')) : 0;
        $force = $r && $r->get_param('force') === 'true';
        $products = self::sync_products($limit ?: 0, $force);
        return new WP_REST_Response(['products' => $products]);
    }

    private static function settings(): array { return (array)get_option(self::OPTION, []); }

    /**
     * Low-level v3 request helper.
     * Auth: Authorization header (no Bearer prefix).
     * Body: raw JSON.
     * URL: {path}?appId=&businessId=
     */
    private static function request_v3(string $path, array $body, bool $is_array_body = false): array|WP_Error {
        $s = self::settings();
        if (empty($s['token']) || empty($s['app_id']) || empty($s['business_id'])) {
            return new WP_Error('not_configured', 'Nhanh.vn chua duoc cau hinh.');
        }
        $url = self::API_V3 . $path . '?appId=' . (int)$s['app_id'] . '&businessId=' . (int)$s['business_id'];
        $payload = $is_array_body ? array_values($body) : $body;
        // Raw JSON body, no escaping needed (PHP encodes UTF-8 fine).
        $json_body = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $r = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => (string)$s['token'],
                'Content-Type' => 'application/json',
            ],
            'body' => $json_body,
        ]);
        if (is_wp_error($r)) return $r;
        $raw = wp_remote_retrieve_body($r);
        $decoded = json_decode($raw, true);
        if (wp_remote_retrieve_response_code($r) !== 200 || !is_array($decoded) || (int)($decoded['code'] ?? 0) !== 1) {
            $msg = is_array($decoded) ? ($decoded['messages'] ?? $decoded['errorCode'] ?? $decoded) : 'Nhanh.vn API error';
            return new WP_Error('nhanh_api', is_string($msg) ? $msg : wp_json_encode($msg), $decoded);
        }
        return $decoded;
    }

    /**
     * Fetch all products from Nhanh v3 list (paginates transparently).
     * v3 returns description + content + prices + inventory in single call.
     * No SKU prefix or status filter — sync everything Nhanh exposes.
     */
    private static function nhanh_products($limit = 0): array {
        $all = [];
        $next = null;
        $pages = 0;
        do {
            $body = [
                'paginator' => [
                    'size' => 100,
                    'sort' => ['id' => 'desc'],
                ],
            ];
            if ($next !== null) $body['paginator']['next'] = $next;
            $r = self::request_v3('/product/list', $body);
            if (is_wp_error($r)) { self::log('list: ' . $r->get_error_message()); break; }
            foreach ((array)($r['data'] ?? []) as $item) {
                $all[] = $item;
                if ($limit > 0 && count($all) >= $limit) return $all;
            }
            $next = $r['paginator']['next'] ?? null;
            $pages++;
            if ($pages > 100) break;  // safety cap = 10k products
        } while ($next);
        return $all;
    }

    public static function sync_products(int $limit = 0, bool $force = false): array {
        if (!class_exists('WooCommerce')) return ['error' => 'WooCommerce is not active'];
        // Boss 2026-07-30: Imagick thumbnail generation can exceed 30s for high-res laptop photos.
        // Boss 2026-08-01: increase to 900s (15min) to allow 86 products + 350 images to sync
        // in a single run. Original 180s only processed ~30 products before timeout.
        @set_time_limit(900);
        wp_raise_memory_limit('image');
        // Boss 2026-08-01: actually enable the intermediate-sizes filter. Previously the
        // filter callback was registered but the $_oscar_nhanh_skip_intermediate global
        // was never set, so WP kept generating all intermediate sizes (thumbnail, medium,
        // medium_large, large, 1536x1536, 2048x2048, woocommerce_*) for every image —
        // ~6-8x slower than necessary. SPA reads _nhanh_source_url for full-size, so
        // we only need 'thumbnail' for admin UI.
        $GLOBALS['_oscar_nhanh_skip_intermediate'] = true;
        add_filter('intermediate_image_sizes_advanced', [self::class, 'filter_intermediate_sizes'], 10, 3);
        // Boss 2026-07-30: surface config errors instead of silent 0/0/0/[].
        $s = self::settings();
        if (empty($s['token']) || empty($s['app_id']) || empty($s['business_id'])) {
            $err = ['error' => 'Nhanh.vn chưa cấu hình. Vào Oscar Nhanh Sync → Settings nhập token + app_id + business_id.'];
            update_option('oscar_nhanh_last_product_sync', current_time('mysql'), false);
            update_option('oscar_nhanh_last_inventory_sync', current_time('mysql'), false);
            update_option('oscar_nhanh_last_result', $err, false);
            self::log('sync_products: ' . $err['error']);
            return $err;
        }
        $created = 0; $updated = 0; $skipped = 0; $errors = []; $images_downloaded = 0;
        // Boss 2026-08-20 (R1): measure wall time + log batch start so silent crons are debuggable.
        $sync_started_at = microtime(true);
        $sync_started_iso = current_time('mysql');
        self::log(sprintf('sync_products: started limit=%d force=%d at=%s', $limit, $force ? 1 : 0, $sync_started_iso), 'info');
        // Step 1: /product/list paginated → identify needs-create / needs-update via updatedAt.
        foreach (self::nhanh_products($limit) as $n) {
            try {
                $nhanh_id = (int)($n['id'] ?? 0);
                $code = (string)($n['code'] ?? '');
                $remote_updated_at = (int)($n['updatedAt'] ?? 0);
                if (!$nhanh_id || !$code) { $skipped++; continue; }

                // Boss 2026-07-30: skip when local _nhanh_updated_at >= Nhanh's updatedAt
                // Boss 2026-07-30: force=true bypasses filter (re-sync all)
                // Boss 2026-08-11: replace wc_get_product_id_by_sku() with direct wpdb query.
                // wc_get_product_id_by_sku() returns false-negative during bulk sync (race
                // with WC SKU lookup table index), causing duplicate products (incident 2026-08-11:
                // 119 posts / 82 unique SKUs / 34 dups). Direct query is race-free.
                // Boss 2026-08-11: also JOIN wp_posts to skip trashed posts (LIMIT 1 may
                // return ghost meta from trashed dups after dedupe wp_trash_post).
                global $wpdb;
                $wc_id = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT pm.post_id FROM {$wpdb->postmeta} pm
                     JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                     WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
                       AND p.post_status IN ('publish', 'draft', 'private')
                     LIMIT 1",
                    $code
                ));
                if (!$force && $wc_id && $remote_updated_at > 0) {
                    $local_updated_at = (int)get_post_meta($wc_id, '_nhanh_updated_at', true);
                    if ($local_updated_at >= $remote_updated_at) {
                        $skipped++;
                        continue;
                    }
                }

                // Step 2: /product/detail for full data (description, content, full image set)
                $detail_resp = self::request_v3('/product/detail', ['filters' => ['id' => $nhanh_id]]);
                if (is_wp_error($detail_resp)) {
                    $errors[] = "$code: " . $detail_resp->get_error_message();
                    self::log("detail $code: " . $detail_resp->get_error_message());
                    continue;
                }
                $detail = $detail_resp['data'] ?? [];
                // Boss 2026-08-20 (R2): null-safe merge — array_merge($n, $detail)
                // overwrites $n['images'] when $detail['images'] is null (which happens
                // when Nhanh's /product/detail payload omits the field), silently
                // dropping images that were present in /product/list. Only write
                // non-null values from $detail so list data wins for null gaps.
                $merged = $n;
                foreach ((array)$detail as $k => $v) {
                    if ($v !== null) $merged[$k] = $v;
                }

                // Step 3: upsert WC product with full data
                $result = self::upsert_product($merged);
                if (!$result) { $skipped++; continue; }
                $wc_id_final = (int)$result['wc_id'];

                // Step 4: download images from Nhanh CDN → wp-content/uploads, attach as featured + gallery
                if (!empty($merged['images'])) {
                    $img_result = self::sync_product_images($wc_id_final, (array)$merged['images']);
                    $images_downloaded += (int)($img_result['downloaded'] ?? 0);
                    foreach ((array)($img_result['errors'] ?? []) as $ie) self::log("img $code: $ie");
                }

                !empty($result['created']) ? $created++ : $updated++;

                // Rate limit: 150 req/30s → 200ms per request
                usleep(200000);
            } catch (Throwable $e) {
                $errors[] = ($n['code'] ?? $n['id'] ?? 'unknown') . ': ' . $e->getMessage();
                self::log(end($errors));
            }
        }
        $result = compact('created', 'updated', 'skipped', 'errors', 'images_downloaded');
        // Boss 2026-08-20 (R1): write start time + duration for external watchdog
        // (Hermes cron POSTs /oscar/v1/nhanh/sync every 15min — this lets us see
        // if the plugin actually ran, not just that the HTTP request returned).
        $result['_started_at'] = $sync_started_iso;
        $result['_duration_ms'] = (int)round((microtime(true) - $sync_started_at) * 1000);
        update_option('oscar_nhanh_last_product_sync', current_time('mysql'), false);
        update_option('oscar_nhanh_last_inventory_sync', current_time('mysql'), false);
        update_option('oscar_nhanh_last_sync_at', time(), false);
        update_option('oscar_nhanh_last_result', $result, false);
        // Boss 2026-08-20 (R1): info-level end-of-batch log so success runs are
        // visible in WooCommerce > Status > Logs without grepping stderr.
        self::log(sprintf(
            'sync_products: done in %dms (created=%d updated=%d skipped=%d errors=%d images=%d)',
            $result['_duration_ms'], $created, $updated, $skipped, count($errors), $images_downloaded
        ), 'info');
        return $result;
    }

    /**
     * Download Nhanh product images (avatar + others) to wp-content/uploads,
     * attach first as featured image, rest as gallery.
     * De-dupe by Nhanh source URL (meta _nhanh_source_url) so re-sync doesn't re-download.
     */
    private static function sync_product_images(int $wc_id, array $images): array {
        $result = ['downloaded' => 0, 'featured' => 0, 'gallery' => 0, 'errors' => []];
        if (!$wc_id) return $result;
        $avatar = (string)($images['avatar'] ?? '');
        $others = (array)($images['others'] ?? []);
        $urls = [];
        if ($avatar !== '') $urls[] = $avatar;
        foreach ($others as $u) { if (is_string($u) && $u !== '') $urls[] = $u; }
        if (!$urls) return $result;

        $ids = [];
        foreach ($urls as $url) {
            try {
                $att_id = self::download_image($url, $wc_id);
                if ($att_id) $ids[] = $att_id;
            } catch (Throwable $e) {
                $result['errors'][] = basename(parse_url($url, PHP_URL_PATH) ?: 'img') . ': ' . $e->getMessage();
            }
        }
        if (!$ids) return $result;

        $featured = $ids[0];
        $gallery = array_slice($ids, 1);
        set_post_thumbnail($wc_id, $featured);
        if ($gallery) {
            $product = wc_get_product($wc_id);
            if ($product) {
                $product->set_gallery_image_ids($gallery);
                $product->save();
            }
        }
        $result['downloaded'] = count($ids);
        $result['featured'] = 1;
        $result['gallery'] = count($gallery);
        return $result;
    }

    /**
     * Sideload a remote image into WordPress media library.
     * Returns existing attachment ID if URL already downloaded (via _nhanh_source_url meta).
     *
     * Boss 2026-09-03: after sideload, convert JPG/PNG to WebP (quality 100 lossy)
     * and store the webp URL in `_oscar_image_webp_url` meta for SPA consumption.
     * Webp conversion failure is logged but does NOT break sync — original JPG
     * attachment is still attached as featured/gallery.
     */
    private static function download_image(string $url, int $parent_post_id): ?int {
        global $wpdb;
        $url = esc_url_raw($url);
        if (!$url) return null;
        $existing = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_nhanh_source_url' AND meta_value = %s LIMIT 1",
            $url
        ));
        if ($existing) return $existing;

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 30);
        if (is_wp_error($tmp)) return null;

        $filename = basename(parse_url($url, PHP_URL_PATH) ?: ('nhanh-' . wp_generate_password(8, false) . '.jpg'));
        $file_array = ['name' => $filename, 'tmp_name' => $tmp];

        $att_id = media_handle_sideload($file_array, $parent_post_id);
        if (is_wp_error($att_id)) {
            @unlink($tmp);
            return null;
        }
        update_post_meta((int)$att_id, '_nhanh_source_url', $url);

        // Webp conversion (boss 2026-09-03: quality 100 lossy — chosen over lossless
        // because lossless webp is 3x larger than JPG. lossy q=100 is visually
        // indistinguishable from JPG q=85 Nhanh default).
        $attached_file = get_attached_file((int)$att_id);
        if ($attached_file) {
            $webp_path = self::convert_to_webp($attached_file);
            if ($webp_path) {
                $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', wp_get_attachment_url((int)$att_id));
                if ($webp_url) update_post_meta((int)$att_id, '_oscar_image_webp_url', $webp_url);
            }
        }

        return (int)$att_id;
    }

    /**
     * Convert a JPG/PNG image to WebP (quality 100 lossy) in the same folder.
     * Idempotent: returns the existing webp path if already converted.
     * Returns null on failure (logged via self::log()).
     *
     * Boss 2026-09-03: chose lossy q=100 over lossless because lossless webp is
     * 3x LARGER than JPG (verified: 679KB JPG → 2.1MB lossless webp on Dell XPS
     * 1926x2568). Lossy q=100 keeps file size close to original while staying
     * pixel-close to Nhanh's default q=85 JPG.
     */
    public static function convert_to_webp(string $source_path): ?string {
        if (!is_file($source_path)) return null;
        $ext = strtolower(pathinfo($source_path, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) return null;

        $webp_path = preg_replace('/\.(jpe?g|png)$/i', '.webp', $source_path);
        if (is_file($webp_path)) return $webp_path;

        try {
            wp_raise_memory_limit('image');
            $im = new \Imagick($source_path);
            // Cap per-image memory to prevent OOM on huge laptop photos
            // (sync boss log 2026-07-30: 30s+ Imagick timeouts on 2048x2048+).
            $im->setResourceLimit(\Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality(100);
            // Explicit lossless=false (default, but be explicit since boss choice).
            $im->setOption('webp:lossless', 'false');
            $im->writeImage($webp_path);
            $im->destroy();
            return is_file($webp_path) ? $webp_path : null;
        } catch (\Throwable $e) {
            self::log('webp convert failed: ' . basename($source_path) . ': ' . $e->getMessage());
            return null;
        }
    }

    /**
     * One-time: pull description + content for all OSCAR products via v3 /product/detail.
     * Run once after v3 migration to backfill WP product descriptions.
     */
    public static function sync_descriptions(): array {
        if (!class_exists('WooCommerce')) return ['error' => 'WooCommerce is not active'];
        $updated = 0; $skipped = 0; $errors = [];
        foreach (self::nhanh_products() as $n) {
            $code = (string)($n['code'] ?? '');
            $nhanh_id = (int)($n['id'] ?? 0);
            if (!$code || !$nhanh_id) continue;
            $r = self::request_v3('/product/detail', ['filters' => ['id' => $nhanh_id]]);
            if (is_wp_error($r)) {
                $errors[] = "$code: " . $r->get_error_message();
                self::log("desc sync $code: " . $r->get_error_message());
                continue;
            }
            $detail = $r['data'] ?? [];
            $desc = (string)($detail['description'] ?? '');
            $cont = (string)($detail['content'] ?? '');
            if (!$desc && !$cont) { $skipped++; continue; }
            $wc_id = wc_get_product_id_by_sku($code);
            if (!$wc_id) { $skipped++; continue; }
            $product = wc_get_product($wc_id);
            if (!$product) { $skipped++; continue; }
            if ($desc) $product->set_short_description(wp_kses_post($desc));
            if ($cont) $product->set_description(wp_kses_post($cont));
            $product->save();
            $updated++;
            // Rate limit safety: 150 req/30s
            usleep(200000);
        }
        return compact('updated', 'skipped', 'errors');
    }

    private static function find_wc_by_nhanh_id(int $nhanh_id): int {
        $q = new WP_Query([
            'post_type' => ['product', 'product_variation'],
            'post_status' => 'any',
            'meta_query' => [[
                'key' => '_nhanh_product_id',
                'value' => $nhanh_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ]],
            'fields' => 'ids',
            'posts_per_page' => 1,
        ]);
        return $q->posts ? (int)$q->posts[0] : 0;
    }

    private static function upsert_product(array $n): ?array {
        $code = (string)($n['code'] ?? '');
        if (!$code) return null;
        // Boss 2026-08-11: wpdb query for race-free SKU lookup. JOIN wp_posts to skip
        // trashed posts (LIMIT 1 may return ghost meta from trashed dups after dedupe).
        global $wpdb;
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key = '_sku' AND pm.meta_value = %s
               AND p.post_status IN ('publish', 'draft', 'private')
             LIMIT 1",
            $code
        ));
        $is_new = false;
        if ($id) {
            $product = wc_get_product($id);
        } else {
            $product = new WC_Product_Simple();
            $product->set_sku($code);
            $product->update_meta_data('_oscar_created_by_sync', 1);
            $is_new = true;
        }
        if (!$product) return null;

        $name = (string)($n['name'] ?? $code);
        // v3 prices are nested under prices.{retail,old,import}
        $prices = (array)($n['prices'] ?? []);
        $price = self::money($prices['retail'] ?? 0);
        $old_price = self::money($prices['old'] ?? 0);
        $import_price = self::money($prices['import'] ?? 0);
        // v3 status is int (1=New, 2=Active, 3=Stopped, 4=Out of stock)
        $status_int = (int)($n['status'] ?? 2);
        $status_offline = in_array($status_int, [3, 4], true);
        // v3 inventory structure identical to v2
        $available = (int)($n['inventory']['available'] ?? $n['inventory']['remain'] ?? 0);
        // v3 shipping weight is nested under shipping.weight (in grams)
        $weight = (int)($n['shipping']['weight'] ?? 2500);

        $product->set_name($name);
        // Boss 2026-07-27: luôn publish, bỏ qua Nhanh status 3/4 (Stopped/Out of stock).
        // Status thật của Nhanh vẫn lưu ở meta `_nhanh_status` để tham khảo.
        $product->set_status('publish');
        if ($old_price > $price && $price > 0) {
            $product->set_regular_price((string)$old_price);
            $product->set_sale_price((string)$price);
        } else {
            $product->set_regular_price((string)$price);
            $product->set_sale_price('');
        }
        $product->set_catalog_visibility('visible');
        $product->set_manage_stock(true);
        $product->set_stock_quantity(max(0, $available));
        $product->set_stock_status($available > 0 ? 'instock' : 'outofstock');
        $product->set_weight((string)max(0, $weight));
        // v3 list returns description (short) + content (full) — both kept in sync from Nhanh
        if (!empty($n['description'])) $product->set_short_description(wp_kses_post((string)$n['description']));
        if (!empty($n['content'])) $product->set_description(wp_kses_post((string)$n['content']));

        $product->update_meta_data('_nhanh_product_id', (int)($n['id'] ?? 0));
        $product->update_meta_data('_nhanh_status', $status_int);
        $product->update_meta_data('_nhanh_import_price', $import_price);
        $product->update_meta_data('_nhanh_updated_at', (int)($n['updatedAt'] ?? 0));
        // Boss 2026-08-11: also persist Nhanh categoryId + brandId for analytics + Phase 2 specs lookup
        if (!empty($n['categoryId'])) $product->update_meta_data('_nhanh_category_id', (int)$n['categoryId']);
        if (!empty($n['brandId'])) $product->update_meta_data('_nhanh_brand_id', (int)$n['brandId']);
        // v3 warranty is read-only via API; track locally for reference
        $warranty = (array)($n['warranty'] ?? []);
        if (!empty($warranty['month'])) $product->update_meta_data('_nhanh_warranty_months', (int)$warranty['month']);
        if (preg_match('/OSCAR-(\d+)/', $code, $m)) $product->update_meta_data('_oscar_source_id', (int)$m[1]);

        // Boss 2026-08-06: sync plugin KHÔNG touch _oscar_badge_vi / _oscar_badge_en.
        // Badge hiển thị trên SPA là dữ liệu của riêng OSCAR (warranty tier 3-12 tháng tuỳ SP),
        // phải set thủ công qua /oscar/v1/specs/apply. Nhanh warranty.month là fallback, ko dùng để
        // tự động ghi đè badge hiển thị. Nếu sau này cần đồng bộ warranty Nhanh → badge, hãy
        // thêm logic mapping rõ ràng ở đây và update memory rule.

        self::assign_default_category($product);
        $product->save();
        if ($is_new) $product->update_meta_data('_oscar_created_by_sync', 1);
        return ['wc_id' => (int)$product->get_id(), 'created' => $is_new];
    }

    private static function assign_default_category(WC_Product $product): void {
        // Boss 2026-08-26: respect an existing category — don't overwrite e.g. laptop-moi set
        // by /oscar/v1/specs/apply or by a prior manual edit. Only default to "Laptop cu" when
        // the product has no category at all (genuinely new, freshly imported from Nhanh).
        // Pre-fix behavior unconditionally reset every synced product to laptop-cu, which made
        // HP OmniBook X Flip 16 (post 931) re-bucket into "Phụ kiện" (laptop-moi filter was lost).
        if (!empty($product->get_category_ids())) return;
        $term = term_exists('Laptop cu', 'product_cat');
        if (!$term) $term = wp_insert_term('Laptop cu', 'product_cat', ['slug' => 'laptop-cu']);
        if (!is_wp_error($term)) {
            $term_id = is_array($term) ? (int)$term['term_id'] : (int)$term;
            $product->set_category_ids([$term_id]);
        }
    }

    private static function money(mixed $value): int {
        if (is_numeric($value)) return max(0, (int)round((float)$value));
        return max(0, (int)preg_replace('/\D+/', '', (string)$value));
    }

    private static function log(string $message, string $level = 'error'): void {
        // Boss 2026-08-20 (R1): WC logger's error() helper is hardcoded; use log()
        // with explicit level so success/info batches land in oscar-nhanh-{date}.log
        // instead of being swallowed by the silent-success blind spot.
        if (function_exists('wc_get_logger')) wc_get_logger()->log($level, $message, ['source' => self::LOG]);
    }

    /**
     * Boss 2026-07-30: skip intermediate image sizes during cron sync to avoid
     * Imagick timeouts (>=30s) on high-res laptop photos. SPA reads the
     * full-size _nhanh_source_url via the URL field, not the WP-sized variants.
     */
    public static function filter_intermediate_sizes(array $sizes): array {
        if (!empty($GLOBALS['_oscar_nhanh_skip_intermediate'])) return ['thumbnail' => null];
        return $sizes;
    }

    /**
     * WP-CLI command (boss 2026-09-03): batch-convert existing JPG/PNG product
     * attachments to WebP (quality 100 lossy). Skips attachments that already
     * have a `_oscar_image_webp_url` meta.
     *
     * Usage:
     *   wp oscar convert-images-to-webp --dry-run
     *   wp oscar convert-images-to-webp --limit=50
     *   wp oscar convert-images-to-webp --product-only
     */
    public static function cli_convert_images_to_webp(array $args, array $assoc_args): void {
        if (!class_exists('WP_CLI')) return;
        $dry_run = !empty($assoc_args['dry-run']);
        $limit = isset($assoc_args['limit']) ? max(0, (int)$assoc_args['limit']) : 0;
        $product_only = !empty($assoc_args['product-only']);

        global $wpdb;
        $sql = "SELECT p.ID FROM $wpdb->posts p
                LEFT JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_oscar_image_webp_url'
                WHERE p.post_type = 'attachment'
                  AND p.post_mime_type IN ('image/jpeg','image/png')
                  AND (m.meta_id IS NULL OR m.meta_value = '')";
        if ($product_only) {
            $sql = "SELECT p.ID FROM $wpdb->posts p
                    LEFT JOIN $wpdb->postmeta m ON m.post_id = p.ID AND m.meta_key = '_oscar_image_webp_url'
                    INNER JOIN $wpdb->posts parent ON parent.ID = p.post_parent AND parent.post_type = 'product'
                    WHERE p.post_type = 'attachment'
                      AND p.post_mime_type IN ('image/jpeg','image/png')
                      AND (m.meta_id IS NULL OR m.meta_value = '')";
        }
        if ($limit) $sql .= " LIMIT " . (int)$limit;
        $ids = $wpdb->get_col($sql);
        WP_CLI::log(sprintf('Found %d attachments to convert%s.', count($ids), $product_only ? ' (product-only)' : ''));

        $ok = 0; $fail = 0; $skipped = 0;
        foreach ($ids as $id) {
            $attached_file = get_attached_file((int)$id);
            if (!$attached_file || !is_file($attached_file)) { $skipped++; continue; }
            if ($dry_run) {
                $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $attached_file);
                WP_CLI::log(sprintf('[dry-run] %d: %s -> %s', $id, basename($attached_file), basename($webp)));
                $ok++;
                continue;
            }
            $webp_path = self::convert_to_webp($attached_file);
            if ($webp_path) {
                $webp_url = preg_replace('/\.(jpe?g|png)$/i', '.webp', wp_get_attachment_url((int)$id));
                if ($webp_url) update_post_meta((int)$id, '_oscar_image_webp_url', $webp_url);
                $ok++;
                WP_CLI::log(sprintf('[ok] %d: %s', $id, basename($attached_file)));
            } else {
                $fail++;
                WP_CLI::warning(sprintf('[fail] %d: %s', $id, basename($attached_file)));
            }
        }
        WP_CLI::log(sprintf('Done. ok=%d fail=%d skipped=%d (mode=%s)', $ok, $fail, $skipped, $dry_run ? 'dry-run' : 'live'));
    }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('oscar convert-images-to-webp', [Oscar_Nhanh_Sync::class, 'cli_convert_images_to_webp']);
}

Oscar_Nhanh_Sync::boot();