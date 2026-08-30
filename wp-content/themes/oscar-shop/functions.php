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

/**
 * Boss 2026-08-26: Blog unified at /#blog (SPA hash route).
 * /blog/ is a legacy WordPress page kept for backward-compat with external
 * links and SEO; users land on the unified blog surface via 301 redirect.
 */
function oscar_shop_redirect_blog_to_spa(): void
{
    if (is_page('blog')) {
        wp_safe_redirect(home_url('/#blog'), 301);
        exit;
    }
}
add_action('template_redirect', 'oscar_shop_redirect_blog_to_spa');

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
        get_template_directory_uri() . '/assets/index-CtI4Mc6z.css',
        [],
        oscar_shop_asset_version('assets/index-CtI4Mc6z.css')
    );
    // Boss 2026-08-04 Bug #6/#7 root cause: NO `?ver=` here.
    // Vite puts content hash in filename (index-D7hgec7J.js), so URL is already
    // cache-busted. Adding a query string via wp_enqueue_script makes the browser
    // see TWO different URLs for the same module (one with `?ver=`, one without
    // from lazy chunks' relative imports). Browsers treat them as separate
    // module instances → React 19 loads twice → 2 `__reactContainer$*` keys →
    // 2 DOM roots → error boundary duplicates main content + DOM mismatch.
    // Boss 2026-08-04 woff2/fonts fix: vite.config.js `base: /wp-content/themes/oscar-shop/`
    // makes @font-face and module preload URLs absolute under theme folder.
wp_enqueue_script(
        'oscar-storefront',
        get_template_directory_uri() . '/assets/index-D7hgec7J.js',
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
    // Boss 2026-08-25: whitelist chỉ /san-pham/<slug>-p<id>/. Mọi URL khác (warranty,
    // returns, delivery, policy, cart, checkout, my-account, /shop/, /blog/, …) được
    // xử lý bởi mu-plugin oscar-whitelist-redirect.php → silent redirect về homepage.
    // Hash routes (#warranty, #returns, ...) được SPA bundle tự render; server-side
    // không cần rewrite rule vì browser strip hash trước khi request.
    add_rewrite_rule('^san-pham/.+-p([0-9]+)/?$', 'index.php?oscar_product_id=$matches[1]', 'top');
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
add_action('pre_get_posts', static function (WP_Query $query): void {
    if (!$query->is_main_query()) {
        return;
    }
    // Boss 2026-08-24: when oscar_app_route is set, WP's main query defaults to fetching
    // latest posts (is_home=true) which sets is_home/is_front flags and renders wrong
    // title/content. Clear those flags so theme's index.php renders the SPA shell only.
    if (get_query_var('oscar_app_route')) {
        $query->is_home = false;
        $query->is_front = false;
        $query->is_page = false;
        $query->is_singular = false;
        $query->is_archive = false;
    }
});
add_filter('document_title_parts', static function (array $parts): array {
    // Boss 2026-08-24: suppress "Home" title default when SPA is in charge
    if (get_query_var('oscar_app_route')) {
        return ['Oscar Shop'];
    }
    return $parts;
}, 10, 1);
add_filter('template_include', static function (string $template): string {
    if (get_query_var('oscar_product_id') || get_query_var('oscar_app_route')) {
        return get_template_directory() . '/index.php';
    }
    // Force SPA mount on WC cart/checkout/my-account pages so React handles them
    // (page-{slug}.php / page.php / WC block template would render coming-soon or placeholder)
    if (function_exists('is_cart') && (function_exists('is_page') && (is_page('cart') || is_page('checkout') || is_page('my-account')))) {
        return get_template_directory() . '/index.php';
    }
    return $template;
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

    $cover       = get_template_directory_uri() . '/assets/images/oscar-cover.webp';
    $description = '';
    $og_type     = 'website';
    $page_image  = $cover;
    $page_title  = '';
    $page_url    = '';
    $product     = null;

    if (is_front_page() || is_home()) {
        $description = get_bloginfo('description', 'display');
        if (!$description) {
            $description = 'Laptop OSCAR Thủ Đức chuyên mua bán, sửa chữa và nâng cấp laptop/PC';
        }
        $page_title = wp_get_document_title();
        $page_url   = home_url('/');
    } elseif (
        (function_exists('is_product') && is_product())
        || (int) get_query_var('oscar_product_id') > 0
    ) {
        if (function_exists('oscar_seo_resolve_product')) {
            $product = oscar_seo_resolve_product();
        } elseif (function_exists('is_product') && is_product()) {
            global $post;
            if ($post && $post->post_type === 'product' && function_exists('wc_get_product')) {
                $product = wc_get_product($post->ID);
            }
        }
        if ($product) {
            $desc_raw    = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
            $description = trim(preg_replace('/\s+/', ' ', $desc_raw));
            $page_title  = $product->get_name();
            $page_url    = get_permalink($product->get_id());
            $image_id    = $product->get_image_id();
            $image_url   = $image_id ? wp_get_attachment_image_url($image_id, 'full') : null;
            if ($image_url) {
                $page_image = $image_url;
            }
            $og_type = 'product';
        }
    } elseif (is_singular('post')) {
        $post = get_post();
        if ($post) {
            $excerpt    = $post->post_excerpt ?: $post->post_content;
            $description = trim(wp_strip_all_tags($excerpt));
            $page_title  = get_the_title($post);
            $page_url    = get_permalink($post);
            $thumb_id    = get_post_thumbnail_id($post);
            $thumb_url   = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'full') : null;
            if ($thumb_url) {
                $page_image = $thumb_url;
            }
            $og_type = 'article'; // Boss 2026-08-25: blog posts must be og:type=article for proper social card.
        }
    } elseif (is_page()) {
        $post = get_post();
        if ($post) {
            $description = trim(wp_strip_all_tags($post->post_content));
            $page_title  = get_the_title($post);
            $page_url    = get_permalink($post);
        }
    }

    if (!$description) {
        $description = get_bloginfo('description', 'display') ?: 'Laptop OSCAR Thủ Đức';
    }
    $description = mb_substr(trim(preg_replace('/\s+/', ' ', $description)), 0, 160);

    if (!$page_title) {
        $page_title = wp_get_document_title();
    }
    if (!$page_url) {
        $page_url = home_url(add_query_arg(null, null));
    }
    $page_url = wp_normalize_path($page_url);

    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta name="theme-color" content="#f15a24">' . "\n";
    echo '<meta property="og:type" content="' . esc_attr($og_type) . '">' . "\n";
    echo '<meta property="og:locale" content="vi_VN">' . "\n";
    echo '<meta property="og:site_name" content="Laptop OSCAR Thủ Đức">' . "\n";
    echo '<meta property="og:image" content="' . esc_url($page_image) . '">' . "\n";
    if ($page_title) {
        echo '<meta property="og:image:alt" content="' . esc_attr($page_title) . '">' . "\n";
    }
    if ($page_title) {
        echo '<meta property="og:title" content="' . esc_attr($page_title) . '">' . "\n";
    }
    if ($page_url) {
        echo '<meta property="og:url" content="' . esc_url($page_url) . '">' . "\n";
    }
    if ($og_type === 'product' && $product) {
        $price = (float) $product->get_price();
        if ($price > 0) {
            $int_price = (string) (int) round($price);
            echo '<meta property="product:price:amount" content="' . esc_attr($int_price) . '">' . "\n";
            echo '<meta property="product:price:currency" content="VND">' . "\n";
        }
        $stock = $product->get_stock_status();
        if ($stock === 'instock') {
            echo '<meta property="product:availability" content="in stock">' . "\n";
        } elseif ($stock === 'outofstock') {
            echo '<meta property="product:availability" content="out of stock">' . "\n";
        }
    }
    if (is_front_page() || is_home()) {
        echo '<link rel="canonical" href="' . esc_url(home_url('/')) . '">' . "\n";
    }
    echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
    if ($page_title) {
        echo '<meta name="twitter:title" content="' . esc_attr($page_title) . '">' . "\n";
    }
    echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
    echo '<meta name="twitter:image" content="' . esc_url($page_image) . '">' . "\n";
    echo '<link rel="icon" type="image/webp" href="' . esc_url(get_template_directory_uri() . '/assets/images/oscar-avatar.webp') . '">' . "\n";
}
add_action('wp_head', 'oscar_shop_meta', 5);

/**
 * Boss 2026-08-25: Blog phase — emit JSON-LD BlogPosting schema for single
 * posts. Complement to existing microdata (itemprop) in single.php. Helps
 * Google rich-result eligibility (Article card, headline image in SERP).
 */
function oscar_shop_blog_posting_schema(): void
{
    if (is_admin() || !is_singular('post')) {
        return;
    }
    $post = get_post();
    if (!$post) {
        return;
    }
    $thumb_id  = get_post_thumbnail_id($post);
    $image_url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'full') : null;
    $excerpt   = $post->post_excerpt ?: wp_strip_all_tags($post->post_content);
    $excerpt   = trim(preg_replace('/\s+/', ' ', $excerpt));
    $excerpt   = mb_substr($excerpt, 0, 200);

    $schema = [
        '@context'         => 'https://schema.org',
        '@type'            => 'BlogPosting',
        'headline'         => get_the_title($post),
        'description'      => $excerpt,
        'url'              => get_permalink($post),
        'datePublished'    => get_the_date('c', $post),
        'dateModified'     => get_the_modified_date('c', $post),
        'mainEntityOfPage' => get_permalink($post),
        'author'           => [
            '@type' => 'Organization',
            'name'  => 'Laptop OSCAR Thủ Đức',
            'url'   => home_url('/'),
        ],
        'publisher'        => [
            '@type' => 'Organization',
            'name'  => 'Laptop OSCAR Thủ Đức',
            'logo'  => [
                '@type' => 'ImageObject',
                'url'   => get_template_directory_uri() . '/assets/images/oscar-avatar.webp',
            ],
        ],
    ];
    if ($image_url) {
        $schema['image'] = $image_url;
    }

    echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
}
add_action('wp_head', 'oscar_shop_blog_posting_schema', 6);

/**
 * Boss 2026-08-25: Blog phase P1 — extract H2/H3 table-of-contents from post content.
 * WP auto-adds slugified IDs lúc render via wp_filter_content_tags / heading_anchors.
 * Trong raw post_content H2/H3 chưa có id → dùng sanitize_title() (Vietnamese-aware)
 * để tính đúng slug mà WP sẽ gắn vào heading khi render.
 * Returns array of [level, id, text]; empty array if no headings.
 */
function oscar_blog_extract_toc(string $content): array
{
    $items = [];
    // Match H2/H3 không có id (raw post_content); cho phép nội dung xuống dòng.
    if (preg_match_all('/<(h[23])[^>]*>(.+?)<\/\1>/is', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $level = (int) substr($m[1], 1); // 2 hoặc 3
            $text  = trim(wp_strip_all_tags($m[2]));
            $id    = sanitize_title($text);
            if ($level >= 2 && $level <= 3 && $id && $text) {
                $items[] = compact('level', 'id', 'text');
            }
        }
    }
    return $items;
}

/**
 * Boss 2026-08-25: Blog phase P1 — return share URLs for FB + Zalo + copy link.
 */
function oscar_blog_share_links(): array
{
    $url   = get_permalink();
    $title = get_the_title();
    return [
        'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($url),
        'zalo'     => 'https://sp.zalo.me/shareInline?url=' . rawurlencode($url),
        'copy_url' => $url,
        'title'    => $title,
    ];
}
