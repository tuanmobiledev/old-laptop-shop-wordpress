<?php
/*
Plugin Name: Blog Asset Deploy
Description: One-shot asset deployer for #blog SPA bundle
Version: 1.0
*/
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    // Check what paths are writable
    register_rest_route('helper/v1', '/blog-asset/check', [
        'methods' => 'GET',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function() {
            $paths = [
                'theme' => get_template_directory() . '/assets/',
                'theme_uri' => get_template_directory_uri() . '/assets/',
                'uploads' => wp_upload_dir()['basedir'] . '/',
            ];
            $results = [];
            foreach ($paths as $key => $path) {
                $results[$key] = [
                    'path' => $path,
                    'exists' => is_dir($path),
                    'writable' => is_writable($path),
                    'readable' => is_readable($path),
                ];
            }
            // Try to write a test file
            $test_path = get_template_directory() . '/assets/_test_write.txt';
            $write_ok = @file_put_contents($test_path, 'test');
            if ($write_ok !== false) {
                @unlink($test_path);
            }
            $results['test_write'] = [
                'path' => $test_path,
                'ok' => $write_ok !== false,
            ];
            return new \WP_REST_Response($results, 200);
        }
    ]);

    // Deploy assets: receive JSON with filename + content (base64), write to theme assets dir
    register_rest_route('helper/v1', '/blog-asset/deploy', [
        'methods' => 'POST',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function($req) {
            $files = $req->get_param('files');
            if (!is_array($files)) return new \WP_REST_Response(['error' => 'files must be array'], 400);
            $assets_dir = get_template_directory() . '/assets/';
            if (!is_writable($assets_dir)) {
                return new \WP_REST_Response(['error' => 'Assets dir not writable: ' . $assets_dir], 500);
            }
            $results = [];
            foreach ($files as $file) {
                $name = basename($file['name'] ?? '');
                $content_b64 = $file['content_b64'] ?? '';
                $content = base64_decode($content_b64, true);
                if (!$name || $content === false) {
                    $results[] = ['name' => $name, 'ok' => false, 'error' => 'invalid input'];
                    continue;
                }
                $target = $assets_dir . $name;
                $written = @file_put_contents($target, $content);
                if ($written === false) {
                    $results[] = ['name' => $name, 'ok' => false, 'error' => 'write failed'];
                } else {
                    $results[] = ['name' => $name, 'ok' => true, 'bytes' => $written, 'path' => $target];
                }
            }
            return new \WP_REST_Response(['results' => $results], 200);
        }
    ]);
});
