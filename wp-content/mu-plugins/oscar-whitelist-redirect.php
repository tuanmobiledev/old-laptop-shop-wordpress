<?php
/**
 * Plugin Name: OSCAR Whitelist Redirect
 * Description: Chỉ cho phép các URL trong whitelist; mọi URL khác redirect về trang chủ.
 *              Đặc biệt: /product/<wp-slug>/ → 301 /san-pham/<wp_slug>-p<oscar_source_id>/
 * Version:     1.2.0
 * Author:      Hermes
 * Boss decision: 2026-08-25 — whitelist các page: /, /san-pham/<slug>-p<id>/ (ID hợp lệ),
 *                /blog/<post-slug>/ (post tồn tại), hash routes. /product/<slug>/ → /san-pham/<slug>-p<id>/ (301).
 *                Tất cả URL khác → silent redirect về homepage.
 *
 * v1.2.0 (2026-08-25): dùng is_singular() thay get_posts() cho /blog/ và /product/.
 *                      Tránh WP render 404 template cho /blog/<invalid>/ — redirect về /.
 */

defined('ABSPATH') || exit;

add_action('template_redirect', 'oscar_whitelist_redirect', 1);

function oscar_whitelist_redirect(): void
{
    // Bypass: admin, AJAX, REST, cron
    if (is_admin()) {
        return;
    }
    if (defined('DOING_AJAX') && DOING_AJAX) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }

    $path = oscar_get_request_path();

    // Boss 2026-10-27 (GSC audit): /san-pham/<slug>-pN KHÔNG có dấu "/" cuối
    // phải 301 về bản CÓ dấu "/" — canonical (oscar_seo_inject_canonical) +
    // product-sitemap.xml đều dùng trailing slash. Nếu không redirect, mỗi PDP
    // có 2 biến thể URL cùng nội dung → loãng tín hiệu + tốn crawl budget.
    $raw_uri  = $_SERVER['REQUEST_URI'] ?? '';
    $raw_path = (string) strtok($raw_uri, '?');
    if ($raw_path !== '' && $raw_path !== '/' && substr($raw_path, -1) !== '/'
        && preg_match('#^/san-pham/[^/]+-p[0-9]+$#', $raw_path)) {
        nocache_headers();
        wp_safe_redirect(home_url($raw_path . '/'), 301);
        exit;
    }

    // Boss 2026-10-27 (GSC audit): URL rác dạng /san-pham/<slug>-p1093/+84984496260
    // do formatTel() thiếu prefix "tel:" → browser resolve "+84984496260" thành
    // RELATIVE URL, ghép vào path hiện tại. Site trả 302 về home → Google mất
    // tín hiệu + tốn crawl budget (Googlebot đã crawl 25 lần, Ahrefs 60 lần).
    // Fix frontend đã sửa nguồn; rule này dọn các URL rác đã bị Google index.
    // Pattern: path kết thúc bằng /+<8-15 chữ số> → bỏ segment đó, 301 về
    // URL gốc (PDP canonical nếu hợp lệ, không thì về home).
    if (preg_match('#^(.*?)/(\+[0-9]{8,15})$#', $path, $m)) {
        $stripped = $m[1] !== '' ? $m[1] : '/';
        // Nếu phần còn lại là PDP hợp lệ → 301 về canonical PDP.
        if (preg_match('#^/san-pham/([^/]+?)-p([0-9]+)$#', $stripped, $pm)
            && oscar_is_valid_oscar_id((int) $pm[2])) {
            nocache_headers();
            wp_safe_redirect(home_url('/san-pham/' . $pm[1] . '-p' . $pm[2] . '/'), 301);
            exit;
        }
        // Còn lại (vd /san-pham/+84984496260, /+84984496260) → 410 Gone.
        // 2026-11-09: 301 về home vẫn bị GSC xếp vào "Trang có lệnh chuyển hướng";
        // 410 bảo Google bỏ hẳn URL rác khỏi index.
        oscar_gone();
        return;
    }

    // /product/<wp-slug>/ → /san-pham/<slug>-p<oscar_source_id>/
    // WP đã resolve query: is_singular('product') true nếu product tồn tại.
    if (preg_match('#^/product/([^/]+?)/?$#', $path, $m)) {
        if (is_singular('product')) {
            oscar_redirect_product_to_canonical();
            return;
        }
        // Boss 2026-11-09: permalink base của WooCommerce trên site này là /san-pham,
        // nên /product/<slug>/ KHÔNG được WP resolve → is_singular() false. Tra post
        // theo slug rồi 301 về canonical để giữ link equity của các URL /product/ cũ
        // mà Google đã index (trước đây 302 về home).
        $looked = get_posts([
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'name'           => (string) $m[1],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if ($looked) {
            $oscar_id = (int) get_post_meta((int) $looked[0], '_oscar_source_id', true);
            if ($oscar_id > 0) {
                nocache_headers();
                wp_safe_redirect(home_url('/san-pham/' . get_post_field('post_name', (int) $looked[0]) . '-p' . $oscar_id . '/'), 301);
                exit;
            }
        }
        // URL có /product/ nhưng không tìm thấy product → 410 (2026-11-09, thay 302 về home)
        oscar_gone();
        return;
    }

    // /san-pham/<slug>-p<id>/ — validate ID tồn tại trong DB (Oscar IDs không phải WP post IDs)
    if (preg_match('#^/san-pham/([^/]+?)-p([0-9]+)/?$#', $path, $m)) {
        if (oscar_is_valid_oscar_id((int) $m[2])) {
            // Boss 2026-10-27 (GSC audit): slug trong URL phải khớp WP post_name
            // (canonical + sitemap dùng post_name). SPA trước đây dựng slug từ
            // product.name → lệch 96/96 sản phẩm → 96 URL trùng lặp cùng nội dung.
            // URL có slug sai → 301 về canonical (giữ 1 URL duy nhất mỗi PDP).
            $canonical_slug = oscar_get_canonical_slug((int) $m[2]);
            if ($canonical_slug !== '' && $canonical_slug !== $m[1]) {
                nocache_headers();
                wp_safe_redirect(home_url('/san-pham/' . $canonical_slug . '-p' . $m[2] . '/'), 301);
                exit;
            }
            return; // Valid, để WP render SPA product view
        }
        // Boss 2026-09-16: OSCAR ID không hợp lệ (vd p1021 INACTIVE) — không redirect
        // về home vì (a) trước đây redirect làm p1021 về /, Google index nhầm thành
        // duplicate home, (b) giờ oscar_seo_inject_canonical đã emit <meta noindex>
        // cho case này. Để WP render SPA shell + noindex cho Google bỏ qua.
        // Boss 2026-11-09 (GSC: "Bị loại trừ bởi thẻ noindex"): trả 200 + noindex thì
        // Google xếp vào mục noindex và /san-pham/<slug bất kỳ>-p<ID chết>/ thành không
        // gian URL vô hạn. Đổi sang 410 Gone: giữ noindex meta, Google bỏ URL hẳn.
        oscar_gone();
        return;
    }

    // /san-pham/<slug>/ (no -p<id>) — bare slug. Look up WP post by slug,
    // find _oscar_source_id → 301 to canonical /san-pham/<slug>-p<id>/.
    // Boss 2026-09-07: previously fell through to redirect_home() (302→/),
    // bad UX for users copy-pasting tab URL without the trailing -p<id>.
    if (preg_match('#^/san-pham/([^/]+?)/?$#', $path, $m)) {
        $bare_slug = (string) $m[1];
        $looked    = get_posts([
            'post_type'      => 'product',
            'name'           => $bare_slug,
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        if ($looked) {
            $oscar_id = get_post_meta((int) $looked[0], '_oscar_source_id', true);
            if ($oscar_id) {
                nocache_headers();
                wp_safe_redirect(home_url('/san-pham/' . $bare_slug . '-p' . $oscar_id . '/'), 301);
                exit;
            }
        }
        // Slug không tìm thấy hoặc thiếu _oscar_source_id → 410 (2026-11-09, thay 302 về home)
        oscar_gone();
        return;
    }

    // /blog/<post-slug>/ — WP resolve xong: is_singular('post') true nếu post tồn tại
    if (preg_match('#^/blog/[^/]+/?$#', $path)) {
        if (is_singular('post')) {
            return; // Valid post
        }
        // URL có /blog/<slug>/ nhưng WP không tìm thấy post → 410 (2026-11-09, thay 302 về home)
        oscar_gone();
        return;
    }

    // /blog/ hoặc /blog (bare) — page-blog.php template sẽ render list.
    // Pagination dùng query string ?paged=N (oscar_get_request_path đã strip),
    // nên /blog?paged=2 cũng chỉ là path /blog.
    if (preg_match('#^/blog/?$#', $path)) {
        return; // WP render page-blog.php (Page ID 922 đã gán template này)
    }

    // Whitelist khác (system paths, /, /wp-*...)
    if (oscar_is_path_whitelisted($path)) {
        return;
    }

    // Default: URL không tồn tại → 410 Gone (không còn 302 về home).
    // Boss 2026-11-09 (GSC: "Trang có lệnh chuyển hướng" + "Soft 404"): 302 về home
    // khiến Google coi mọi URL rác (/shop/, /cart/, /tag/..., archive) là redirect
    // và crawl lại mãi. 410 = URL không tồn tại thật, Google bỏ khỏi index.
    oscar_gone();
}

function oscar_get_request_path(): string
{
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path        = strtok($request_uri, '?');
    $path        = rtrim($path, '/');
    return $path === '' ? '/' : $path;
}

function oscar_is_path_whitelisted(string $path): bool
{
    // System paths — luôn cho phép
    if (preg_match(
        // 2026-11-09: thêm index.php + .well-known vào whitelist — trước đây
        // chúng 302 về home, giờ unknown path trả 410 nên phải giữ 200.
        '#^/(wp-(content|includes|admin|login\.php|json|cron\.php|signup\.php)|feed|sitemap|robots\.txt|favicon\.ico|index\.php|\.well-known)#i',
        $path
    )) {
        return true;
    }

    // Application whitelist
    $patterns = [
        '#^/$#',                          // home
    ];

    foreach ($patterns as $p) {
        if (preg_match($p, $path)) {
            return true;
        }
    }

    return false;
}

function oscar_redirect_home(): void
{
    nocache_headers();
    wp_safe_redirect(home_url('/'), 302);
    exit;
}

/**
 * 410 Gone — URL không còn tồn tại.
 *
 * Không exit(): để WP render SPA shell như bình thường (user vẫn thấy app),
 * chỉ khác HTTP status. is_404 được tắt để theme load index.php chứ không
 * rơi vào 404 handler của WP.
 */
function oscar_gone(): void
{
    global $wp_query;
    nocache_headers();
    if ($wp_query instanceof WP_Query) {
        $wp_query->is_404 = false;
    }
    // URL WC (/shop/, /product-category/…) sẽ rơi vào template archive-product.php của
    // WooCommerce — trang trắng, không có <div id="root">. Ép về index.php (SPA shell)
    // để user vào link cũ vẫn thấy app; Google chỉ thấy status 410.
    add_filter('template_include', static function (): string {
        return get_template_directory() . '/index.php';
    }, 99);
    status_header(410);
}

function oscar_redirect_product_to_canonical(): void
{
    $post_id  = (int) get_queried_object_id();
    $oscar_id = get_post_meta($post_id, '_oscar_source_id', true);

    if ($oscar_id) {
        $post_slug = (string) get_post_field('post_name', $post_id);
        $canonical = home_url('/san-pham/' . $post_slug . '-p' . $oscar_id . '/');
        nocache_headers();
        wp_safe_redirect($canonical, 301);
        exit;
    }

    // Product tồn tại nhưng thiếu Oscar source_id → không redirect canonical được
    oscar_gone();
}

/**
 * Canonical slug (WP post_name) cho một OSCAR source id.
 * Dùng để 301 URL có slug sai về đúng canonical URL.
 */
function oscar_get_canonical_slug(int $oscar_id): string
{
    if ($oscar_id <= 0) {
        return '';
    }
    $cache_key = 'oscanon_slug_' . $oscar_id;
    $cached    = get_transient($cache_key);
    if ($cached !== false) {
        return (string) $cached;
    }
    $posts = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => '_oscar_source_id',
                'value'   => (string) $oscar_id,
                'compare' => '=',
            ],
        ],
    ]);
    $slug = '';
    if ($posts) {
        $slug = (string) get_post_field('post_name', (int) $posts[0]);
    }
    set_transient($cache_key, $slug, DAY_IN_SECONDS);
    return $slug;
}

function oscar_is_valid_oscar_id(int $oscar_id): bool
{
    if ($oscar_id <= 0) {
        return false;
    }

    $cache_key = 'oscar_valid_id_' . $oscar_id;
    $cached    = get_transient($cache_key);

    if ($cached === 'YES') {
        return true;
    }
    if ($cached === 'NO') {
        return false;
    }

    $posts = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => [
            [
                'key'     => '_oscar_source_id',
                'value'   => (string) $oscar_id,
                'compare' => '=',
            ],
        ],
    ]);

    $valid = !empty($posts);
    set_transient($cache_key, $valid ? 'YES' : 'NO', DAY_IN_SECONDS);

    return $valid;
}
