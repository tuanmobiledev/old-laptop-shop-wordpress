<?php
/*
Plugin Name: Blog Meta Helper
Description: Register _oscar_* meta keys and bulk-seed EN translations for blog posts
Version: 1.0
*/

if (!defined('ABSPATH')) exit;

// Register meta keys for REST API exposure
add_action('init', function() {
    $keys = ['_oscar_title_en', '_oscar_content_en', '_oscar_excerpt_en', '_oscar_old_time', '_oscar_query', '_oscar_tag_en'];
    foreach ($keys as $key) {
        register_meta('post', $key, [
            'object_subtype' => 'post',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); },
        ]);
    }
});

// REST endpoint to bulk-set meta for a list of posts
add_action('rest_api_init', function() {
    register_rest_route('helper/v1', '/blog-meta', [
        'methods' => 'POST',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function(\WP_REST_Request $req) {
            $items = $req->get_param('items');
            if (!is_array($items)) return new \WP_Error('bad_request', 'items must be array', ['status' => 400]);
            
            $results = [];
            foreach ($items as $item) {
                $post_id = intval($item['id'] ?? 0);
                $meta = $item['meta'] ?? [];
                if (!$post_id) {
                    $results[] = ['id' => $post_id, 'ok' => false, 'error' => 'invalid id'];
                    continue;
                }
                $updated = [];
                foreach ($meta as $k => $v) {
                    $key = '_oscar_' . ltrim($k, '_oscar_');
                    update_post_meta($post_id, $key, $v);
                    $updated[$key] = substr($v, 0, 50);
                }
                $results[] = ['id' => $post_id, 'ok' => true, 'updated' => $updated];
            }
            return new \WP_REST_Response(['results' => $results], 200);
        },
    ]);
    
    // Verify endpoint
    register_rest_route('helper/v1', '/blog-meta/verify', [
        'methods' => 'GET',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function() {
            $ids = get_posts(['post_type' => 'post', 'posts_per_page' => 20, 'fields' => 'ids']);
            $out = [];
            foreach ($ids as $id) {
                $out[] = [
                    'id' => $id,
                    'title_en' => get_post_meta($id, '_oscar_title_en', true),
                    'has_en' => (bool) get_post_meta($id, '_oscar_title_en', true),
                ];
            }
            return new \WP_REST_Response(['posts' => $out], 200);
        },
    ]);
});

// Self-cleanup: when admin requests ?deactivate=1, deactivate this plugin
add_action('init', function() {
    if (current_user_can('activate_plugins') && isset($_GET['deactivate_blog_meta'])) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('blog-meta-helper deactivated');
    }
});
