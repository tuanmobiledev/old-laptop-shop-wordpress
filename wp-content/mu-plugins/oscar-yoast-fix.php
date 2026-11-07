<?php
/**
 * Plugin Name: Oscar Yoast SEO Fix
 * Description: Fill gaps in Yoast-generated titles/descriptions on maytinhthuduc.com.
 *              - Taxonomy archives: remove "Archives" suffix + descriptive meta description
 *              - /blog/ list page: proper title + description
 *              - PDP fallback: when Yoast returns generic brand, use REQUEST_URI slug
 *              - XML sitemap: drop taxonomy archives that 302 to `/` (product_cat, category)
 *
 * Author: Oscar Dev
 * Version: 1.2.0
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
 * PDP fallback: KHÔNG sinh meta description từ slug nữa (2026-11-06).
 *
 * Bug: filter này trả desc kiểu "Hp Envy Laptop 17 Da0013dx Ultra 7 155h ..."
 * (ucwords trên slug) và nó được Yoast in TRƯỚC desc đúng của theme
 * (`oscar_shop_meta()` build từ short_description) ⇒ trang có 2 thẻ
 * <meta name="description">, Google lấy thẻ đầu = chuỗi slug sai hoa/thường.
 * Trả '' để Yoast không in gì, theme là nguồn duy nhất.
 */
add_filter('wpseo_metadesc', static function ($desc) {
    if (is_admin()) return $desc;
    $uri = isset($_SERVER['REQUEST_URI']) ? strtok($_SERVER['REQUEST_URI'], '?') : '';
    if (preg_match('#/san-pham/[a-z0-9-]+?-p(\d+)/?$#i', $uri)) {
        return '';
    }
    return $desc;
}, 22);

/**
 * XML sitemap: bỏ taxonomy archive không tồn tại (2026-11-07).
 *
 * Web chạy SPA nên /product-category/* và /blog/category/* đều 302 về `/`.
 * Yoast vẫn liệt kê 6 URL đó trong sitemap ⇒ GSC báo "Trang bị chuyển hướng",
 * sitemap mất tín nhiệm. Chặn tại gốc: is_valid_taxonomy() gate cả index link
 * lẫn entry, nên filter này xoá luôn 2 sub-sitemap.
 */
add_filter('wpseo_sitemap_exclude_taxonomy', static function ($exclude, $taxonomy) {
    return in_array($taxonomy, ['product_cat', 'category'], true) ? true : $exclude;
}, 10, 2);
