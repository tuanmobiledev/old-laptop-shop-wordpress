<?php
/**
 * Plugin Name: Oscar Yoast SEO Fix
 * Description: Fill gaps in Yoast-generated titles/descriptions on maytinhthuduc.com.
 *              - Taxonomy archives: remove "Archives" suffix + descriptive meta description
 *              - /blog/ list page: proper title + description
 *              - PDP fallback: when Yoast returns generic brand, use REQUEST_URI slug
 *
 * Author: Oscar Dev
 * Version: 1.1.0
 */
defined('ABSPATH') || exit;

/**
 * Fix taxonomy archive title: remove "Archives" suffix.
 * e.g. "Laptop cũ Archives - Laptop OSCAR" → "Laptop cũ - Laptop OSCAR"
 */
add_filter('wpseo_title', static function ($title) {
    if (is_admin()) return $title;
    if (is_tax() || is_category() || is_tag()) {
        $title = preg_replace('/\s+Archives\s+/i', ' ', $title);
        $title = preg_replace('/^Page\s+\d+\s+of\s+\d+\s+/i', '', $title);
    }
    return $title;
}, 20);

/**
 * Fix taxonomy archive description: use term description if available,
 * else generate from term title + count.
 */
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
 * Fix /blog/ list page (WP page ID 922) — Yoast falls back to brand for both title+desc.
 */
add_filter('wpseo_title', static function ($title) {
    if (is_admin()) return $title;
    if (is_page() && (int) get_queried_object_id() === 922) {
        return 'Blog chia sẻ kiến thức laptop | Laptop OSCAR Thủ Đức';
    }
    return $title;
}, 21);

add_filter('wpseo_metadesc', static function ($desc) {
    if (is_admin()) return $desc;
    if (is_page() && (int) get_queried_object_id() === 922) {
        return 'Blog chia sẻ kiến thức, đánh giá laptop cũ, hướng dẫn kỹ thuật và mẹo hay tại Laptop OSCAR Thủ Đức.';
    }
    return $desc;
}, 21);

/**
 * Fix PDP fallback: when Yoast returns generic brand (no product context available),
 * use REQUEST_URI slug to build search-relevant description.
 */
add_filter('wpseo_metadesc', static function ($desc) {
    if (is_admin()) return $desc;
    $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
    if (!preg_match('#/san-pham/([a-z0-9-]+?)-p(\d+)/?$#i', $uri, $m)) return $desc;
    if (strlen($desc) >= 40 && stripos($desc, 'Laptop OSCAR Thủ Đức chuyên mua bán') === false) return $desc;
    $slug = $m[1];
    $name = ucwords(str_replace('-', ' ', $slug));
    return sprintf(
        '%s - thông số, giá bán, đánh giá chi tiết tại %s.',
        $name, get_bloginfo('name')
    );
}, 22);
