<?php
/**
 * SPA fallback template.
 *
 * Used by WP template hierarchy when no specific template matches:
 *   - Front page / homepage
 *   - Static pages (post_type=page) without their own template
 *   - Archives, search results, 404
 *   - WC product, shop, cart, checkout, my-account (WC wraps via get_header/get_footer)
 *
 * The React bundle (`<div id="root">`) renders all in-page content client-side.
 * `get_footer()` includes `template-parts/footer-business.php` (single source
 * of truth — same footer used by `single.php` blog detail).
 *
 * NOTE: SPA bundle loading + asset hashes are wired in functions.php — see
 * `wp_enqueue_script()` block that hashes index-XXXX.js + styles.css.
 *
 * @package Oscar_Shop
 */
defined('ABSPATH') || exit;
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('oscar-react-storefront'); ?>>
<?php wp_body_open(); ?>
<div id="root"></div>
<noscript>Bạn cần bật JavaScript để sử dụng website Laptop OSCAR Thủ Đức.</noscript>
<?php get_footer(); ?>