<?php
/**
 * Theme bootstrap for the original OSCAR React storefront.
 */

defined('ABSPATH') || exit;

function oscar_shop_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('woocommerce');
    add_theme_support('wc-product-gallery-zoom');
    add_theme_support('wc-product-gallery-lightbox');
    add_theme_support('wc-product-gallery-slider');
}
add_action('after_setup_theme', 'oscar_shop_setup');

function oscar_shop_asset_version(string $relative): string
{
    $file = get_template_directory() . '/' . ltrim($relative, '/');
    return is_file($file) ? (string) filemtime($file) : wp_get_theme()->get('Version');
}

function oscar_shop_enqueue_assets(): void
{
    if (is_admin()) {
        return;
    }

    wp_enqueue_style(
        'oscar-storefront',
        get_template_directory_uri() . '/assets/index-BZOXiVYO.css',
        [],
        oscar_shop_asset_version('assets/index-BZOXiVYO.css')
    );
    // Boss 2026-08-04 Bug #6/#7 root cause: NO `?ver=` here.
    // Vite puts content hash in filename (index-ClrsitLE.js), so URL is already
    // cache-busted. Adding a query string via wp_enqueue_script makes the browser
    // see TWO different URLs for the same module (one with `?ver=`, one without
    // from lazy chunks' relative imports). Browsers treat them as separate
    // module instances → React 19 loads twice → 2 `__reactContainer$*` keys →
    // 2 DOM roots → error boundary duplicates main content + DOM mismatch.
    // Boss 2026-08-04 woff2/fonts fix: vite.config.js `base: /wp-content/themes/oscar-shop/`
    // makes @font-face and module preload URLs absolute under theme folder.
    wp_enqueue_script(
        'oscar-storefront',
        get_template_directory_uri() . '/assets/index-ClrsitLE.js',
        [],
        null,
        true
    );
}
add_action('wp_enqueue_scripts', 'oscar_shop_enqueue_assets');
add_filter('script_loader_tag', static function (string $tag, string $handle): string {
    return $handle === 'oscar-storefront'
        ? str_replace('<script ', '<script type="module" crossorigin ', $tag)
        : $tag;
}, 10, 2);

function oscar_shop_frontend_config(): void
{
    if (is_admin()) {
        return;
    }
    $config = [
        'homeUrl' => home_url('/'),
        'restUrl' => esc_url_raw(rest_url('oscar/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'themeUrl' => get_template_directory_uri(),
        'wooEnabled' => class_exists('WooCommerce'),
    ];
    echo '<script>window.OSCAR_WP=' . wp_json_encode($config) . ';</script>' . "\n";
}
add_action('wp_head', 'oscar_shop_frontend_config', 1);

function oscar_shop_resource_hints(array $urls, string $relation_type): array
{
    if ($relation_type === 'preconnect') {
        $urls[] = ['href' => 'https://maps.google.com', 'crossorigin' => 'anonymous'];
    }
    return $urls;
}
add_filter('wp_resource_hints', 'oscar_shop_resource_hints', 10, 2);

function oscar_shop_document_title(string $title): string
{
    return is_front_page() ? 'Laptop OSCAR Thủ Đức - Laptop cũ, phụ kiện và sửa chữa' : $title;
}
add_filter('pre_get_document_title', 'oscar_shop_document_title');

function oscar_shop_rewrites(): void
{
    add_rewrite_rule('^san-pham/.+-p([0-9]+)/?$', 'index.php?oscar_product_id=$matches[1]', 'top');
    add_rewrite_rule('^(warranty|bao-hanh|chinh-sach-bao-hanh)/?$', 'index.php?oscar_app_route=warranty', 'top');
    add_rewrite_rule('^(returns|doi-tra)/?$', 'index.php?oscar_app_route=returns', 'top');
    add_rewrite_rule('^(delivery|giao-hang)/?$', 'index.php?oscar_app_route=delivery', 'top');
    add_rewrite_rule('^(policy|chinh-sach)/?$', 'index.php?oscar_app_route=policy', 'top');
}
add_action('init', 'oscar_shop_rewrites');
add_action('after_switch_theme', static function (): void {
    oscar_shop_rewrites();
    flush_rewrite_rules();
});
add_filter('query_vars', static function (array $vars): array {
    $vars[] = 'oscar_product_id';
    $vars[] = 'oscar_app_route';
    return $vars;
});
add_filter('template_include', static function (string $template): string {
    return (get_query_var('oscar_product_id') || get_query_var('oscar_app_route')) ? get_template_directory() . '/index.php' : $template;
});
add_filter('pre_handle_404', static function ($preempt, WP_Query $query) {
    return get_query_var('oscar_app_route') ? true : $preempt;
}, 10, 2);
add_action('template_redirect', static function (): void {
    if (get_query_var('oscar_app_route') && is_404()) {
        global $wp_query;
        $wp_query->is_404 = false;
        status_header(200);
    }
}, 0);

function oscar_shop_redirect_canonical($redirect_url, $requested_url)
{
    return (get_query_var('oscar_product_id') || get_query_var('oscar_app_route')) ? false : $redirect_url;
}
add_filter('redirect_canonical', 'oscar_shop_redirect_canonical', 10, 2);

/**
 * Bridge: WP rewrite -> SPA hash router.
 *
 * The SPA bundle routes via `window.location.hash` (e.g. `#warranty`, `#returns`,
 * `#delivery`, `#policy`). When WP rewrite rules expose clean URLs like `/warranty`
 * or `/chinh-sach-bao-hanh`, they set the `oscar_app_route` query var, but the SPA
 * does not know about that var — it only watches the URL hash.
 *
 * Implementation lives in inc/route-bridge.php. We require it here so the bridge
 * is loaded with the theme.
 */
require_once get_template_directory() . '/inc/route-bridge.php';

function oscar_shop_meta(): void
{
    if (is_admin()) {
        return;
    }
    $description = 'Laptop OSCAR Thủ Đức chuyên mua bán, sửa chữa và nâng cấp laptop/PC. Tư vấn cấu hình, kiểm tra máy rõ ràng, nâng cấp RAM/SSD.';
    $cover = get_template_directory_uri() . '/assets/images/oscar-cover.webp';
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta name="theme-color" content="#f15a24">' . "\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:locale" content="vi_VN">' . "\n";
    echo '<meta property="og:site_name" content="Laptop OSCAR Thủ Đức">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($cover) . '">' . "\n";
    echo '<link rel="icon" type="image/webp" href="' . esc_url(get_template_directory_uri() . '/assets/images/oscar-avatar.webp') . '">' . "\n";
}
add_action('wp_head', 'oscar_shop_meta', 5);
