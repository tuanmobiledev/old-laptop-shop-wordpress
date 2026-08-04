<?php
/*
Plugin Name: Blog Meta V2
Description: Clean EN content seeder for blog posts
Version: 2.0
*/
if (!defined('ABSPATH')) exit;

// Register meta for REST visibility
add_action('init', function() {
    $keys = ['_oscar_title_en', '_oscar_content_en', '_oscar_excerpt_en', '_oscar_query', '_oscar_old_time', '_oscar_tag_en'];
    foreach ($keys as $key) {
        register_meta('post', $key, [
            'object_subtype' => 'post',
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can('edit_posts'); }
        ]);
    }
});

add_action('rest_api_init', function() {
    register_rest_route('helper/v1', '/blog-meta-v2/seed', [
        'methods' => 'POST',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function($req) {
            $items = $req->get_param('items');
            if (!is_array($items)) return new \WP_REST_Response(['error' => 'items must be array'], 400);
            $results = [];
            foreach ($items as $item) {
                $post_id = intval($item['id'] ?? 0);
                if (!$post_id) continue;
                $meta = $item['meta'] ?? [];
                $updated = [];
                foreach ($meta as $key => $value) {
                    $full_key = '_oscar_' . $key;
                    $r = update_post_meta($post_id, $full_key, $value);
                    $updated[$full_key] = get_post_meta($post_id, $full_key, true);
                }
                $results[] = ['id' => $post_id, 'ok' => true, 'updated' => $updated];
            }
            return new \WP_REST_Response(['count' => count($results), 'results' => $results], 200);
        }
    ]);

    register_rest_route('helper/v1', '/blog-meta-v2/list', [
        'methods' => 'GET',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function() {
            $posts = get_posts(['post_type' => 'post', 'posts_per_page' => -1, 'fields' => 'ids']);
            $out = [];
            foreach ($posts as $id) {
                $out[] = [
                    'id' => $id,
                    'title_en_len' => strlen(get_post_meta($id, '_oscar_title_en', true)),
                    'content_en_len' => strlen(get_post_meta($id, '_oscar_content_en', true)),
                    'excerpt_en_len' => strlen(get_post_meta($id, '_oscar_excerpt_en', true)),
                ];
            }
            return new \WP_REST_Response($out, 200);
        }
    ]);
});
