<?php
/*
Plugin Name: Deploy V2
Description: Theme files deployer (functions.php, etc.)
Version: 2.0
*/
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    register_rest_route('helper/v1', '/deploy-v2/write', [
        'methods' => 'POST',
        'permission_callback' => function() { return current_user_can('edit_themes'); },
        'callback' => function($req) {
            $path = $req->get_param('path');
            $content_b64 = $req->get_param('content_b64');
            if (!$path || !$content_b64) {
                return new \WP_REST_Response(['error' => 'path and content_b64 required'], 400);
            }
            // Allow only theme dir paths
            $theme_dir = wp_normalize_path(get_template_directory());
            $full_path = wp_normalize_path($theme_dir . '/' . ltrim($path, '/'));
            if (strpos($full_path, $theme_dir) !== 0) {
                return new \WP_REST_Response(['error' => 'path must be within theme dir', 'theme_dir' => $theme_dir, 'full_path' => $full_path], 400);
            }
            $content = base64_decode($content_b64, true);
            if ($content === false) {
                return new \WP_REST_Response(['error' => 'invalid base64'], 400);
            }
            // Backup existing file
            if (file_exists($full_path)) {
                @copy($full_path, $full_path . '.bak.' . time());
            }
            $bytes = @file_put_contents($full_path, $content);
            if ($bytes === false) {
                return new \WP_REST_Response(['error' => 'write failed', 'path' => $full_path], 500);
            }
            return new \WP_REST_Response([
                'ok' => true,
                'path' => $full_path,
                'bytes' => $bytes
            ], 200);
        }
    ]);
});
