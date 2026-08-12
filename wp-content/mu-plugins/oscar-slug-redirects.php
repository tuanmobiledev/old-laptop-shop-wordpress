<?php
/**
 * Plugin Name: OSCAR Slug Redirects
 * Description: 301 redirects for product slugs that changed (e.g. Nhanh rename).
 *              Maintain via WP option 'oscar_slug_redirects' = [old_path => new_path, ...].
 *              Add entries with: update_option('oscar_slug_redirects', [...]); from WP-CLI or REST.
 * Author: OSCAR Thủ Đức
 */

defined('ABSPATH') || exit;

/**
 * Apply redirects on template_redirect.
 * Priority 1 (very early) so we beat any 404 page render.
 */
add_action('template_redirect', 'oscar_slug_redirects_apply', 1);

function oscar_slug_redirects_apply() {
    if (is_admin()) return;

    $redirects = get_option('oscar_slug_redirects', []);
    if (!is_array($redirects) || !$redirects) return;

    $uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '';
    if (!$uri) return;

    // Strip query string for matching
    $path = strtok($uri, '?');
    $path = rtrim($path, '/');

    // Try exact path match (without trailing slash)
    $key = $path . '/';
    if (!isset($redirects[$key]) && !isset($redirects[$path])) {
        foreach ($redirects as $from => $to) {
            $from_norm = rtrim($from, '/');
            if ($from_norm === $path) {
                $key = $from;
                break;
            }
        }
    }
    if (!$key || !isset($redirects[$key])) return;

    $target = $redirects[$key];
    if (preg_match('#^https?://#i', $target)) {
        $url = $target;
    } else {
        $url = home_url($target);
    }

    wp_safe_redirect($url, 301, 'OSCAR Slug Redirect');
    exit;
}
