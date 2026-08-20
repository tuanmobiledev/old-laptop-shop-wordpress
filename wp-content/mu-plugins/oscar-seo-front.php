<?php
/**
 * Plugin Name: OSCAR SEO Front
 * Description: Front-end SEO fixes that complement oscar-seo.php:
 *              - <html lang="vi-VN"> site-wide
 *              - Organization + WebSite + LocalBusiness JSON-LD schema on the homepage
 *              - BreadcrumbList JSON-LD schema on inner pages
 *
 * Update 2026-08-20: Added LocalBusiness schema (Boss confirmed address 33a Đường số 17,
 *                   Thủ Đức, HCM, 71319; phone 0984.496.260; 24/7; geo from Maps URL).
 *                   Added BreadcrumbList schema for product / post / page / taxonomy.
 *
 * Update 2026-08-20 (R3): Added CollectionPage + ItemList JSON-LD schema for
 *                   product_cat taxonomy archive pages. Helps Google show a
 *                   product list carousel under the SERP title for category
 *                   pages (e.g. "/product-category/laptop-cu/").
 *
 * Author: OSCAR Thủ Đức
 */
defined('ABSPATH') || exit;

/**
 * Force <html lang="vi-VN"> site-wide. Vietnamese is the source language
 * of every page on this site — content, OG locale, schema inLanguage all
 * already use vi_VN; the only thing not aligned was the <html> tag.
 */
add_filter('language_attributes', 'oscar_seo_front_lang');
function oscar_seo_front_lang($output)
{
    return 'lang="vi-VN"';
}

/**
 * LocalBusiness data — single source of truth. Edit here when shop info
 * changes (address, phone, hours, geo). The schema function below reads
 * from this array.
 *
 * 2026-08-20: Boss provided exact address "33a Đường số 17, Thủ Đức,
 *             Hồ Chí Minh 71319, Việt Nam" and "Mở cửa 24/7". Phone
 *             0984.496.260 extracted from SPA bundle hotline field.
 *             Geo from Google Maps URL !3d10.8555602!4d106.7610724.
 */
function oscar_seo_front_localbusiness_data()
{
    return [
        '@type'    => 'LocalBusiness',
        '@id'      => 'https://maytinhthuduc.com/#localbusiness',
        'name'     => 'Laptop OSCAR Thủ Đức',
        'image'    => 'https://maytinhthuduc.com/wp-content/themes/oscar-shop/assets/images/oscar-cover.webp',
        'url'      => 'https://maytinhthuduc.com/',
        'telephone' => '+84984496260',
        'priceRange' => 'VNĐ',
        'address'  => [
            '@type'           => 'PostalAddress',
            'streetAddress'   => '33a Đường số 17',
            'addressLocality' => 'Thủ Đức',
            'addressRegion'   => 'Hồ Chí Minh',
            'postalCode'      => '71319',
            'addressCountry'  => 'VN',
        ],
        'geo'      => [
            '@type'     => 'GeoCoordinates',
            'latitude'  => 10.8555602,
            'longitude' => 106.7610724,
        ],
        'openingHoursSpecification' => [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],
            'opens'     => '00:00',
            'closes'    => '23:59',
        ],
        'areaServed' => [
            '@type' => 'City',
            'name'  => 'Thủ Đức, Hồ Chí Minh',
        ],
    ];
}

/**
 * Organization + WebSite + LocalBusiness JSON-LD on the homepage only.
 * Priority 12 — runs after oscar_seo_product_jsonld (priority 6) and
 * after core WP head (priority 8–11).
 */
add_action('wp_head', 'oscar_seo_front_org_schema', 12);
function oscar_seo_front_org_schema()
{
    if (is_admin()) return;
    if (!is_front_page() && !is_home()) return;

    $home = home_url('/');
    $name = 'Laptop OSCAR Thủ Đức';
    $logo = $home . 'wp-content/themes/oscar-shop/assets/images/oscar-cover.webp';
    $desc = get_bloginfo('description', 'display') ?: 'Laptop OSCAR Thủ Đức — laptop cũ, phụ kiện và sửa chữa';

    $graph = [
        [
            '@type'       => 'Organization',
            '@id'         => $home . '#organization',
            'name'        => $name,
            'url'         => $home,
            'logo'        => $logo,
            'description' => $desc,
        ],
        [
            '@type'      => 'WebSite',
            '@id'        => $home . '#website',
            'url'        => $home,
            'name'       => $name,
            'inLanguage' => 'vi-VN',
        ],
    ];

    // LocalBusiness — also emit on front page only. Backwards-compat: keep the
    // filter so themes/plugins can override fields without editing this file.
    $local = apply_filters('oscar_seo_front_localbusiness', oscar_seo_front_localbusiness_data());
    if (is_array($local) && !empty($local['name'])) {
        // Merge: filter wins over defaults for top-level keys, but always
        // ensure @context is present.
        $local['@context'] = 'https://schema.org';
        if (empty($local['@type'])) {
            $local['@type'] = 'LocalBusiness';
        }
        if (empty($local['@id'])) {
            $local['@id'] = $home . '#localbusiness';
        }
        $graph[] = $local;
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ];

    echo '<script type="application/ld+json" id="oscar-seo-front-ld">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

/**
 * BreadcrumbList JSON-LD on inner pages (product / post / page / taxonomy).
 * Priority 13 — runs after org_schema (priority 12) so the two scripts sit
 * together at the end of <head>.
 *
 * Skips: front page, search, 404, admin. Emits only when at least 2 items
 * exist (Home + current).
 */
add_action('wp_head', 'oscar_seo_front_breadcrumb', 13);
function oscar_seo_front_breadcrumb()
{
    if (is_admin()) return;
    if (is_front_page() || is_home() || is_search() || is_404()) return;

    $items   = [];
    $pos     = 1;
    $home    = home_url('/');
    $home_id = null;

    // Always start with Home
    $home_item = [
        '@type'    => 'ListItem',
        'position' => $pos++,
        'name'     => 'Trang chủ',
        'item'     => $home,
    ];
    $items[] = $home_item;

    if (is_product()) {
        $post_id = get_the_ID();

        // Walk deepest product_cat → root (top-down)
        $terms = get_the_terms($post_id, 'product_cat');
        if (!empty($terms) && !is_wp_error($terms)) {
            $deepest_depth = -1;
            $deepest_term  = null;
            foreach ($terms as $t) {
                $depth = count(get_ancestors($t->term_id, 'product_cat', 'product_cat'));
                if ($depth > $deepest_depth) {
                    $deepest_depth = $depth;
                    $deepest_term  = $t;
                }
            }
            if ($deepest_term) {
                $ancestor_ids = get_ancestors($deepest_term->term_id, 'product_cat', 'product_cat');
                $chain        = array_reverse($ancestor_ids);
                $chain[]      = $deepest_term->term_id;
                foreach ($chain as $tid) {
                    $t = get_term($tid, 'product_cat');
                    if ($t && !is_wp_error($t)) {
                        $items[] = [
                            '@type'    => 'ListItem',
                            'position' => $pos++,
                            'name'     => $t->name,
                            'item'     => get_term_link($t),
                        ];
                    }
                }
            }
        }

        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_the_title($post_id),
            'item'     => get_permalink($post_id),
        ];
    } elseif (is_single()) {
        // Blog post → Home > [Category if any] > Title
        $cats = get_the_category();
        if (!empty($cats) && !is_wp_error($cats)) {
            // Use the lowest-id category as the breadcrumb crumb (parent walks
            // not needed for blogs since hierarchy is typically flat)
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $cats[0]->name,
                'item'     => get_term_link($cats[0]),
            ];
        } else {
            // No category — use the "Tin tức" / blog archive page if defined
            $blog_page_id = (int) get_option('page_for_posts');
            if ($blog_page_id) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => get_the_title($blog_page_id),
                    'item'     => get_permalink($blog_page_id),
                ];
            }
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_the_title(),
            'item'     => get_permalink(),
        ];
    } elseif (is_page()) {
        // WordPress page → walk parent chain top-down
        $pid      = get_the_ID();
        $ancestors = get_post_ancestors($pid);
        if (!empty($ancestors)) {
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $aid) {
                $items[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => get_the_title($aid),
                    'item'     => get_permalink($aid),
                ];
            }
        }
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_the_title($pid),
            'item'     => get_permalink($pid),
        ];
    } elseif (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();
        if ($term && isset($term->term_id)) {
            // For taxonomies with hierarchy (product_cat, category), walk ancestors
            if (is_taxonomy_hierarchical($term->taxonomy)) {
                $ancestors = get_ancestors($term->term_id, $term->taxonomy, 'taxonomy');
                $ancestors = array_reverse($ancestors);
                foreach ($ancestors as $tid) {
                    $t = get_term($tid, $term->taxonomy);
                    if ($t && !is_wp_error($t)) {
                        $items[] = [
                            '@type'    => 'ListItem',
                            'position' => $pos++,
                            'name'     => $t->name,
                            'item'     => get_term_link($t),
                        ];
                    }
                }
            }
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $pos++,
                'name'     => $term->name,
                'item'     => get_term_link($term),
            ];
        }
    } else {
        return; // Unknown page type — don't emit broken breadcrumb
    }

    if (count($items) < 2) return;

    $payload = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $items,
    ];

    echo '<script type="application/ld+json" id="oscar-seo-front-breadcrumb">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

/**
 * CollectionPage + ItemList JSON-LD for product_cat taxonomy archive pages.
 * Priority 14 — runs after breadcrumb (priority 13) so the two scripts sit
 * together at the end of <head>.
 *
 * Skips: front page, pagination, other taxonomies, post tags.
 * Emits only when the term has at least 1 published product.
 *
 * R3 (2026-08-20): Google can show the first 10 product items as a list under
 * the SERP title for category pages. We populate 20 (Google caps at ~10 but
 * more is fine). numberOfItems reflects the true count (capped by WP_Query
 * if -1) so the schema is honest about total inventory.
 */
add_action('wp_head', 'oscar_seo_front_collection_schema', 14);
function oscar_seo_front_collection_schema()
{
    if (is_admin()) return;
    if (is_paged()) return;                        // don't duplicate on /?page=2
    if (!is_tax('product_cat')) return;

    $term = get_queried_object();
    if (!$term || !isset($term->term_id)) return;

    // Honour Yoast/RankMath titles if present, else term name
    $title = function_exists('wpseo_get_term_title') && wpseo_get_term_title($term, false, '', false)
        ? wpseo_get_term_title($term)
        : $term->name;
    $description = term_description($term) ?: wp_strip_all_tags($term->name . ' — ' . get_bloginfo('name'));
    $url = get_term_link($term);
    if (is_wp_error($url)) return;

    // Count all published products in this term (for numberOfItems)
    $total = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare(
        "SELECT COUNT(DISTINCT p.ID) FROM {$GLOBALS['wpdb']->posts} p
         JOIN {$GLOBALS['wpdb']->term_relationships} tr ON tr.object_id = p.ID
         JOIN {$GLOBALS['wpdb']->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
         WHERE tt.term_id = %d AND p.post_type = 'product' AND p.post_status = 'publish'",
        $term->term_id
    ));
    if ($total < 1) return;

    // Pull top 20 products — match SPA catalog render pattern (newest first by UAT)
    $products = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'tax_query'      => [[
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
        ]],
        'meta_key'  => '_nhanh_updated_at',
        'orderby'   => 'meta_value_num',
        'order'     => 'DESC',
    ]);
    if (!$products) return;

    $items = [];
    $pos = 1;
    foreach ($products as $p) {
        $permalink = get_permalink($p->ID);
        if (!$permalink) continue;
        $items[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'url'      => $permalink,
            'name'     => wp_strip_all_tags(get_the_title($p->ID)),
        ];
    }
    if (!$items) return;

    $payload = [
        '@context' => 'https://schema.org',
        '@type'    => 'CollectionPage',
        'name'     => wp_strip_all_tags($title),
        'description' => wp_strip_all_tags($description),
        'url'      => $url,
        'mainEntity' => [
            '@type'         => 'ItemList',
            'numberOfItems' => $total,
            'itemListElement' => $items,
        ],
    ];

    echo '<script type="application/ld+json" id="oscar-seo-front-collection">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}