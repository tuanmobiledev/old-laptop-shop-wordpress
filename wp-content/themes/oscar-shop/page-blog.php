<?php
/**
 * Template Name: Blog Landing
 *
 * Lists latest blog posts in a card grid that matches SPA homepage style.
 * URL: /blog/
 *
 * @package Oscar_Shop
 */

get_header(); ?>

<a class="oscar-skip-link" href="#oscar-content">Bỏ qua đến nội dung</a>

<main id="primary" class="oscar-blog-main" role="main">
  <div class="oscar-shell">

    <nav class="oscar-breadcrumb" aria-label="Breadcrumb">
      <ol itemscope itemtype="https://schema.org/BreadcrumbList">
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
          <a itemprop="item" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span itemprop="name">Trang chủ</span></a>
          <meta itemprop="position" content="1" />
        </li>
        <li class="sep" aria-hidden="true">›</li>
        <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" aria-current="page">
          <span itemprop="name">Bài viết</span>
          <meta itemprop="position" content="2" />
        </li>
      </ol>
    </nav>

    <header class="oscar-blog-header">
      <span class="oscar-cat-badge">OSCAR Blog</span>
      <h1 class="oscar-blog-title">Bài viết &amp; Đánh giá</h1>
      <p class="oscar-blog-lead">Chia sẻ kinh nghiệm, đánh giá laptop đồ họa, workstation Dell/HP/Lenovo và mẹo sửa chữa hữu ích từ đội ngũ OSCAR Thủ Đức.</p>
    </header>

    <div id="oscar-content" class="oscar-blog-grid" itemscope itemtype="https://schema.org/Blog">

      <?php
      $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
      $blog_query = new WP_Query( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'paged'          => $paged,
        'posts_per_page' => 12,
      ) );

      if ( $blog_query->have_posts() ) :
        while ( $blog_query->have_posts() ) : $blog_query->the_post(); ?>

          <a class="oscar-blog-card" href="<?php the_permalink(); ?>" aria-label="Đọc: <?php echo esc_attr( get_the_title() ); ?>" itemprop="blogPost" itemscope itemtype="https://schema.org/BlogPosting">
            <meta itemprop="author" content="Laptop OSCAR Thủ Đức" />
            <time itemprop="datePublished" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="visually-hidden"><?php echo esc_html( get_the_date() ); ?></time>

            <?php if ( has_post_thumbnail() ) : ?>
              <div class="oscar-blog-thumb"><?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy', 'itemprop' => 'image' ) ); ?></div>
            <?php else : ?>
              <div class="oscar-blog-thumb oscar-blog-thumb-placeholder" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
              </div>
            <?php endif; ?>

            <div class="oscar-blog-body">
              <?php
              $rcats = get_the_category();
              if ( ! empty( $rcats ) ) : ?>
                <span class="oscar-blog-cat"><?php echo esc_html( $rcats[0]->name ); ?></span>
              <?php endif; ?>

              <h2 itemprop="headline"><?php the_title(); ?></h2>

              <?php if ( has_excerpt() ) : ?>
                <p itemprop="description" class="oscar-blog-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22, '…' ) ); ?></p>
              <?php endif; ?>

              <div class="oscar-blog-meta">
                <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                <?php
                $content_for_reading = wp_strip_all_tags( strip_shortcodes( get_the_content() ) );
                $word_count = str_word_count( $content_for_reading, 0, "àáảãạâầấẩẫậăằắẳẵặèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđÀÁẢÃẠÂẦẤẨẪẬĂẰẮẲẴẶÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰỲÝỶỸỴĐ" );
                $reading_min = max( 1, (int) ceil( $word_count / 200 ) );
                ?>
                <span class="sep" aria-hidden="true">•</span>
                <span><?php echo esc_html( $reading_min ); ?> phút đọc</span>
                <span class="oscar-blog-arrow" aria-hidden="true">→</span>
              </div>
            </div>
          </a>

        <?php endwhile;
        wp_reset_postdata();
      endif; ?>

    </div>

    <?php
    // Pagination
    $total_pages = $blog_query->max_num_pages;
    if ( $total_pages > 1 ) : ?>
      <nav class="oscar-blog-pagination" aria-label="Phân trang">
        <?php
        $current = max( 1, get_query_var( 'paged' ) );
        echo paginate_links( array(
          'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
          'format'    => '?paged=%#%',
          'current'   => $current,
          'total'     => $total_pages,
          'prev_text' => '← Trước',
          'next_text' => 'Sau →',
          'mid_size'  => 1,
          'end_size'  => 1,
        ) );
        ?>
      </nav>
    <?php endif; ?>

  </div>
</main>

<style id="oscar-blog-css">
/* ====== Skip link (a11y) ====== */
.oscar-skip-link{
  position:absolute;top:-100px;left:16px;z-index:9999;
  background:var(--oscar-orange-500,#f15a24);color:#fff;
  padding:12px 20px;border-radius:0 0 12px 12px;
  font-weight:600;font-size:14px;text-decoration:none;
  transition:top 180ms cubic-bezier(.4,0,.2,1);
}
.oscar-skip-link:focus,.oscar-skip-link:focus-visible{top:0;outline:2px solid #fff;outline-offset:2px}

/* ====== Focus ring ====== */
.oscar-blog-main :focus-visible{outline:2px solid var(--oscar-orange-500,#f15a24);outline-offset:2px;border-radius:4px}

/* ====== Hide screen-reader-only ====== */
.visually-hidden{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}

/* ====== Layout ====== */
.oscar-blog-main{padding:24px 0 96px;background:var(--oscar-surface,#fff);min-height:60vh}
.oscar-shell{width:min(1180px,100% - 32px);margin:0 auto}

/* ====== Breadcrumb ====== */
.oscar-blog-main .oscar-breadcrumb{margin-bottom:20px;font-size:13px;line-height:1.5}
.oscar-blog-main .oscar-breadcrumb ol{list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.oscar-blog-main .oscar-breadcrumb li{display:inline-flex;align-items:center;color:var(--oscar-ink-500,#64748b)}
.oscar-blog-main .oscar-breadcrumb a{color:var(--oscar-ink-700,#334155);text-decoration:none;transition:color 150ms ease-out}
.oscar-blog-main .oscar-breadcrumb a:hover{color:var(--oscar-orange-700,#c2410c);text-decoration:underline;text-underline-offset:3px}
.oscar-blog-main .oscar-breadcrumb .sep{color:var(--oscar-ink-400,#94a3b8)}
.oscar-blog-main .oscar-breadcrumb li[aria-current="page"]{color:var(--oscar-ink-900,#0f172a);font-weight:600}

/* ====== Header ====== */
.oscar-blog-header{max-width:760px;margin:0 auto 40px;text-align:center;padding:24px 0 32px}
.oscar-blog-header .oscar-cat-badge{display:inline-block;font-size:12px;font-weight:700;color:var(--oscar-orange-700,#c2410c);background:var(--oscar-orange-50,#fff5ec);padding:5px 12px;border-radius:9999px;letter-spacing:.04em;text-transform:uppercase;margin-bottom:16px}
.oscar-blog-title{font-family:"IBM Plex Sans",sans-serif;font-size:38px;font-weight:700;color:var(--oscar-ink-900,#0f172a);margin:0 0 14px;line-height:1.18;letter-spacing:-.015em}
.oscar-blog-lead{font-size:18px;color:var(--oscar-ink-700,#334155);margin:0;line-height:1.6}

/* ====== Grid ====== */
.oscar-blog-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:48px}

/* ====== Card ====== */
.oscar-blog-card{display:flex;flex-direction:column;background:#fff;border-radius:14px;overflow:hidden;border:1px solid var(--oscar-border-soft,#e2e8f0);text-decoration:none;color:inherit;transition:transform 200ms cubic-bezier(.4,0,.2,1),box-shadow 200ms cubic-bezier(.4,0,.2,1),border-color 200ms ease-out;-webkit-tap-highlight-color:transparent}
.oscar-blog-card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(13,24,40,.10);border-color:var(--oscar-orange-500,#f15a24)}
.oscar-blog-thumb{aspect-ratio:16/9;background:var(--oscar-surface-alt,#f8fafc);overflow:hidden}
.oscar-blog-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 400ms cubic-bezier(.4,0,.2,1)}
.oscar-blog-card:hover .oscar-blog-thumb img{transform:scale(1.05)}
.oscar-blog-thumb-placeholder{display:flex;align-items:center;justify-content:center;color:var(--oscar-ink-400,#94a3b8)}
.oscar-blog-thumb-placeholder svg{width:64px;height:64px}

.oscar-blog-body{padding:18px;display:flex;flex-direction:column;flex:1;position:relative}
.oscar-blog-cat{display:inline-block;font-size:11px;font-weight:700;color:var(--oscar-orange-700,#c2410c);letter-spacing:.06em;text-transform:uppercase;margin-bottom:6px}
.oscar-blog-body h2{font-family:"IBM Plex Sans",sans-serif;font-size:18px;font-weight:600;color:var(--oscar-ink-900,#0f172a);margin:0 0 8px;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.oscar-blog-excerpt{font-size:14px;color:var(--oscar-ink-700,#334155);line-height:1.55;margin:0 0 14px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.oscar-blog-meta{display:flex;align-items:center;gap:8px;font-size:12px;color:var(--oscar-ink-500,#64748b);margin-top:auto}
.oscar-blog-meta .sep{color:var(--oscar-ink-400,#94a3b8)}
.oscar-blog-arrow{position:absolute;right:18px;bottom:18px;width:30px;height:30px;border-radius:50%;background:var(--oscar-orange-50,#fff5ec);color:var(--oscar-orange-700,#c2410c);display:inline-flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;transition:background-color 150ms ease-out,color 150ms ease-out,transform 200ms cubic-bezier(.4,0,.2,1)}
.oscar-blog-card:hover .oscar-blog-arrow{background:var(--oscar-orange-500,#f15a24);color:#fff;transform:translateX(3px)}

/* ====== Pagination ====== */
.oscar-blog-pagination{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-top:32px}
.oscar-blog-pagination .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 14px;border-radius:10px;border:1px solid var(--oscar-border,#d9e4ee);color:var(--oscar-ink-700,#334155);font-size:14px;font-weight:600;text-decoration:none;transition:background-color 150ms ease-out,color 150ms ease-out,border-color 150ms ease-out}
.oscar-blog-pagination .page-numbers.current{background:var(--oscar-orange-500,#f15a24);color:#fff;border-color:var(--oscar-orange-500,#f15a24)}
.oscar-blog-pagination .page-numbers:hover:not(.current){background:var(--oscar-orange-50,#fff5ec);border-color:var(--oscar-orange-500,#f15a24);color:var(--oscar-orange-700,#c2410c)}

/* ====== Reduced motion ====== */
@media (prefers-reduced-motion:reduce){*,::before,::after{animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}

/* ====== Responsive ====== */
@media (max-width:980px){.oscar-blog-grid{grid-template-columns:repeat(3,1fr);gap:16px}}
@media (max-width:680px){
  .oscar-blog-grid{grid-template-columns:repeat(2,1fr);gap:14px}
  .oscar-blog-title{font-size:28px}
  .oscar-blog-lead{font-size:16px}
  .oscar-blog-body{padding:14px}
  .oscar-blog-body h2{font-size:16px}
}
@media (max-width:480px){
  .oscar-blog-grid{grid-template-columns:1fr}
}
</style>

<?php get_footer(); ?>
