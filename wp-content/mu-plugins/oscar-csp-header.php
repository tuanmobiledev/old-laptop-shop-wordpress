<?php
/**
 * Plugin Name: Oscar CSP Header (Report-Only)
 * Description: Emits Content-Security-Policy-Report-Only header + logs violations via /wp-json/oscar/v1/csp-report. Phase 1 (Report-Only) before Phase 2 (Enforce) in v47.14.
 * Version: v47.13
 * Author: Boss <maytinhoscar@gmail.com>
 *
 * Boss 2026-09-02: Security gap lớn nhất hiện tại — KHÔNG có CSP header.
 * WordPress core + Yoast + WooCommerce + Nhanh sync + SPA bundle đều không bị protect.
 * Phase 1 (v47.13, this): Report-Only — observe violations, KHÔNG block.
 * Phase 2 (v47.14): Enforce — sau khi phân tích log sạch.
 *
 * Directives rationale:
 * - script-src 'unsafe-inline' 'unsafe-eval': Vite bundle cần eval cho dev mode + inline scripts từ WP core / Yoast. Long-term: nonce-based, chưa apply.
 * - style-src 'unsafe-inline': React inline styles + Yoast inline CSS + WP block editor.
 * - img-src 'self' data: https: blob: SPA load ảnh từ wp-content/uploads/ (self), Google user avatars (https), inline SVG (data:), blob uploads.
 * - font-src 'self' data: fonts.gstatic.com: Vite woff2 local + Google Fonts CDN.
 * - connect-src 'self': REST API endpoints (WP + oscar/v1).
 * - frame-src 'self' google.com maps.google.com youtube.com: embedded maps + videos.
 * - frame-ancestors 'none': anti-clickjacking (replaces X-Frame-Options).
 * - base-uri 'self': prevent <base> hijacking.
 * - form-action 'self': form chỉ submit về same origin (prevent form-based exfiltration).
 * - object-src 'none': block legacy <object>/<embed>/<applet>.
 * - report-uri: browser POST violation report tới endpoint này.
 */

if (!defined('ABSPATH')) { exit; }

add_action('send_headers', 'oscar_csp_report_only_header', 1);
function oscar_csp_report_only_header() {
    $directives = [
        "default-src 'self'",
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
        "img-src 'self' data: https: blob:",
        "font-src 'self' data: https://fonts.gstatic.com",
        "connect-src 'self'",
        "frame-src 'self' https://www.google.com https://maps.google.com https://www.youtube.com",
        "frame-ancestors 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "object-src 'none'",
        "report-uri /wp-json/oscar/v1/csp-report",
    ];
    header('Content-Security-Policy-Report-Only: ' . implode('; ', $directives));
}

add_action('rest_api_init', 'oscar_csp_register_report_route');
function oscar_csp_register_report_route() {
    register_rest_route('oscar/v1', '/csp-report', [
        'methods'             => 'POST,GET',
        'permission_callback' => '__return_true', // public endpoint, browser sends violation reports without auth
        'callback'            => 'oscar_csp_handle_report',
    ]);
}

function oscar_csp_handle_report(WP_REST_Request $request) {
    $body = $request->get_body();
    $log_file = WP_CONTENT_DIR . '/csp-violations.log';

    // Append violation entry. Format: ISO8601 timestamp + raw body (JSON) + newline.
    // Log file capped at ~10MB bằng simple rotation (giữ last 5000 entries).
    $entry = sprintf("[%s] %s\n", gmdate('c'), $body);

    // Atomic write with size cap
    if (file_exists($log_file) && filesize($log_file) > 10 * 1024 * 1024) {
        // Rotate: rename to .1, start fresh
        @rename($log_file, $log_file . '.1');
    }
    @file_put_contents($log_file, $entry, FILE_APPEND | LOCK_EX);

    // Always respond 204 No Content (browser doesn't need body)
    return new WP_REST_Response(null, 204);
}