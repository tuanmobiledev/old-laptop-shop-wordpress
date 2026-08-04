<?php
/**
 * Plugin Name: OSCAR SEO
 * Description: Server-side Product JSON-LD, canonical link fix, dynamic og:image, product-title, and image sitemap endpoint.
 * Author: OSCAR Thủ Đức
 */

defined('ABSPATH') || exit;

/**
 * Resolve product from current request — handles both:
 *  - canonical /product/<slug>/  (is_product())
 *  - custom    /san-pham/<slug>-pN/  (oscar_product_id query var)
 */
function oscar_seo_resolve_product() {
    if (function_exists('is_product') && is_product()) {
        global $post;
        if ($post && $post->post_type === 'product') {
            return function_exists('wc_get_product') ? wc_get_product($post->ID) : null;
        }
    }
    $source_id = (int) get_query_var('oscar_product_id');
    if ($source_id > 0) {
        $posts = get_posts([
            'post_type'      => 'product',
            'meta_key'       => '_oscar_source_id',
            'meta_value'     => (string) $source_id,
            'posts_per_page' => 1,
            'fields'         => 'ids',
        ]);
        if ($posts && function_exists('wc_get_product')) {
            return wc_get_product($posts[0]);
        }
    }
    return null;
}

/**
 * Server-side Product JSON-LD — replaces client-side injected #product-ld.
 * Priority 6 → after frontend-config (priority 1) and theme meta (priority 5).
 */
add_action('wp_head', 'oscar_seo_product_jsonld', 6);
function oscar_seo_product_jsonld() {
    if (is_admin()) return;
    $product = oscar_seo_resolve_product();
    if (!$product || !is_object($product)) return;

    $name    = $product->get_name();
    $desc    = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());
    $sku     = $product->get_sku();
    $brand   = $product->get_meta('_oscar_brand') ?: ($product->get_attribute('pa_brand') ?: '');
    $price   = (float) $product->get_price();
    $regular = (float) $product->get_regular_price();
    $stock   = $product->get_stock_status();

    $image_ids = array_filter(array_merge(
        [$product->get_image_id()],
        (array) $product->get_gallery_image_ids()
    ));
    $image_urls = [];
    foreach ($image_ids as $id) {
        $u = wp_get_attachment_image_url($id, 'full');
        if ($u) $image_urls[] = $u;
    }
    if (!$image_urls) {
        $image_urls = [wc_placeholder_img_src()];
    }

    $canonical = get_permalink($product->get_id());

    $payload = [
        '@context'    => 'https://schema.org',
        '@type'       => 'Product',
        'name'        => $name,
        'description' => mb_substr($desc, 0, 5000),
        'sku'         => $sku ?: null,
        'image'       => $image_urls,
        'offers'      => [
            '@type'         => 'Offer',
            'url'           => $canonical,
            'priceCurrency' => 'VND',
            'price'         => $price,
            'availability'  => $stock === 'instock'
                ? 'https://schema.org/InStock'
                : 'https://schema.org/PreOrder',
            'itemCondition' => 'https://schema.org/UsedCondition',
        ],
    ];
    if ($brand) {
        $payload['brand'] = ['@type' => 'Brand', 'name' => $brand];
    }
    if ($regular > 0 && $regular > $price) {
        $payload['offers']['priceValidUntil'] = gmdate('Y-m-d', strtotime('+1 year'));
    }
    $payload = array_filter($payload, static fn($v) => $v !== null && $v !== []);
    $payload = apply_filters('oscar_seo_product_jsonld', $payload, $product);

    echo '<script type="application/ld+json" id="oscar-seo-product-ld">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

/**
 * Canonical link for /san-pham/<slug>-pN/ permalink — points to /product/<slug>/.
 */
add_action('wp_head', 'oscar_seo_inject_canonical', 1);
function oscar_seo_inject_canonical() {
    if (is_admin()) return;
    $source_id = (int) get_query_var('oscar_product_id');
    if ($source_id <= 0) return;
    $posts = get_posts([
        'post_type'      => 'product',
        'meta_key'       => '_oscar_source_id',
        'meta_value'     => (string) $source_id,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ]);
    if (!$posts) return;
    $real = get_permalink($posts[0]);
    if (!$real) return;
    remove_action('wp_head', 'rel_canonical');
    echo '<link rel="canonical" href="' . esc_url($real) . '" />' . "\n";
}

/**
 * Dynamic <title> for /san-pham/<slug>-pN/ permalink.
 */
add_filter('pre_get_document_title', 'oscar_seo_product_title', 20);
function oscar_seo_product_title($title) {
    if (is_admin()) return $title;
    $product = oscar_seo_resolve_product();
    if (!$product) return $title;
    $name = $product->get_name();
    return $name . ' – Laptop OSCAR Thủ Đức';
}

/**
 * Override og:image for product pages via output buffer.
 */
add_action('wp_head', 'oscar_seo_og_image_ob_start', 0);
function oscar_seo_og_image_ob_start() {
    if (is_admin()) return;
    ob_start();
}
add_action('wp_head', 'oscar_seo_og_image_ob_end', PHP_INT_MAX);
function oscar_seo_og_image_ob_end() {
    if (is_admin()) return;
    $html = ob_get_clean();
    $product = oscar_seo_resolve_product();
    if ($product) {
        $image_id = $product->get_image_id();
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'full') : null;
        if ($image_url) {
            $html = preg_replace('/<meta[^>]+property=["\']og:image["\'][^>]*>\s*\n?/i', '', $html);
            $html .= '<meta property="og:image" content="' . esc_url($image_url) . '" />' . "\n";
            $html .= '<meta property="og:image:alt" content="' . esc_attr($product->get_name()) . '" />' . "\n";
        }
    }
    echo $html;
}

/**
 * Remove client-side injected #product-ld (avoids duplicate Product schema).
 */
add_action('wp_footer', 'oscar_seo_remove_client_ld', 99);
function oscar_seo_remove_client_ld() {
    if (is_admin()) return;
    $product = oscar_seo_resolve_product();
    if (!$product) return;
    echo '<script>(function(){var r=function(){var e=document.getElementById("product-ld");if(e)e.remove();};if(document.readyState==="complete"||document.readyState==="interactive"){setTimeout(r,0);}else{document.addEventListener("DOMContentLoaded",r);}})();</script>' . "\n";
}

/**
 * Image sitemap — register /image-sitemap.xml rewrite endpoint.
 * Outputs Google image-sitemap format with proper xmlns:image namespace.
 */
add_action('init', 'oscar_seo_image_sitemap_rewrite');
function oscar_seo_image_sitemap_rewrite() {
    add_rewrite_rule('^image-sitemap\.xml$', 'index.php?oscar_image_sitemap=1', 'top');
}
add_filter('query_vars', 'oscar_seo_image_sitemap_query_var');
function oscar_seo_image_sitemap_query_var($vars) {
    $vars[] = 'oscar_image_sitemap';
    return $vars;
}
add_action('template_redirect', 'oscar_seo_image_sitemap_output', 0);
function oscar_seo_image_sitemap_output() {
    if ((int) get_query_var('oscar_image_sitemap') !== 1) return;

    header('Content-Type: application/xml; charset=UTF-8');
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

    $per_page = 1000;
    $page     = max(1, (int) ($_GET['page'] ?? 1));
    $offset   = ($page - 1) * $per_page;

    // Collect unique attachment IDs from _thumbnail_id + _product_image_gallery — only PUBLISHED products.
    global $wpdb;
    $values = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT pm.meta_value FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key IN ('_thumbnail_id', '_product_image_gallery')
               AND pm.meta_value <> ''
               AND p.post_type = 'product'
               AND p.post_status = 'publish'
             LIMIT %d OFFSET %d",
            $per_page, $offset
        )
    );

    $ids = [];
    foreach ($values as $v) {
        foreach (explode(',', (string) $v) as $piece) {
            $id = (int) trim($piece);
            if ($id > 0) $ids[$id] = true;
        }
    }
    $ids = array_keys($ids);

    foreach ($ids as $att_id) {
        $url = wp_get_attachment_url($att_id);
        if (!$url) continue;
        // Find parent product for title — only published
        $title = '';
        $parent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT pm.post_id FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
             WHERE pm.meta_key IN ('_thumbnail_id', '_product_image_gallery')
               AND FIND_IN_SET(%d, pm.meta_value) > 0
               AND p.post_type = 'product' AND p.post_status = 'publish'
             LIMIT 1",
            $att_id
        ));
        if ($parent) {
            $title = get_the_title($parent);
        }
        echo '  <url>' . "\n";
        echo '    <loc>' . esc_url($url) . '</loc>' . "\n";
        if ($title) {
            echo '    <image:image>' . "\n";
            echo '      <image:loc>' . esc_url($url) . '</image:loc>' . "\n";
            echo '      <image:title>' . esc_xml($title) . '</image:title>' . "\n";
            echo '    </image:image>' . "\n";
        }
        echo '  </url>' . "\n";
    }

    echo '</urlset>' . "\n";
    exit;
}
