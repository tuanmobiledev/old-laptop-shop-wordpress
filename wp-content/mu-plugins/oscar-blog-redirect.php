<?php
/**
 * Plugin Name: Oscar Blog Redirect
 * Description: 301 redirect old date-based URLs (/2026/08/22/slug/) -> /blog/slug/
 * Version: 1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'template_redirect', function () {
    $uri = $_SERVER['REQUEST_URI'];
    if ( preg_match( '#^/(\d{4})/(\d{2})/(\d{2})/([^/]+)/?$#', $uri, $m ) ) {
        $slug = $m[4];
        $post = get_page_by_path( $slug, OBJECT, array( 'post', 'page' ) );
        if ( ! $post ) {
            $post = get_page_by_path( $slug, OBJECT, 'post' );
        }
        if ( $post && $post->post_status === 'publish' ) {
            $target = home_url( '/blog/' . $slug . '/' );
            wp_safe_redirect( $target, 301 );
            exit;
        }
    }
}, 1 );
