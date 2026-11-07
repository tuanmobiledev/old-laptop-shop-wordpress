<?php
/**
 * Plugin Name: Oscar HTML Sitemap
 * Description: /tat-ca-trang/ — một trang HTML liệt kê toàn bộ sản phẩm + bài viết.
 *              Trước 2026-11-07 mỗi trang là một ốc đảo: homepage và PDP không có
 *              link nội bộ nào tới /blog/* hay /san-pham/*, Google chỉ biết 246 URL
 *              qua sitemap XML nên 146 bài viết nằm ở trạng thái "unknown to Google".
 * Version: 1.0.0
 * Author: Oscar Dev
 */
defined('ABSPATH') || exit;

add_action('template_redirect', 'oscar_html_sitemap_render', 0);

function oscar_html_sitemap_render(): void
{
    // Priority 0 để chạy TRƯỚC oscar_whitelist_redirect (priority 1):
    // /tat-ca-trang/ không nằm trong whitelist của nó.
    $path = (string) strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    if (rtrim($path, '/') !== '/tat-ca-trang') {
        return;
    }

    $products = get_posts([
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'no_found_rows'  => true,
    ]);
    $posts = get_posts([
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
    ]);
    // 1 query meta cho tất cả sản phẩm thay vì N query lẻ.
    update_meta_cache('post', wp_list_pluck($products, 'ID'));

    $product_links = [];
    foreach ($products as $p) {
        $oscar_id = get_post_meta($p->ID, '_oscar_source_id', true);
        if (!$oscar_id) {
            continue; // không có ID Nhanh thì không có URL canonical
        }
        $product_links[] = [
            home_url('/san-pham/' . $p->post_name . '-p' . $oscar_id . '/'),
            get_the_title($p),
        ];
    }

    $title = 'Tất cả sản phẩm và bài viết | Laptop OSCAR Thủ Đức';
    $desc  = 'Danh mục đầy đủ: ' . count($product_links) . ' sản phẩm laptop và '
           . count($posts) . ' bài viết tại Laptop OSCAR Thủ Đức.';

    status_header(200);
    header('Content-Type: text/html; charset=utf-8');
    nocache_headers();
    ?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($title); ?></title>
<meta name="description" content="<?php echo esc_attr($desc); ?>">
<link rel="canonical" href="<?php echo esc_url(home_url('/tat-ca-trang/')); ?>">
<style>
body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:0;color:#0f172a;background:#fff;line-height:1.6}
.wrap{max-width:960px;margin:0 auto;padding:24px 16px 64px}
h1{font-size:1.6rem;margin:0 0 8px}h2{font-size:1.15rem;margin:32px 0 12px;border-bottom:2px solid #f15a24;padding-bottom:6px}
p.lead{color:#475569;margin:0 0 8px}
ul{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:6px 20px}
li a{color:#0b5eb8;text-decoration:none}li a:hover{text-decoration:underline}
.back{display:inline-block;margin-bottom:16px;color:#f15a24;text-decoration:none;font-weight:600}
</style>
</head>
<body>
<div class="wrap">
<a class="back" href="<?php echo esc_url(home_url('/')); ?>">&larr; Laptop OSCAR Thủ Đức</a>
<h1><?php echo esc_html($title); ?></h1>
<p class="lead"><?php echo esc_html($desc); ?></p>

<h2>Sản phẩm (<?php echo count($product_links); ?>)</h2>
<ul>
<?php foreach ($product_links as [$url, $name]) : ?>
  <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($name); ?></a></li>
<?php endforeach; ?>
</ul>

<h2>Bài viết (<?php echo count($posts); ?>)</h2>
<ul>
<?php foreach ($posts as $p) : ?>
  <li><a href="<?php echo esc_url(home_url('/blog/' . $p->post_name . '/')); ?>"><?php echo esc_html(get_the_title($p)); ?></a></li>
<?php endforeach; ?>
</ul>
</div>
</body>
</html>
    <?php
    exit;
}
