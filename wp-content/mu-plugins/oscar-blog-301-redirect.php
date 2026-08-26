<?php
/**
 * Plugin Name: OSCAR Blog 301 Redirect
 * Description: 301 redirect cho 3 slug blog đã đổi (posts 913, 916, 939) để giữ traffic SEO cũ.
 * Version: 1.1.0
 * Author: Laptop OSCAR Thủ Đức
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    $redirects = [
        'top-5-laptop-dell-cu-dang-mua-nhat-2026'         => 'top-5-laptop-dell-cu',
        'dell-precision-5570-workstation-do-hoa-mong-nhe' => 'dell-precision-5570-vs-5560-workstation-do-hoa',
        'hp-omnibook-x-flip-16-next-gen-ai'               => 'hp-omnibook-x-flip-16-next-gen-ai-2026',
    ];
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    foreach ($redirects as $old => $new) {
        $pattern = '#^/blog/' . preg_quote($old, '#') . '/?$#';
        if (preg_match($pattern, $uri)) {
            wp_redirect(home_url('/blog/' . $new . '/'), 301);
            exit;
        }
    }
}, 1);
