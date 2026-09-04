<?php
/**
 * Plugin Name: OSCAR Blog Auto Poster
 * Description: REST endpoint for n8n auto-publish blog posts (daily automation). Bearer-token auth, validates category, sideloads featured image, publishes.
 * Version: 1.0.0
 * Author: OSCAR Thủ Đức
 *
 * Endpoint: POST /wp-json/oscar/v1/blog/create
 * Auth: header `X-Oscar-Token: <OSCAR_BLOG_API_TOKEN>` (env var set via Coolify)
 *
 * Payload:
 *   title                  (required) string
 *   content_html           (required) string — full HTML body
 *   excerpt                (required) string — SEO summary
 *   category_slug          (required) string — one of: danh-gia-san-pham, tu-van-chon-mua, kien-thuc-laptop, su-dung-bao-duong, so-sanh, tin-cong-nghe
 *   featured_image_url     (optional) string — public URL of image to sideload
 *   featured_image_caption (optional) string
 *   featured_image_alt     (optional) string
 *   dry_run                (optional) bool — true = insert as draft + return, no publish
 */

defined('ABSPATH') || exit;

/**
 * Read bearer token from env (preferred) or fallback to constant.
 */
function oscar_blog_auto_poster_token(): string {
    $env_token = function_exists('getenv_docker') ? getenv_docker('OSCAR_BLOG_API_TOKEN', '') : (string) getenv('OSCAR_BLOG_API_TOKEN');
    if ($env_token !== '') return $env_token;
    return defined('OSCAR_BLOG_API_TOKEN') ? (string) OSCAR_BLOG_API_TOKEN : '';
}

function oscar_blog_auto_poster_validate_token(WP_REST_Request $req): bool {
    // Accept multiple header formats for compatibility:
    // 1. Authorization: Bearer <token> (standard)
    // 2. X-Oscar-Token: <token> (legacy/script-friendly)
    $auth_header = (string) $req->get_header('authorization');
    $provided = '';
    if ($auth_header !== '') {
        if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
            $provided = trim($matches[1]);
        }
    }
    if ($provided === '') {
        $provided = (string) $req->get_header('x_oscar_token');
    }
    $expected = oscar_blog_auto_poster_token();
    if ($expected === '' || $provided === '') return false;
    return hash_equals($expected, $provided);
}

/**
 * Map category slug to existing WP category term_id.
 * 4 fixed categories per Boss 2026-08-25. Refuses any other slug.
 */
function oscar_blog_auto_poster_resolve_category(string $slug): int|WP_Error {
    $valid_slugs = [
        'danh-gia-san-pham',
        'tu-van-chon-mua',
        'kien-thuc-laptop',
        'su-dung-bao-duong',
        'so-sanh',
        'tin-cong-nghe',
    ];
    if (!in_array($slug, $valid_slugs, true)) {
        return new WP_Error('invalid_category', 'category_slug must be one of: ' . implode(', ', $valid_slugs), ['status' => 400]);
    }
    $term = get_term_by('slug', $slug, 'category');
    if (!$term) {
        return new WP_Error('category_missing', "Category '$slug' does not exist in WP", ['status' => 404]);
    }
    return (int) $term->term_id;
}

/**
 * Sideload image from URL → media library → attach to post.
 * Returns attachment ID or WP_Error. Rolls back nothing — caller decides.
 *
 * PITFALL: media_handle_sideload() works fine HERE (called via HTTP request,
 * not via wp eval-file) — different from the pitfall in
 * oscar-blog-style-guide §"Featured image abstract bokeh" which applies to
 * wp eval-file context only.
 */
function oscar_blog_auto_poster_sideload_image(string $url, int $post_id, string $caption = '', string $alt = ''): int|WP_Error {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    // PITFALL: Bearer-token REST call has NO user context → wp_check_filetype_and_ext
    // runs as guest → "Sorry, you are not allowed to upload this file type" even
    // for valid images. Fix: set current user to admin before sideload.
    if (!function_exists('wp_set_current_user')) {
        require_once ABSPATH . 'wp-includes/pluggable.php';
    }
    wp_set_current_user(1);
    if (!current_user_can('upload_files')) {
        return new WP_Error('no_upload_perm', 'Current user (id=1) lacks upload_files capability', ['status' => 403]);
    }

    $tmp = download_url($url, 30);
    if (is_wp_error($tmp)) {
        return new WP_Error('image_download_failed', 'Could not fetch image: ' . $tmp->get_error_message(), ['status' => 502]);
    }

    // Inspect actual file content (not URL extension — Unsplash URLs have no extension)
    // PITFALL: wp_check_filetype_and_ext returns ['type' => false] when filename has no
    // recognized extension. Need to fall back to finfo_file in that case.
    $mime = wp_check_filetype_and_ext($tmp, basename(parse_url($url, PHP_URL_PATH) ?: 'featured.jpg'));
    $type = $mime['type'] ?? '';
    $ext  = $mime['ext'] ?? '';
    if (empty($type) || empty($ext)) {
        // Fallback: re-check using tmp file's own content via finfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected = finfo_file($finfo, $tmp);
            finfo_close($finfo);
            if ($detected) {
                $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/avif' => 'avif'];
                $type = $detected;
                $ext  = $map[$detected] ?? 'jpg';
            }
        }
        if (empty($type) || empty($ext)) {
            @unlink($tmp);
            return new WP_Error('image_type_unknown', 'Could not determine image mime type from URL or file content', ['status' => 400]);
        }
    }

    $base = basename(parse_url($url, PHP_URL_PATH) ?: 'featured');
    // Strip query string + add proper extension
    $base = preg_replace('/\?.*$/', '', $base);
    $base = preg_replace('/\.(jpg|jpeg|png|webp|gif)$/i', '', $base);
    $filename = $base . '.' . $ext;

    $file_array = [
        'name'     => $filename,
        'tmp_name' => $tmp,
        'type'     => $type,
    ];

    $attach_id = media_handle_sideload($file_array, $post_id, $caption);
    if (is_wp_error($attach_id)) {
        @unlink($tmp);
        return new WP_Error('image_sideload_failed', 'media_handle_sideload: ' . $attach_id->get_error_message(), ['status' => 500]);
    }

    if ($caption) {
        wp_update_post([
            'ID'           => $attach_id,
            'post_excerpt' => $caption,
        ]);
    }
    if ($alt) {
        update_post_meta($attach_id, '_wp_attachment_image_alt', $alt);
    }
    return (int) $attach_id;
}

/**
 * Verify post count invariants after insert (Boss rule — post_type post vs product separation).
 */
function oscar_blog_auto_poster_verify_invariants(int $expected_posts_added, int $expected_products_change = 0): array {
    $posts    = (int) wp_count_posts('post')->publish;
    $products = (int) wp_count_posts('product')->publish;
    return [
        'posts_publish_count'    => $posts,
        'products_publish_count' => $products,
        'note' => 'Caller must compare to pre-insert counts. Expected posts_delta=' . $expected_posts_added . ', products_delta=' . $expected_products_change,
    ];
}

/**
 * Main handler.
 */
function oscar_blog_auto_poster_handler(WP_REST_Request $req): WP_REST_Response|WP_Error {
    // 1. Extract + sanitize input
    $title          = trim((string) $req->get_param('title'));
    $content_html   = (string) $req->get_param('content_html');
    $excerpt        = trim((string) $req->get_param('excerpt'));
    $category_slug  = trim((string) $req->get_param('category_slug'));
    $image_url      = trim((string) $req->get_param('featured_image_url'));
    $image_caption  = trim((string) $req->get_param('featured_image_caption'));
    $image_alt      = trim((string) $req->get_param('featured_image_alt'));
    $dry_run        = filter_var($req->get_param('dry_run'), FILTER_VALIDATE_BOOLEAN);

    // 2. Validate required
    $missing = [];
    if ($title === '')        $missing[] = 'title';
    if ($content_html === '') $missing[] = 'content_html';
    if ($excerpt === '')      $missing[] = 'excerpt';
    if ($category_slug === '')$missing[] = 'category_slug';
    if ($missing) {
        return new WP_Error('missing_fields', 'Missing required fields: ' . implode(', ', $missing), ['status' => 400]);
    }
    if (mb_strlen($title) > 200) {
        return new WP_Error('title_too_long', 'title max 200 chars (got ' . mb_strlen($title) . ')', ['status' => 400]);
    }
    if (mb_strlen($excerpt) > 300) {
        return new WP_Error('excerpt_too_long', 'excerpt max 300 chars (got ' . mb_strlen($excerpt) . ')', ['status' => 400]);
    }
    if (mb_strlen($content_html) < 500) {
        return new WP_Error('content_too_short', 'content_html min 500 chars (got ' . mb_strlen($content_html) . ') — Mode A blog posts must be 1500-3500 words', ['status' => 400]);
    }

    // 3. Validate category
    $cat_id = oscar_blog_auto_poster_resolve_category($category_slug);
    if (is_wp_error($cat_id)) return $cat_id;

    // 4. Capture pre-insert counts (Boss invariant)
    $pre_posts    = (int) wp_count_posts('post')->publish;
    $pre_products = (int) wp_count_posts('product')->publish;

    // 5. Insert post as DRAFT first (safer — if image fails, don't pollute public list)
    $post_id = wp_insert_post([
        'post_title'    => $title,
        'post_content'  => wp_slash($content_html),
        'post_excerpt'  => $excerpt,
        'post_status'   => 'draft',
        'post_author'   => 1,
        'post_category' => [$cat_id],
        'post_type'     => 'post',
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_Error('insert_failed', 'wp_insert_post: ' . $post_id->get_error_message(), ['status' => 500]);
    }

    $result = [
        'post_id'     => $post_id,
        'slug'        => get_post_field('post_name', $post_id),
        'category_id' => $cat_id,
    ];

    // 6. Sideload featured image (if URL provided)
    if ($image_url !== '') {
        $attach_id = oscar_blog_auto_poster_sideload_image($image_url, $post_id, $image_caption, $image_alt);
        if (is_wp_error($attach_id)) {
            // Rollback post
            wp_delete_post($post_id, true);
            return new WP_Error('image_failed', 'sideload failed; rolled back post: ' . $attach_id->get_error_message(), ['status' => 502]);
        }
        set_post_thumbnail($post_id, $attach_id);
        $result['featured_image_id'] = $attach_id;
        $result['featured_image_url'] = wp_get_attachment_url($attach_id);
    }

    // 7. Publish (unless dry_run)
    if (!$dry_run) {
        $pub = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
        if (is_wp_error($pub)) {
            wp_delete_post($post_id, true);
            return new WP_Error('publish_failed', 'wp_update_post: ' . $pub->get_error_message(), ['status' => 500]);
        }
    }

    // 8. Final invariant check
    $post_posts    = (int) wp_count_posts('post')->publish;
    $post_products = (int) wp_count_posts('product')->publish;

    $result['post_url']     = get_permalink($post_id);
    $result['post_status']  = $dry_run ? 'draft' : 'publish';
    $result['invariants']   = [
        'posts_publish_count_before'    => $pre_posts,
        'posts_publish_count_after'     => $post_posts,
        'products_publish_count_before' => $pre_products,
        'products_publish_count_after'  => $post_products,
        'posts_delta'                   => $post_posts - $pre_posts,
        'products_delta'                => $post_products - $pre_products,
        'invariant_ok'                  => ($post_products === $pre_products),  // products MUST not change
    ];

    return new WP_REST_Response($result, $dry_run ? 200 : 201);
}

/**
 * Register endpoint.
 */
add_action('rest_api_init', function () {
    register_rest_route('oscar/v1', '/blog/create', [
        'methods'             => 'POST',
        'permission_callback' => 'oscar_blog_auto_poster_validate_token',
        'callback'            => 'oscar_blog_auto_poster_handler',
        'args'                => [
            'title'                  => ['required' => false, 'type' => 'string'],
            'content_html'           => ['required' => false, 'type' => 'string'],
            'excerpt'                => ['required' => false, 'type' => 'string'],
            'category_slug'          => ['required' => false, 'type' => 'string'],
            'featured_image_url'     => ['required' => false, 'type' => 'string'],
            'featured_image_caption' => ['required' => false, 'type' => 'string'],
            'featured_image_alt'     => ['required' => false, 'type' => 'string'],
            'dry_run'                => ['required' => false, 'type' => 'boolean'],
        ],
    ]);
});
