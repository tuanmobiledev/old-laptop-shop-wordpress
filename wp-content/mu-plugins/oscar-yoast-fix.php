<?php
/**
 * Plugin Name: Oscar Yoast SEO Fix
 * Description: Fill gaps in Yoast-generated titles/descriptions for taxonomy archives
 *              and product pages on maytinhthuduc.com.
 * Author: Oscar Dev
 * Version: 1.0.0
 *
 * Fixes:
 *  1) Taxonomy archive titles: remove "Archives" suffix (e.g. "Laptop cũ Archives ..." → "Laptop cũ ...")
 *  2) Taxonomy archive descriptions: generate from term title + count (default falls back to brand)
 *  3) PDP fallback: when no product excerpt (SPA shell render, OSCAR ID missing), generate
 *     generic description from REQUEST_URI slug (avoid showing homepage brand on PDP)
 */
defined('ABSPATH') || exit;

/**
 * Fix 1+2: Taxonomy archive title and description
 */
add_filter('wpseo_title', static function ($title) {
    if (is_admin()) return $title;
    if (is_tax() || is_category() || is_tag()) {
        $title = preg_replace('/\s+Archives\s+/i', ' ', $title);
        $title = preg_replace('/^Page\s+\d+\s+of\s+\d+\s+/i', '', $title);
    }
    return $title;
}, 20);

add_filter('wpseo_metadesc', static function ($desc) {
    if (is_admin()) return $desc;
    if (is_tax() || is_category() || is_tag()) {
        $term = get_queried_object();
        if (!$term || empty($term->name)) return $desc;
        $term_desc = trim(term_description($term));
        if (!empty($term_desc)) {
            $desc = wp_strip_all_tags(strip_shortcodes($term_desc));
            $desc = mb_substr($desc, 0, 160);
        } elseif (empty($desc) || strlen($desc) < 30) {
            $count = isset($term->count) ? (int) $term->count : 0;
            $desc = sprintf(
                '%s (%d sản phẩm) tại %s. Hàng chính hãng, bảo hành uy tín, tư vấn miễn phí.',
                $term->name, $count, get_bloginfo('name')
            );
        }
    }
    return $desc;
}, 20);

/**
 * Fix 3: PDP description fallback.
 *
 * When REQUEST_URI matches /san-pham/<slug>-pNNN/ but Yoast returned generic brand fallback,
 * this means the SPA shell is rendering without WC product context (e.g. invalid NNN, or
 * product not found). Use the slug to build a search-relevant description.
 */
add_filter('wpseo_metadesc', static function ($desc) {
    if (is_admin()) return $desc;
    $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
    if (!preg_match('#/san-pham/([a-z0-9-]+?)-p(\d+)/?$#i', $uri, $m)) return $desc;
    // Already has decent desc (≥40 chars)
    if (strlen($desc) >= 40 && stripos($desc, 'Laptop OSCAR Thủ Đức chuyên mua bán') === false) return $desc;
    $slug = $m[1];
    // Convert slug to readable name (hyphens → spaces, drop trailing noise)
    $name = ucwords(str_replace('-', ' ', $slug));
    $desc = sprintf(
        '%s - thông số, giá bán, đánh giá chi tiết tại %s.',
        $name, get_bloginfo('name')
    );
    return $desc;
}, 21);
