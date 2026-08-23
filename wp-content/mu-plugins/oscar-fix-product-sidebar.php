<?php
/**
 * Plugin Name: Oscar Fix Product Sidebar
 * Description: Remove default WC sidebar widget on single product pages
 *              (WordPress renders default Pages/Archives/Categories widgets
 *              via woocommerce_get_sidebar hook, breaking SPA layout)
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;

add_action('init', static function () {
    // Remove default WC sidebar rendering on product pages
    remove_action('woocommerce_sidebar', 'woocommerce_get_sidebar');
}, 999);