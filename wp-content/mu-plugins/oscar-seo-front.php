<?php
/**
 * Plugin Name: OSCAR SEO Front
 * Description: Front-end SEO fixes that complement oscar-seo.php:
 *              - <html lang="vi-VN"> site-wide
 *              - Organization + WebSite JSON-LD schema on the homepage
 *              - LocalBusiness JSON-LD when filtered data is provided
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
 * Organization + WebSite JSON-LD on the homepage only.
 * Priority 12 — runs after oscar_seo_product_jsonld (priority 6) and
 * after core WP head (priority 8–11).
 */
add_action('wp_head', 'oscar_seo_front_org_schema', 12);
function oscar_seo_front_org_schema()
{
    if (is_admin()) return;
    if (!is_front_page() && !is_home()) return;

    $home       = home_url('/');
    $name       = 'Laptop OSCAR Thủ Đức';
    $logo       = $home . 'wp-content/themes/oscar-shop/assets/images/oscar-cover.webp';
    $desc       = get_bloginfo('description', 'display') ?: 'Laptop OSCAR Thủ Đức — laptop cũ, phụ kiện và sửa chữa';

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

    // LocalBusiness: emit only when a filter provides data. Avoid guessing
    // Boss's store address, phone, opening hours, etc.
    $local = apply_filters('oscar_seo_front_localbusiness', null);
    if (is_array($local) && !empty($local['name'])) {
        $local['@context'] = 'https://schema.org';
        $local['@type']    = !empty($local['@type']) ? $local['@type'] : 'LocalBusiness';
        if (empty($local['@id'])) {
            $local['@id'] = $home . '#localbusiness';
        }
        if (empty($local['name'])) {
            $local['name'] = $name;
        }
        if (empty($local['url'])) {
            $local['url'] = $home;
        }
        $graph[] = $local;
    }

    $payload = [
        '@context' => 'https://schema.org',
        '@graph'   => $graph,
    ];

    echo '<script type="application/ld+json" id="oscar-seo-front-ld">' . wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}
