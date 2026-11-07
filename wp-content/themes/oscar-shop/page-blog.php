<?php
/**
 * Blog archive — trang thật, render server-side (2026-11-07).
 *
 * Trước đây template này chỉ 301 → /#blog (SPA hash route), nên Google không có
 * trang danh sách bài nào để bò: 146 bài chỉ tồn tại trong sitemap XML → "unknown
 * to Google".
 *
 * Không phân trang: 146 link trên một trang là bình thường với Google, còn
 * /blog/page/N/ thì oscar_whitelist_redirect coi là URL lạ và 302 về `/`.
 *
 * body_class('single-post') là CỐ Ý, không phải copy-paste: React (src/main.jsx,
 * biến isPostDetail) chỉ render <Header /> khi <body> có class này — giống hệt
 * single.php — nên bundle không ghi đè nội dung PHP bên dưới.
 *
 * @package Oscar_Shop
 */
defined('ABSPATH') || exit;

$oscar_posts = get_posts([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
]);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class('oscar-blog-archive single-post'); ?>>
<?php wp_body_open(); ?>
<div id="root"><?php
// Skeleton header cho mạng yếu; React thay bằng <Header /> khi mount.
get_template_part('template-parts/header-business');
?></div>

<main id="primary" class="oscar-single-main" role="main">
  <div class="oscar-shell">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="<?php echo esc_url(home_url('/')); ?>"><span>Trang chủ</span></a>
      <span class="sep" aria-hidden="true">›</span>
      <span aria-current="page">Bài viết</span>
    </nav>

    <h1 class="oscar-single-title">Bài viết công nghệ</h1>
    <p class="oscar-blog-lead">
      <?php echo esc_html(count($oscar_posts)); ?> bài: đánh giá laptop, so sánh cấu hình và mẹo sử dụng máy tại Laptop OSCAR Thủ Đức.
    </p>

    <ul class="oscar-blog-list">
      <?php foreach ($oscar_posts as $p) : ?>
        <li>
          <a href="<?php echo esc_url(get_permalink($p)); ?>"><?php echo esc_html(get_the_title($p)); ?></a>
          <time datetime="<?php echo esc_attr(get_the_date('c', $p)); ?>"><?php echo esc_html(get_the_date('d/m/Y', $p)); ?></time>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</main>

<style>
.oscar-blog-list{list-style:none;margin:24px 0 0;padding:0}
.oscar-blog-list li{display:flex;justify-content:space-between;gap:16px;align-items:baseline;padding:14px 0;border-bottom:1px solid #e2e8f0}
.oscar-blog-list a{color:#0b5eb8;text-decoration:none;font-weight:600;font-size:1.02rem}
.oscar-blog-list a:hover{text-decoration:underline}
.oscar-blog-list time{color:#64748b;font-size:.85rem;white-space:nowrap}
.oscar-blog-lead{color:#475569;margin:8px 0 0}
</style>

<?php get_footer(); ?>
