<?php
/**
 * Plugin Name: OSCAR Whitelist Redirect
 * Description: Chỉ cho phép các URL trong whitelist; mọi URL khác redirect về trang chủ.
 *              Đặc biệt: /product/<wp-slug>/ → 301 /san-pham/<wp_slug>-p<oscar_source_id>/
 * Version:     1.2.0
 * Author:      Hermes
 * Boss decision: 2026-08-25 — whitelist các page: /, /san-pham/<slug>-p<id>/ (ID hợp lệ),
 *                /blog/<post-slug>/ (post tồn tại), hash routes. /product/<slug>/ → /san-pham/<slug>-p<id>/ (301).
 *                Tất cả URL khác → silent redirect về homepage.
 *
 * v1.2.0 (2026-08-25): dùng is_singular() thay get_posts() cho /blog/ và /product/.
 *                      Tránh WP render 404 template cho /blog/<invalid>/ — redirect về /.
 */

defined('ABSPATH') || exit;

add_action('template_redirect', 'oscar_whitelist_redirect', 1);

function oscar_whitelist_redirect(): void
{
    // Bypass: admin, AJAX, REST, cron
    if (is_admin()) {
        return;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    $path = oscar_get_request_path();

    // /product/<wp-slug>/ → /san-pham/<slug>-p<oscar_source_id>/
    // WP đã resolve query: is_singular('product') true nếu product tồn tại.
    if (preg_match('#^/product/[^/]+/?$#', $path)) {
        if (is_singular('product')) {
            oscar_redirect_product_to_canonical();
            return;
        }
        // URL có /product/ nhưng không tìm thấy product → silent redirect
        oscar_redirect_home();
        return;
    }

    // /san-pham/<slug>-p<id>/ — validate ID tồn tại trong DB (Oscar IDs không phải WP post IDs)
    if (preg_match('#^/san-pham/([^/]+?)-p([0-9]+)/?$#', $path, $m)) {
        if (oscar_is_valid_oscar_id((int) $m[2])) {
            return; // Valid, để WP render SPA product view
        }
        oscar_redirect_home();
        return;
    }

    // /blog/<post-slug>/ — WP resolve xong: is_singular('post') true nếu post tồn tại
    if (preg_match('#^/blog/[^/]+/?$#', $path)) {
        if (is_singular('post')) {
            return; // Valid post
        }
        // URL có /blog/<slug>/ nhưng WP không tìm thấy post → silent redirect
        oscar_redirect_home();
        return;
    }

    // Whitelist khác (system paths, /, /wp-*...)
    if (oscar_is_path_whitelisted($path)) {
        return;
    }

    // Default: silent redirect về homepage
    oscar_redirect_home();
}

function oscar_get_request_path(): string
{
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path        = strtok($request_uri, '?');
    $path        = rtrim($path, '/');
    return $path === '' ? '/' : $path;
}

function oscar_is_path_whitelisted(string $path): bool
{
    // System paths — luôn cho phép
    if (preg_match(
        '#^/(wp-(content|includes|admin|login\.php|json|cron\.php|signup\.php)|feed|sitemap|robots\.txt|favicon\.ico)#i',
        $path
    )) {
        return true;
    }

    // Application whitelist
    $patterns = [
        '#^/$#',                          // home
    ];

    foreach ($patterns as $p) {
        if (preg_match($p, $path)) {
            return true;
        }
    }

    return false;
}

function oscar_redirect_home(): void
{
    nocache_headers();
    wp_safe_redirect(home_url('/'), 302);
    exit;
}

function oscar_redirect_product_to_canonical(): void
{
    $post_id  = (int) get_queried_object_id();
    $oscar_id = get_post_meta($post_id, '_oscar_source_id', true);

    if ($oscar_id) {
        $post_slug = (string) get_post_field('post_name', $post_id);
        $canonical = home_url('/san-pham/' . $post_slug . '-p' . $oscar_id . '/');
        nocache_headers();
        wp_safe_redirect($canonical, 301);
        exit;
    }

    // Product tồn tại nhưng thiếu Oscar source_id → không redirect canonical được
    oscar_redirect_home();
}

function oscar_is_valid_oscar_id(int $oscar_id): bool
{
    if ($oscar_id <= 0) {
        return false;
    }

    $cache_key = 'oscar_valid_id_' . $oscar_id;
    $cached    = get_transient($cache_key);

    if ($cached === 'YES') {
        return true;
    }
    if ($cached === 'NO') {
        return false;
    }

    $posts = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => '_oscar_source_id',
                'value'   => (string) $oscar_id,
                'compare' => '=',
            ],
        ],
    ]);

    $valid = !empty($posts);
    set_transient($cache_key, $valid ? 'YES' : 'NO', DAY_IN_SECONDS);

    return $valid;
}
