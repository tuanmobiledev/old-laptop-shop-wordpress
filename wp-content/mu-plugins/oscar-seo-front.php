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