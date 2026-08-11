<?php
/**
 * Plugin Name: OSCAR Shop Core
 * Description: Đồng bộ catalogue OSCAR với WooCommerce và cung cấp REST API cho storefront.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * Text Domain: oscar-shop-core
 */

defined('ABSPATH') || exit;

final class Oscar_Shop_Core
{
    private const SOURCE_ID_KEY = '_oscar_source_id';
    private const DATA_FILE = ABSPATH . 'oscar-data/catalog.json';

    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
        add_action('init', [self::class, 'register_taxonomies']);
        add_filter('woocommerce_product_data_tabs', [self::class, 'add_product_tab']);
        add_action('woocommerce_product_data_panels', [self::class, 'render_product_tab']);
        add_action('woocommerce_process_product_meta', [self::class, 'save_product_fields']);
        add_action('admin_notices', [self::class, 'dependency_notice']);
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('oscar import', [self::class, 'cli_import']);
        }
    }

    public static function dependency_notice(): void
    {
        if (!class_exists('WooCommerce')) {
            echo '<div class="notice notice-error"><p><strong>OSCAR Shop Core</strong> cần WooCommerce được cài đặt và kích hoạt.</p></div>';
        }
    }

    public static function register_taxonomies(): void
    {
        foreach (['brand' => 'Thương hiệu', 'cpu' => 'CPU', 'gpu' => 'GPU', 'ram' => 'RAM', 'ssd' => 'SSD', 'screen' => 'Màn hình', 'demand' => 'Nhu cầu'] as $slug => $label) {
            register_taxonomy('pa_' . $slug, ['product'], [
                'label' => $label,
                'public' => true,
                'show_ui' => true,
                'show_in_rest' => true,
                'hierarchical' => false,
                'rewrite' => false,
            ]);
        }
    }

    public static function register_routes(): void
    {
        register_rest_route('oscar/v1', '/products', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_products'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('oscar/v1', '/newsletter', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'newsletter'],
            'permission_callback' => '__return_true',
            'args' => ['email' => ['required' => true, 'sanitize_callback' => 'sanitize_email']],
        ]);
        register_rest_route('oscar/v1', '/addons', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [self::class, 'rest_addons'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('oscar/v1', '/orders', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'create_order'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route('oscar/v1', '/admin/session', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => static fn(): WP_REST_Response => new WP_REST_Response(['authenticated' => true]),
            'permission_callback' => static fn(): bool => current_user_can('manage_woocommerce'),
        ]);
        register_rest_route('oscar/v1', '/admin/media', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'upload_media'],
            'permission_callback' => static fn(): bool => current_user_can('manage_woocommerce') && current_user_can('upload_files'),
        ]);
        register_rest_route('oscar/v1', '/specs/apply', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'apply_specs'],
            'permission_callback' => static fn(): bool => current_user_can('manage_woocommerce'),
        ]);
        register_rest_route('oscar/v1', '/admin/fetch-image', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'fetch_image'],
            'permission_callback' => static fn(): bool => current_user_can('manage_woocommerce'),
        ]);
        register_rest_route('oscar/v1', '/admin/attach-product-images', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [self::class, 'attach_product_images'],
            'permission_callback' => static fn(): bool => current_user_can('manage_woocommerce'),
        ]);
    }

    public static function rest_products(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('WooCommerce')) {
            return new WP_REST_Response([], 503);
        }
        $products = wc_get_products(['status' => 'publish', 'limit' => -1, 'orderby' => 'menu_order', 'order' => 'ASC']);
        $products = array_values(array_filter($products, static fn(WC_Product $product): bool => !$product->get_meta('_oscar_catalog_type')));
        return new WP_REST_Response(array_map([self::class, 'serialize_product'], $products));
    }

    private static function serialize_product(WC_Product $product): array
    {
        $image_ids = array_values(array_filter(array_merge([$product->get_image_id()], $product->get_gallery_image_ids())));
        $field = static fn(string $key): string => (string) $product->get_meta('_oscar_' . $key);

        // Prefer Nhanh CDN URLs if stored in _nhanh_image_urls meta (JSON array).
        // Falls back to WP attachment URLs if not present.
        $nhanh_urls = (array) json_decode((string) $product->get_meta('_nhanh_image_urls'), true);
        $nhanh_urls = array_values(array_filter(array_map('esc_url_raw', $nhanh_urls)));
        $images_out = $nhanh_urls ?: array_values(array_filter(array_map(
            static fn(int $id) => wp_get_attachment_image_url($id, 'full'),
            $image_ids
        )));

        return [
            'id' => (int) ($product->get_meta(self::SOURCE_ID_KEY) ?: $product->get_id()),
            'wooId' => $product->get_id(),
            'name' => $product->get_name(),
            'category' => self::first_term_slug($product->get_id(), 'product_cat'),
            'brand' => $field('brand'),
            'cpu' => $field('cpu'),
            'gpu' => $field('gpu'),
            'ram' => $field('ram'),
            'ssd' => $field('ssd'),
            'screen' => $field('screen'),
            'batteryWh' => (float) $field('battery_wh'),
            'batteryRuntime' => $field('battery_runtime'),
            'demand' => $field('demand'),
            'stock' => $product->get_stock_quantity(),
            'price' => (float) $product->get_price(),
            'oldPrice' => (float) $product->get_regular_price(),
            'image' => $images_out[0] ?? wc_placeholder_img_src(),
            'images' => $images_out,
            'video' => $field('video'),
            'condition' => ['vi' => $field('condition_vi'), 'en' => $field('condition_en')],
            'badge' => ['vi' => $field('badge_vi'), 'en' => $field('badge_en')],
            'variants' => self::product_variants($product),
            'upgradeability' => [
                'ramMode' => $field('ram_mode'),
                'ramType' => $field('ram_type'),
                'ramSlots' => (int) $field('ram_slots'),
                'ramMax' => $field('ram_max'),
                'storageMode' => $field('storage_mode'),
                'storageType' => $field('storage_type'),
                'storageSlots' => (int) $field('storage_slots'),
                'confidence' => $field('upgrade_confidence'),
                'note' => $field('upgrade_note'),
            ],
        ];
    }

    private static function product_variants(WC_Product $product): array
    {
        $raw = (string) $product->get_meta('_oscar_variants');
        if ($raw === '') return [];
        $items = json_decode($raw, true);
        if (!is_array($items)) return [];
        $allowed = ['label', 'cpu', 'ram', 'ssd', 'screen', 'gpu', 'price', 'stockStatus', 'source', 'sourceDate'];
        return array_values(array_map(static function (array $item) use ($allowed): array {
            $result = [];
            foreach ($allowed as $key) {
                if (!array_key_exists($key, $item)) continue;
                $result[$key] = $key === 'price' ? (float) $item[$key] : sanitize_text_field((string) $item[$key]);
            }
            return $result;
        }, array_filter($items, 'is_array')));
    }

    private static function first_term_slug(int $post_id, string $taxonomy): string
    {
        $terms = wp_get_post_terms($post_id, $taxonomy);
        return !is_wp_error($terms) && $terms ? $terms[0]->slug : '';
    }

    public static function newsletter(WP_REST_Request $request): WP_REST_Response
    {
        $email = sanitize_email((string) $request->get_param('email'));
        if (!$email || !is_email($email)) {
            return new WP_REST_Response(['message' => 'Email không hợp lệ.'], 422);
        }
        $emails = array_values(array_unique(array_merge((array) get_option('oscar_newsletter_emails', []), [$email])));
        update_option('oscar_newsletter_emails', $emails, false);
        return new WP_REST_Response(['success' => true], 201);
    }

    public static function upload_media(WP_REST_Request $request)
    {
        if (empty($_FILES['file']['tmp_name'])) {
            return new WP_Error('oscar_missing_file', 'Vui lòng chọn file cần tải lên.', ['status' => 422]);
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $attachment_id = media_handle_upload('file', 0);
        if (is_wp_error($attachment_id)) return $attachment_id;
        return new WP_REST_Response([
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id),
        ], 201);
    }

    public static function rest_addons(): WP_REST_Response
    {
        $items = wc_get_products(['status' => 'publish', 'limit' => -1, 'meta_key' => '_oscar_review_required', 'meta_value' => 'yes']);
        $result = [];
        foreach ($items as $product) {
            $type = (string)$product->get_meta('_oscar_catalog_type');
            if (!in_array($type, ['accessory', 'service', 'warranty'], true)) continue;
            $result[] = [
                'wooId' => $product->get_id(), 'sku' => $product->get_sku(), 'name' => $product->get_name(),
                'price' => (float)$product->get_price(), 'type' => $type,
            ];
        }
        return new WP_REST_Response($result);
    }

    public static function create_order(WP_REST_Request $request): WP_REST_Response
    {
        if (!class_exists('WooCommerce')) return new WP_REST_Response(['message' => 'WooCommerce chưa sẵn sàng.'], 503);
        $data = $request->get_json_params();
        $name = sanitize_text_field((string)($data['name'] ?? ''));
        $phone = preg_replace('/[^0-9+]/', '', (string)($data['phone'] ?? ''));
        $ids = array_values(array_unique(array_filter(array_map('absint', (array)($data['productIds'] ?? [])))));
        if (mb_strlen($name) < 2 || !preg_match('/^(?:\+?84|0)[0-9]{8,10}$/', $phone) || !$ids || count($ids) > 12) {
            return new WP_REST_Response(['message' => 'Vui lòng kiểm tra họ tên, số điện thoại và sản phẩm.'], 422);
        }
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $rate_key = 'oscar_order_' . md5($ip);
        if (get_transient($rate_key)) return new WP_REST_Response(['message' => 'Vui lòng chờ trước khi gửi yêu cầu tiếp theo.'], 429);
        $main = wc_get_product($ids[0]);
        if (!$main || $main->get_status() !== 'publish' || $main->get_meta('_oscar_catalog_type')) {
            return new WP_REST_Response(['message' => 'Laptop không hợp lệ.'], 422);
        }
        $variant = null;
        $variant_index = !array_key_exists('variantIndex', $data) || $data['variantIndex'] === null || $data['variantIndex'] === '' ? null : absint($data['variantIndex']);
        $variants = self::product_variants($main);
        if ($variant_index !== null) {
            if (!isset($variants[$variant_index]) || empty($variants[$variant_index]['price'])) {
                return new WP_REST_Response(['message' => 'Phiên bản sản phẩm không hợp lệ.'], 422);
            }
            $variant = $variants[$variant_index];
        }
        $allowed_addons = [];
        foreach (array_slice($ids, 1) as $id) {
            $addon = wc_get_product($id);
            if (!$addon || $addon->get_status() !== 'publish' || $addon->get_meta('_oscar_review_required') !== 'yes') continue;
            $allowed_addons[] = $addon;
        }
        try {
            $order = wc_create_order();
            $order->set_billing_first_name($name);
            $order->set_billing_phone($phone);
            $order->set_customer_note(sanitize_textarea_field((string)($data['note'] ?? '')));
            $main_item_id = $variant
                ? $order->add_product($main, 1, ['subtotal' => (float)$variant['price'], 'total' => (float)$variant['price']])
                : $order->add_product($main, 1);
            if ($variant && $main_item_id) {
                $main_item = $order->get_item($main_item_id);
                foreach (['cpu' => 'CPU', 'ram' => 'RAM', 'ssd' => 'SSD', 'screen' => 'Màn hình', 'gpu' => 'GPU', 'stockStatus' => 'Tình trạng nguồn'] as $key => $label) {
                    if (!empty($variant[$key])) $main_item->add_meta_data($label, $variant[$key], true);
                }
                $main_item->add_meta_data('Phiên bản', $variant['label'] ?? ('Cấu hình ' . ($variant_index + 1)), true);
                $main_item->save();
                $order->update_meta_data('_oscar_variant_index', $variant_index);
            }
            foreach ($allowed_addons as $addon) $order->add_product($addon, 1);
            $order->calculate_totals();
            $order->update_meta_data('_oscar_storefront_order', 'yes');
            $order->save();
            $order->update_status('on-hold', 'Khách gửi yêu cầu cấu hình từ website.');
            set_transient($rate_key, 1, 60);
            return new WP_REST_Response(['success' => true, 'orderId' => $order->get_id(), 'total' => (float)$order->get_total()], 201);
        } catch (Throwable $e) {
            return new WP_REST_Response(['message' => 'Không thể tạo đơn lúc này.'], 500);
        }
    }

    /**
     * Apply spec meta (cpu/ram/ssd/screen/gpu/battery_wh/battery_runtime/demand/condition_vi/condition_en/warranty_months/brand/badge_vi/badge_en)
     * to a batch of products. Idempotent: skips writes where the existing meta matches.
     * Accepts JSON array: [{woo_id, cpu?, ram?, ssd?, screen?, gpu?, battery_wh?, battery_runtime?, demand?, condition_vi?, condition_en?, warranty_months?, brand?, badge_vi?, badge_en?}, ...]
     * Returns {success, updated, skipped_same, not_found, errors[]}.
     * Added 2026-07-30 — sync specs from oscar-descriptions.json.
     * Updated 2026-07-30 — added badge_vi/badge_en (Phase 1 of Nhanh→Woo sync).
     */
    public static function apply_specs(WP_REST_Request $request): WP_REST_Response
    {
        $items = $request->get_json_params();
        if (!is_array($items)) {
            return new WP_REST_Response(['message' => 'Payload không hợp lệ.'], 422);
        }
        $spec_fields = ['cpu', 'ram', 'ssd', 'screen', 'gpu', 'battery_wh', 'battery_runtime', 'demand', 'condition_vi', 'condition_en', 'warranty_months', 'brand', 'badge_vi', 'badge_en'];
        $stats = ['updated' => 0, 'skipped_same' => 0, 'not_found' => [], 'errors' => []];
        foreach ($items as $item) {
            $woo_id = absint($item['woo_id'] ?? 0);
            $product = $woo_id ? wc_get_product($woo_id) : null;
            if (!$product) {
                $stats['not_found'][] = $woo_id ?: '?';
                continue;
            }
            $writes = 0;
            try {
                foreach ($spec_fields as $field) {
                    if (!array_key_exists($field, $item)) continue;
                    $raw = $item[$field];
                    if ($raw === null || $raw === '') {
                        // explicit empty clears the field
                        $existing = (string) $product->get_meta('_oscar_' . $field);
                        if ($existing !== '') {
                            $product->update_meta_data('_oscar_' . $field, '');
                            $writes++;
                        }
                        continue;
                    }
                    // Cast per-field
                    if ($field === 'battery_wh' || $field === 'warranty_months') {
                        $value = (string) max(0, (int) $raw);
                    } else {
                        $value = sanitize_text_field((string) $raw);
                    }
                    $existing = (string) $product->get_meta('_oscar_' . $field);
                    if ($existing === $value) {
                        $stats['skipped_same']++;
                        continue;
                    }
                    $product->update_meta_data('_oscar_' . $field, $value);
                    $writes++;
                }
                if ($writes > 0) {
                    $product->save();
                    $stats['updated']++;
                }
            } catch (Throwable $e) {
                $stats['errors'][] = "woo_id=$woo_id: " . $e->getMessage();
            }
        }
        return new WP_REST_Response(['success' => true] + $stats, 200);
    }

    /**
     * Download an external image to the WP media library.
     * POST /oscar/v1/admin/fetch-image  body: {url}
     * Returns {attachment_id, url, filename, mime, size}.
     * Added 2026-07-30 — Phase 3 of Nhanh→Woo sync.
     */
    public static function fetch_image(WP_REST_Request $request): WP_REST_Response
    {
        $url = esc_url_raw((string) $request->get_param('url'));
        if (!$url) {
            return new WP_REST_Response(['message' => 'URL không hợp lệ.'], 422);
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $tmp = download_url($url, 60);
        if (is_wp_error($tmp)) {
            return new WP_REST_Response(['message' => 'Download failed: ' . $tmp->get_error_message()], 502);
        }
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $name = basename($path) ?: ('nhanh-' . md5($url) . '.jpg');
        $name = preg_replace('/[?#].*$/', '', $name);
        $name = sanitize_file_name($name);
        $file_array = ['name' => $name, 'tmp_name' => $tmp];
        $attachment_id = media_handle_sideload($file_array, 0);
        if (is_wp_error($attachment_id)) {
            @unlink($tmp);
            return new WP_REST_Response(['message' => 'Upload failed: ' . $attachment_id->get_error_message()], 500);
        }
        $url_out = wp_get_attachment_url($attachment_id);
        $mime = get_post_mime_type($attachment_id);
        $rel = get_attached_file($attachment_id);
        $size = $rel ? filesize($rel) : 0;
        return new WP_REST_Response([
            'attachment_id' => $attachment_id,
            'url' => $url_out,
            'filename' => basename($name),
            'mime' => $mime,
            'size' => $size,
        ], 201);
    }

    /**
     * Attach a fetched image to a product as featured + gallery.
     * POST /oscar/v1/admin/attach-product-images  body: {woo_id, image_id, gallery_ids[]}
     * Clears _nhanh_image_urls so SPA falls back to WP attachment.
     * Returns {success, woo_id, image_id, gallery_ids, cleared_nhanh_urls}.
     * Added 2026-07-30 — Phase 3 of Nhanh→Woo sync.
     */
    public static function attach_product_images(WP_REST_Request $request): WP_REST_Response
    {
        $woo_id = absint($request->get_param('woo_id'));
        $image_id = absint($request->get_param('image_id'));
        $gallery_ids = (array) ($request->get_param('gallery_ids') ?? []);
        $gallery_ids = array_values(array_filter(array_map('absint', $gallery_ids)));
        $product = $woo_id ? wc_get_product($woo_id) : null;
        if (!$product) {
            return new WP_REST_Response(['message' => 'Product not found'], 404);
        }
        if ($image_id) {
            set_post_thumbnail($woo_id, $image_id);
        }
        if (!empty($gallery_ids)) {
            update_post_meta($woo_id, '_product_image_gallery', implode(',', $gallery_ids));
        } else {
            delete_post_meta($woo_id, '_product_image_gallery');
        }
        delete_post_meta($woo_id, '_nhanh_image_urls');
        return new WP_REST_Response([
            'success' => true,
            'woo_id' => $woo_id,
            'image_id' => $image_id ?: null,
            'gallery_ids' => $gallery_ids,
            'cleared_nhanh_urls' => true,
        ], 200);
    }

    public static function cli_import(array $args, array $assoc_args): void
    {
        if (!class_exists('WooCommerce')) {
            WP_CLI::error('WooCommerce chưa được kích hoạt.');
        }
        $with_remote = isset($assoc_args['remote-images']);
        $result = self::import_catalog($with_remote);
        WP_CLI::success(sprintf('Đã đồng bộ %d sản phẩm; %d ảnh mới.', $result['products'], $result['images']));
    }

    public static function import_catalog(bool $with_remote_images = false): array
    {
        $data = self::read_catalog();
        $count = 0;
        $image_count = 0;
        foreach (($data['products'] ?? []) as $item) {
            $source_id = absint($item['id'] ?? 0);
            if (!$source_id || empty($item['name'])) {
                continue;
            }
            $existing = get_posts([
                'post_type' => 'product', 'post_status' => 'any', 'numberposts' => 1,
                'meta_key' => self::SOURCE_ID_KEY, 'meta_value' => $source_id, 'fields' => 'ids',
            ]);
            $product = $existing ? wc_get_product($existing[0]) : new WC_Product_Simple();
            if (!$product) {
                $product = new WC_Product_Simple();
            }
            $product->set_name(wp_strip_all_tags($item['name']));
            $product->set_slug(sanitize_title($item['name']) . '-p' . $source_id);
            $product->set_status('publish');
            $product->set_catalog_visibility('visible');
            $product->set_regular_price((string) ($item['oldPrice'] ?: $item['price']));
            $product->set_sale_price(!empty($item['oldPrice']) && $item['oldPrice'] > $item['price'] ? (string) $item['price'] : '');
            $product->set_manage_stock(true);
            $product->set_stock_quantity(max(0, (int) ($item['stock'] ?? 0)));
            $product->set_stock_status(($item['stock'] ?? 0) > 0 ? 'instock' : 'outofstock');
            $product->set_short_description(self::product_description($item));
            $product->set_description(self::product_description($item, true));
            $product->update_meta_data(self::SOURCE_ID_KEY, $source_id);
            foreach (['brand', 'cpu', 'gpu', 'ram', 'ssd', 'screen', 'demand', 'video'] as $key) {
                $product->update_meta_data('_oscar_' . $key, sanitize_text_field((string) ($item[$key] ?? '')));
            }
            $product->update_meta_data('_oscar_battery_wh', (string) ($item['batteryWh'] ?? ''));
            $product->update_meta_data('_oscar_battery_runtime', sanitize_text_field((string) ($item['batteryRuntime'] ?? '')));
            foreach (['condition', 'badge'] as $key) {
                foreach (['vi', 'en'] as $lang) {
                    $product->update_meta_data('_oscar_' . $key . '_' . $lang, sanitize_text_field((string) ($item[$key][$lang] ?? '')));
                }
            }
            $product_id = $product->save();
            self::assign_category($product_id, (string) ($item['category'] ?? 'laptop-cu'));
            self::assign_attributes($product_id, $item);
            $images = self::import_images($product_id, $item, $with_remote_images);
            if ($images) {
                $product = wc_get_product($product_id);
                $product->set_image_id(array_shift($images));
                $product->set_gallery_image_ids($images);
                $product->save();
                $image_count += count($images) + 1;
            }
            $count++;
        }
        return ['products' => $count, 'images' => $image_count];
    }

    private static function product_description(array $item, bool $long = false): string
    {
        $specs = array_filter([$item['cpu'] ?? '', $item['gpu'] ?? '', $item['ram'] ?? '', $item['ssd'] ?? '', $item['screen'] ?? '']);
        $intro = '<p>' . esc_html(($item['condition']['vi'] ?? 'Laptop cũ tuyển chọn') . ' tại Laptop OSCAR Thủ Đức.') . '</p>';
        if (!$long) {
            return $intro . '<p>' . esc_html(implode(' • ', $specs)) . '</p>';
        }
        return $intro . '<h2>Thông số kỹ thuật</h2><ul>' . implode('', array_map(static fn($value) => '<li>' . esc_html((string) $value) . '</li>', $specs)) . '</ul>';
    }

    private static function assign_category(int $product_id, string $slug): void
    {
        $term = term_exists($slug, 'product_cat');
        if (!$term) {
            $term = wp_insert_term(ucwords(str_replace('-', ' ', $slug)), 'product_cat', ['slug' => $slug]);
        }
        if (!is_wp_error($term)) {
            wp_set_object_terms($product_id, [(int) (is_array($term) ? $term['term_id'] : $term)], 'product_cat');
        }
    }

    private static function assign_attributes(int $product_id, array $item): void
    {
        foreach (['cpu', 'gpu', 'ram', 'ssd', 'screen', 'demand'] as $key) {
            $value = sanitize_text_field((string) ($item[$key] ?? ''));
            if (!$value) continue;
            $taxonomy = 'pa_' . $key;
            if (!term_exists($value, $taxonomy)) wp_insert_term($value, $taxonomy);
            wp_set_object_terms($product_id, [$value], $taxonomy, false);
        }
    }

    private static function import_images(int $product_id, array $item, bool $with_remote): array
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $sources = array_values(array_unique(array_filter(array_merge([$item['image'] ?? ''], (array) ($item['images'] ?? [])))));
        $attachment_ids = [];
        foreach ($sources as $index => $source) {
            if (str_starts_with($source, '/product-images/')) {
                $path = get_template_directory() . '/assets/images/products/' . basename($source);
                $id = self::attach_local_image($path, $product_id, $item['name'], $index);
            } elseif ($with_remote && wp_http_validate_url($source)) {
                $id = media_sideload_image($source, $product_id, $item['name'], 'id');
            } else {
                continue;
            }
            if (!is_wp_error($id) && $id) $attachment_ids[] = (int) $id;
        }
        return array_values(array_unique($attachment_ids));
    }

    private static function attach_local_image(string $path, int $product_id, string $title, int $index): int
    {
        if (!is_file($path)) return 0;
        $key = '_oscar_source_file';
        $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 1, 'meta_key' => $key, 'meta_value' => basename($path), 'fields' => 'ids']);
        if ($existing) return (int) $existing[0];
        $upload = wp_upload_bits(basename($path), null, file_get_contents($path));
        if (!empty($upload['error'])) return 0;
        $type = wp_check_filetype($upload['file']);
        $id = wp_insert_attachment(['post_mime_type' => $type['type'], 'post_title' => $title . ($index ? ' ' . ($index + 1) : ''), 'post_status' => 'inherit'], $upload['file'], $product_id);
        if (is_wp_error($id)) return 0;
        wp_update_attachment_metadata($id, wp_generate_attachment_metadata($id, $upload['file']));
        update_post_meta($id, $key, basename($path));
        return (int) $id;
    }

    private static function read_catalog(): array
    {
        if (!is_readable(self::DATA_FILE)) return [];
        $data = json_decode((string) file_get_contents(self::DATA_FILE), true);
        return is_array($data) ? $data : [];
    }

    public static function add_product_tab(array $tabs): array
    {
        $tabs['oscar'] = ['label' => 'Thông số OSCAR', 'target' => 'oscar_product_data', 'class' => []];
        return $tabs;
    }

    public static function render_product_tab(): void
    {
        echo '<div id="oscar_product_data" class="panel woocommerce_options_panel">';
        foreach (['brand' => 'Thương hiệu', 'cpu' => 'CPU', 'gpu' => 'GPU', 'ram' => 'RAM', 'ssd' => 'SSD', 'screen' => 'Màn hình', 'battery_wh' => 'Pin (Wh)', 'battery_runtime' => 'Thời lượng pin', 'demand' => 'Nhu cầu', 'video' => 'URL video'] as $key => $label) {
            woocommerce_wp_text_input(['id' => '_oscar_' . $key, 'label' => $label]);
        }
        echo '</div>';
    }

    public static function save_product_fields(int $post_id): void
    {
        foreach (['brand', 'cpu', 'gpu', 'ram', 'ssd', 'screen', 'battery_wh', 'battery_runtime', 'demand', 'video'] as $key) {
            if (isset($_POST['_oscar_' . $key])) update_post_meta($post_id, '_oscar_' . $key, sanitize_text_field(wp_unslash($_POST['_oscar_' . $key])));
        }
    }
}

Oscar_Shop_Core::boot();
