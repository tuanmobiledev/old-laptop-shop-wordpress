<?php
/*
Plugin Name: Blog Meta Inspector
Description: Inspect stored meta for blog posts
Version: 1.0
*/
if (!defined('ABSPATH')) exit;

add_action('rest_api_init', function() {
    register_rest_route('helper/v1', '/blog-meta/inspect', [
        'methods' => 'GET',
        'permission_callback' => function() { return current_user_can('edit_posts'); },
        'callback' => function() {
            $ids = get_posts(['post_type' => 'post', 'posts_per_page' => 5, 'fields' => 'ids']);
            $out = [];
            foreach ($ids as $id) {
                $title_en = get_post_meta($id, '_oscar_title_en', true);
                $content_en = get_post_meta($id, '_oscar_content_en', true);
                $out[] = [
                    'id' => $id,
                    'title_en' => $title_en,
                    'title_en_len' => strlen($title_en),
                    'content_en' => substr($content_en, 0, 100),
                    'content_en_len' => strlen($content_en),
                ];
            }
            return new \WP_REST_Response(['posts' => $out], 200);
        },
    ]);
});
