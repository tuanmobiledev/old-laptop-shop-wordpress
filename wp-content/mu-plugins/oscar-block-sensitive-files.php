<?php
/**
 * Plugin Name: Oscar Block Sensitive Files
 * Description: Boss 2026-09-03: Return 403 for sensitive files (.log, .sql, .bak, .env, .ini, .yml, .git, .swp) under wp-content/ to prevent info disclosure.
 * Version: 1.0.0
 * Author: Oscar Shop
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', static function (): void {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    $path        = wp_parse_url($request_uri, PHP_URL_PATH) ?? '';

    // Match files in wp-content/ with sensitive extensions or hidden dotfiles
    if (preg_match('#/wp-content/(?:.+/)?[^/]+\.(log|sql|bak|sql\.gz|tar|gz|zip|env|ini|yml|yaml|swp|swo)$#i', $path)) {
        status_header(403);
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }

    // Also block direct access to .git/, .svn/, .hg/ anywhere in wp-content
    if (preg_match('#/\.(git|svn|hg)/#i', $path)) {
        status_header(403);
        nocache_headers();
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }
}, 1);
