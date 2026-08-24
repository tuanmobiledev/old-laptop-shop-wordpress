<?php
/**
 * Single Post Template — renders full blog content with site header/footer.
 * Created 2026-08-22 to fix SPA modal-only display (now redirects to permalink).
 * Polished 2026-08-22 with Oscar Shop Design System (ui-ux-pro-max skill):
 *   - Readable body (18px, line-height 1.7, max-width 65ch)
 *   - A11y foundation (skip-link, focus ring, aria-current, semantic markup)
 *   - Reading progress bar (top sticky)
 *   - Token-driven colors (Oscar orange palette)
 *   - Better article meta + author card with 2 CTAs
 *
 * @package Oscar_Shop
 */

get_header(); ?>

<!-- Skip link (a11y) -->
<a class="oscar-skip-link" href="#oscar-content">Bỏ qua đến nội dung</a>

<!-- Reading progress bar (a11y hidden, decorative) -->
<div class="oscar-reading-progress" aria-hidden="true">
  <div class="oscar-reading-progress-bar" id="oscar-progress-bar"></div>
</div>

<main id="primary" class="oscar-single-main" role="main">
  <div class="oscar-shell">
    <?php while ( have_posts() ) : the_post(); ?>

      <nav class="oscar-breadcrumb" aria-label="Breadcrumb">
        <ol itemscope itemtype="https://schema.org/BreadcrumbList">
          <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a itemprop="item" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span itemprop="name">Trang chủ</span></a>
            <meta itemprop="position" content="1" />
          </li>
          <li class="sep" aria-hidden="true">›</li>
          <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
            <a itemprop="item" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>"><span itemprop="name">Bài viết</span></a>
            <meta itemprop="position" content="2" />
          </li>
          <li class="sep" aria-hidden="true">›</li>
          <li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" aria-current="page">
            <span itemprop="name"><?php the_title(); ?></span>
            <meta itemprop="position" content="3" />
          </li>
        </ol>
      </nav>

      <article id="post-<?php the_ID(); ?>" <?php post_class( 'oscar-single-article' ); ?> itemscope itemtype="https://schema.org/Article">
        <meta itemprop="author" content="Laptop OSCAR Thủ Đức" />
        <meta itemprop="datePublished" content="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" />
        <meta itemprop="dateModified" content="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>" />

        <header class="oscar-single-header">
          <?php
          $cats = get_the_category();
          if ( ! empty( $cats ) ) : ?>
            <a class="oscar-cat-badge" href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">
              <?php echo esc_html( $cats[0]->name ); ?>
            </a>
          <?php endif; ?>

          <h1 class="oscar-single-title" itemprop="headline"><?php the_title(); ?></h1>

          <?php if ( has_excerpt() ) : ?>
            <p class="oscar-single-lead" itemprop="description"><?php echo esc_html( get_the_excerpt() ); ?></p>
          <?php endif; ?>

          <div class="oscar-single-meta">
            <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" itemprop="datePublished">
              <?php echo esc_html( get_the_date() ); ?>
            </time>
            <span class="sep" aria-hidden="true">•</span>
            <span class="oscar-author">
              Đăng bởi <strong itemprop="author">Laptop OSCAR Thủ Đức</strong>
            </span>
            <?php if ( has_category() ) : ?>
              <span class="sep" aria-hidden="true">•</span>
              <span class="oscar-cats"><?php the_category( ', ' ); ?></span>
            <?php endif; ?>
            <?php
            // Reading time: ~200 words/min for Vietnamese text
            $content_for_reading = wp_strip_all_tags( strip_shortcodes( get_the_content() ) );
            $word_count = str_word_count( $content_for_reading, 0, "àáảãạâầấẩẫậăằắẳẵặèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđÀÁẢÃẠÂẦẤẨẪẬĂẰẮẲẴẶÈÉẺẼẸÊỀẾỂỄỆÌÍỈĨỊÒÓỎÕỌÔỒỐỔỖỘƠỜỚỞỠỢÙÚỦŨỤƯỪỨỬỮỰỲÝỶỸỴĐ" );
            $reading_min = max( 1, (int) ceil( $word_count / 200 ) );
            ?>
            <span class="sep" aria-hidden="true">•</span>
            <span class="oscar-reading" aria-label="Thời gian đọc">
              <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              <?php echo esc_html( $reading_min ); ?> phút đọc
            </span>
            <?php if ( function_exists( 'get_post_views' ) && get_post_views() ) : ?>
              <span class="sep" aria-hidden="true">•</span>
              <span class="oscar-views" aria-label="Lượt xem">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php echo number_format_i18n( (int) get_post_views() ); ?> lượt xem
              </span>
            <?php endif; ?>
          </div>
        </header>

        <?php if ( has_post_thumbnail() ) : ?>
          <figure class="oscar-single-featured">
            <?php the_post_thumbnail( 'large', array( 'loading' => 'eager', 'fetchpriority' => 'high', 'itemprop' => 'image' ) ); ?>
            <?php if ( get_the_post_thumbnail_caption() ) : ?>
              <figcaption><?php echo esc_html( get_the_post_thumbnail_caption() ); ?></figcaption>
            <?php endif; ?>
          </figure>
        <?php endif; ?>

        <div id="oscar-content" class="oscar-entry-content oscar-prose" itemprop="articleBody">
          <?php
          the_content();

          wp_link_pages( array(
            'before' => '<nav class="oscar-page-links" aria-label="Phân trang">' . esc_html__( 'Trang:', 'oscar-shop' ),
            'after'  => '</nav>',
          ) );
          ?>
        </div>

        <section class="oscar-single-article-end" aria-label="Thông tin bài viết">
          <?php if ( has_tag() ) : ?>
            <div class="oscar-tags">
              <span class="oscar-tags-label">Tags:</span>
              <?php the_tags( '', ' ', '' ); ?>
            </div>
          <?php endif; ?>

          <div class="oscar-author-card" itemprop="author" itemscope itemtype="https://schema.org/Person">
            <div class="oscar-author-avatar">
              <?php echo get_avatar( get_the_author_meta( 'ID' ), 80 ); ?>
            </div>
            <div class="oscar-author-info">
              <strong itemprop="name">Laptop OSCAR Thủ Đức</strong>
              <p itemprop="description">Đội ngũ kỹ thuật OSCAR — chuyên laptop đồ họa, workstation Dell/HP/Lenovo và dịch vụ sửa chữa chuyên nghiệp tại TP.HCM. Hotline: <a href="tel:0984496260">0984.496.260</a>.</p>
              <div class="oscar-author-cta-group">
                <a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>" class="oscar-author-cta oscar-author-cta-primary">Liên hệ tư vấn →</a>
                <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="oscar-author-cta oscar-author-cta-secondary">Xem tất cả bài viết</a>
              </div>
            </div>
          </div>

          <div class="oscar-back-link">
            <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>">
              <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
              Quay lại danh sách bài viết
            </a>
          </div>
        </section>
      </article>

      <?php
      // Related posts: same category, exclude current
      $cats = wp_get_post_categories( get_the_ID() );
      if ( ! empty( $cats ) ) :
        $related = new WP_Query( array(
          'category__in'   => $cats,
          'post__not_in'   => array( get_the_ID() ),
          'posts_per_page' => 6,
          'ignore_sticky_posts' => 1,
        ) );
        if ( $related->have_posts() ) : ?>
          <section class="oscar-related" aria-labelledby="oscar-related-heading">
            <h2 id="oscar-related-heading">
              <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
              Bài viết liên quan
            </h2>
            <div class="oscar-related-grid">
              <?php while ( $related->have_posts() ) : $related->the_post(); ?>
                <a class="oscar-related-card" href="<?php the_permalink(); ?>" aria-label="Đọc: <?php echo esc_attr( get_the_title() ); ?>">
                  <?php if ( has_post_thumbnail() ) : ?>
                    <div class="oscar-related-thumb"><?php the_post_thumbnail( 'medium', array( 'loading' => 'lazy' ) ); ?></div>
                  <?php else : ?>
                    <div class="oscar-related-thumb oscar-related-thumb-placeholder" aria-hidden="true">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                    </div>
                  <?php endif; ?>
                  <div class="oscar-related-body">
                    <?php
                    $rcats = get_the_category();
                    if ( ! empty( $rcats ) ) : ?>
                      <span class="oscar-related-cat"><?php echo esc_html( $rcats[0]->name ); ?></span>
                    <?php endif; ?>
                    <h3><?php the_title(); ?></h3>
                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                    <span class="oscar-related-arrow" aria-hidden="true">→</span>
                  </div>
                </a>
              <?php endwhile; wp_reset_postdata(); ?>
            </div>
          </section>
        <?php endif;
      endif; ?>

    <?php endwhile; ?>
  </div>
</main>

<style id="oscar-single-css">
/* ====== Skip link (a11y) ====== */
.oscar-skip-link{
  position:absolute;top:-100px;left:16px;z-index:9999;
  background:var(--oscar-orange-500,#f15a24);color:#fff;
  padding:12px 20px;border-radius:0 0 12px 12px;
  font-weight:600;font-size:14px;text-decoration:none;
  transition:top 180ms cubic-bezier(.4,0,.2,1);
}
.oscar-skip-link:focus,.oscar-skip-link:focus-visible{top:0;outline:2px solid #fff;outline-offset:2px}

/* ====== Focus ring (a11y) ====== */
.oscar-single-main :focus-visible{
  outline:2px solid var(--oscar-orange-500,#f15a24);
  outline-offset:2px;border-radius:4px;
}

/* ====== Reading progress bar ====== */
.oscar-reading-progress{
  position:sticky;top:0;left:0;width:100%;height:3px;
  background:transparent;z-index:50;pointer-events:none;
}
.oscar-reading-progress-bar{
  height:100%;width:0;
  background:linear-gradient(90deg,var(--oscar-orange-500,#f15a24),var(--oscar-orange-700,#c2410c));
  transition:width 80ms linear;
}

/* ====== Single Post Layout ====== */
.oscar-single-main{
  padding:24px 0 96px;
  background:var(--oscar-surface,#fff);
  min-height:60vh;
}
.oscar-shell{
  width:min(1180px,100% - 32px);margin:0 auto;
}

/* ====== Breadcrumb ====== */
.oscar-breadcrumb{margin-bottom:20px;font-size:13px;line-height:1.5}
.oscar-breadcrumb ol{list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.oscar-breadcrumb li{display:inline-flex;align-items:center;color:var(--oscar-ink-500,#64748b)}
.oscar-breadcrumb a{color:var(--oscar-ink-700,#334155);text-decoration:none;transition:color 150ms ease-out}
.oscar-breadcrumb a:hover{color:var(--oscar-orange-700,#c2410c);text-decoration:underline;text-underline-offset:3px}
.oscar-breadcrumb .sep{color:var(--oscar-ink-400,#94a3b8)}
.oscar-breadcrumb li[aria-current="page"]{color:var(--oscar-ink-900,#0f172a);font-weight:600;max-width:520px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

/* ====== Article container ====== */
.oscar-single-article{
  background:#fff;border-radius:16px;padding:40px 48px;
  box-shadow:0 4px 16px rgba(13,24,40,.06);
  border:1px solid var(--oscar-border-soft,#e2e8f0);
  margin-bottom:40px;
}
.oscar-single-header{margin-bottom:28px;padding-bottom:24px;border-bottom:1px solid var(--oscar-border-soft,#e2e8f0)}

/* ====== Category badge ====== */
.oscar-cat-badge{
  display:inline-block;background:var(--oscar-orange-50,#fff5ec);
  color:var(--oscar-orange-700,#c2410c);
  padding:5px 12px;border-radius:9999px;
  font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;
  text-decoration:none;margin-bottom:14px;
  transition:background-color 150ms ease-out,color 150ms ease-out;
}
.oscar-cat-badge:hover{background:var(--oscar-orange-500,#f15a24);color:#fff}

/* ====== Title ====== */
.oscar-single-title{
  font-family:"IBM Plex Sans",sans-serif;
  font-size:38px;font-weight:700;color:var(--oscar-ink-900,#0f172a);
  margin:0 0 14px;line-height:1.18;letter-spacing:-.015em;
  max-width:22ch;
}
.oscar-single-lead{
  font-size:20px;font-weight:400;color:var(--oscar-ink-700,#334155);
  line-height:1.55;margin:0 0 20px;max-width:60ch;
}

/* ====== Meta ====== */
.oscar-single-meta{
  display:flex;align-items:center;gap:10px;font-size:13px;
  color:var(--oscar-ink-500,#64748b);flex-wrap:wrap;margin-top:8px;
}
.oscar-single-meta strong{color:var(--oscar-ink-900,#0f172a);font-weight:600}
.oscar-single-meta .sep{color:var(--oscar-ink-400,#94a3b8)}
.oscar-single-meta .oscar-reading,
.oscar-single-meta .oscar-views{display:inline-flex;align-items:center;gap:5px}

/* ====== Featured image ====== */
.oscar-single-featured{margin:0 0 32px;border-radius:14px;overflow:hidden;background:var(--oscar-surface-alt,#f8fafc)}
.oscar-single-featured img{width:100%;height:auto;display:block}
.oscar-single-featured figcaption{font-size:13px;color:var(--oscar-ink-500,#64748b);text-align:center;padding:10px 0 0;font-style:italic}

/* ====== Prose (article body) ====== */
.oscar-prose{
  font-family:"IBM Plex Sans",sans-serif;
  font-size:18px;line-height:1.75;
  color:var(--oscar-ink-900,#0f172a);
  max-width:65ch;
  word-break:break-word;overflow-wrap:anywhere;
  hyphens:auto;
}
.oscar-prose p{margin:0 0 1.2em}
.oscar-prose h2,.oscar-prose h3,.oscar-prose h4{
  font-family:"IBM Plex Sans",sans-serif;
  scroll-margin-top:120px;
}
.oscar-prose h2[id]::before,.oscar-prose h3[id]::before{
  content:"#";color:var(--oscar-orange-500,#f15a24);opacity:0;
  margin-right:8px;transition:opacity 150ms ease-out;
}
.oscar-prose h2[id]:hover::before,.oscar-prose h3[id]:hover::before{opacity:.6}
.oscar-prose h2{
  font-size:30px;font-weight:700;color:var(--oscar-ink-900,#0f172a);
  margin:2em 0 .6em;line-height:1.25;letter-spacing:-.01em;
  padding-top:.4em;border-top:1px solid var(--oscar-border-soft,#e2e8f0);
}
.oscar-prose h3{font-size:24px;font-weight:600;margin:1.6em 0 .5em;line-height:1.3;color:var(--oscar-ink-900,#0f172a)}
.oscar-prose h4{font-size:20px;font-weight:600;margin:1.3em 0 .4em;color:var(--oscar-ink-900,#0f172a)}
.oscar-prose a{
  color:var(--oscar-orange-700,#c2410c);
  text-decoration:underline;text-underline-offset:3px;text-decoration-thickness:1px;
  transition:color 150ms ease-out,text-decoration-thickness 150ms ease-out;
}
.oscar-prose a:hover{color:var(--oscar-orange-900,#7c2d12);text-decoration-thickness:2px}
.oscar-prose ul,.oscar-prose ol{margin:0 0 1.2em;padding-left:1.5em}
.oscar-prose li{margin-bottom:.4em}
.oscar-prose li::marker{color:var(--oscar-orange-500,#f15a24)}
.oscar-prose strong{font-weight:600;color:var(--oscar-ink-900,#0f172a)}
.oscar-prose em{font-style:italic}
.oscar-prose blockquote{
  margin:1.5em 0;padding:1em 1.25em;
  border-left:4px solid var(--oscar-orange-500,#f15a24);
  background:var(--oscar-orange-50,#fff5ec);
  border-radius:0 12px 12px 0;font-style:italic;color:var(--oscar-ink-700,#334155);
}
.oscar-prose blockquote p:last-child{margin-bottom:0}
.oscar-prose code{
  font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace;
  font-size:.92em;padding:2px 6px;
  background:var(--oscar-surface-alt,#f8fafc);
  border:1px solid var(--oscar-border-soft,#e2e8f0);
  border-radius:6px;color:var(--oscar-orange-900,#7c2d12);
}
.oscar-prose pre{
  background:var(--oscar-ink-900,#0f172a);color:#e2e8f0;
  padding:1.25em;border-radius:12px;overflow-x:auto;margin:0 0 1.4em;
  font-size:.9rem;line-height:1.6;
}
.oscar-prose pre code{background:transparent;border:0;padding:0;color:inherit}
.oscar-prose img{
  max-width:100%;height:auto;border-radius:12px;
  margin:1.5em auto;display:block;
  box-shadow:0 4px 14px rgba(13,24,40,.08);
}
.oscar-prose figure{margin:1.5em 0}
.oscar-prose figcaption{text-align:center;font-size:13px;color:var(--oscar-ink-500,#64748b);margin-top:.5em;font-style:italic}
.oscar-prose table{
  width:100%;border-collapse:collapse;margin:1.5em 0;font-size:.95rem;
  background:var(--oscar-surface,#fff);border-radius:8px;overflow:hidden;
}
.oscar-prose th{
  text-align:left;padding:12px 16px;
  background:var(--oscar-surface-alt,#f8fafc);
  border-bottom:2px solid var(--oscar-border,#d9e4ee);
  font-weight:600;color:var(--oscar-ink-900,#0f172a);
}
.oscar-prose td{padding:12px 16px;border-bottom:1px solid var(--oscar-border-soft,#e2e8f0);color:var(--oscar-ink-700,#334155)}
.oscar-prose tr:last-child td{border-bottom:0}
.oscar-prose hr{border:0;height:1px;background:var(--oscar-border-soft,#e2e8f0);margin:2.5em 0}
.oscar-page-links{margin:1.5em 0;padding:1em;background:var(--oscar-surface-alt,#f8fafc);border-radius:8px;font-size:14px;display:flex;gap:8px;flex-wrap:wrap}

/* ====== Single article end (tags + author card + back link) ====== */
.oscar-single-article-end{margin-top:40px;padding-top:32px;border-top:1px solid var(--oscar-border-soft,#e2e8f0)}
.oscar-tags{margin-bottom:32px;font-size:13px;color:var(--oscar-ink-500,#64748b)}
.oscar-tags-label{font-weight:700;margin-right:8px;color:var(--oscar-ink-700,#334155)}
.oscar-tags a{
  display:inline-block;background:var(--oscar-surface-alt,#f8fafc);
  padding:6px 12px;border-radius:999px;margin:0 6px 6px 0;
  font-size:12px;color:var(--oscar-ink-700,#334155);text-decoration:none;
  border:1px solid var(--oscar-border-soft,#e2e8f0);
  transition:background-color 150ms ease-out,color 150ms ease-out,border-color 150ms ease-out;
}
.oscar-tags a:hover{background:var(--oscar-orange-500,#f15a24);color:#fff;border-color:var(--oscar-orange-500,#f15a24)}

/* ====== Author card ====== */
.oscar-author-card{
  display:flex;gap:20px;align-items:flex-start;
  background:var(--oscar-orange-50,#fff5ec);
  border:1px solid var(--oscar-orange-100,#ffe0b6);
  padding:24px;border-radius:14px;margin-bottom:32px;
}
.oscar-author-avatar img{border-radius:50%;display:block;width:80px;height:80px;border:3px solid #fff;box-shadow:0 2px 8px rgba(13,24,40,.08)}
.oscar-author-info{flex:1;min-width:0}
.oscar-author-info strong{display:block;font-size:18px;color:var(--oscar-ink-900,#0f172a);margin-bottom:6px;font-weight:700}
.oscar-author-info p{margin:0 0 14px;font-size:14px;color:var(--oscar-ink-700,#334155);line-height:1.6}
.oscar-author-info p a{color:var(--oscar-orange-700,#c2410c);text-decoration:none;font-weight:600}
.oscar-author-info p a:hover{text-decoration:underline}
.oscar-author-cta-group{display:flex;flex-wrap:wrap;gap:10px;margin-top:8px}
.oscar-author-cta{
  display:inline-flex;align-items:center;justify-content:center;
  min-height:44px;padding:0 20px;border-radius:9999px;
  font-size:14px;font-weight:600;text-decoration:none;
  transition:background-color 150ms ease-out,color 150ms ease-out,transform 150ms ease-out;
  -webkit-tap-highlight-color:transparent;
}
.oscar-author-cta-primary{background:var(--oscar-orange-500,#f15a24);color:#fff}
.oscar-author-cta-primary:hover{background:var(--oscar-orange-700,#c2410c)}
.oscar-author-cta-secondary{background:#fff;color:var(--oscar-orange-700,#c2410c);border:2px solid var(--oscar-orange-500,#f15a24)}
.oscar-author-cta-secondary:hover{background:var(--oscar-orange-50,#fff5ec)}
.oscar-author-cta:active{transform:scale(.97)}

/* ====== Back link ====== */
.oscar-back-link{padding:24px 0 0;text-align:center;border-top:1px solid var(--oscar-border-soft,#e2e8f0);margin-top:24px}
.oscar-back-link a{
  display:inline-flex;align-items:center;gap:8px;
  min-height:44px;padding:0 24px;border-radius:9999px;
  font-weight:600;font-size:14px;color:var(--oscar-orange-700,#c2410c);
  text-decoration:none;border:1px solid var(--oscar-border,#d9e4ee);
  transition:background-color 150ms ease-out,color 150ms ease-out,border-color 150ms ease-out;
}
.oscar-back-link a:hover{background:var(--oscar-orange-500,#f15a24);color:#fff;border-color:var(--oscar-orange-500,#f15a24)}
.oscar-back-link a svg{transition:transform 200ms cubic-bezier(.4,0,.2,1)}
.oscar-back-link a:hover svg{transform:translateX(-3px)}

/* ====== Related posts ====== */
.oscar-related{margin-top:48px}
.oscar-related h2{
  font-family:"IBM Plex Sans",sans-serif;
  font-size:28px;font-weight:700;color:var(--oscar-ink-900,#0f172a);
  margin:0 0 24px;line-height:1.25;letter-spacing:-.01em;
  display:flex;align-items:center;gap:10px;
}
.oscar-related h2 svg{color:var(--oscar-orange-500,#f15a24)}
.oscar-related-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.oscar-related-card{max-width:280px}
.oscar-related-card{
  display:flex;flex-direction:column;
  background:#fff;border-radius:12px;overflow:hidden;
  border:1px solid var(--oscar-border-soft,#e2e8f0);
  text-decoration:none;color:inherit;
  transition:transform 200ms cubic-bezier(.4,0,.2,1),box-shadow 200ms cubic-bezier(.4,0,.2,1),border-color 200ms ease-out;
  -webkit-tap-highlight-color:transparent;
}
.oscar-related-card:hover{
  transform:translateY(-3px);
  box-shadow:0 12px 28px rgba(13,24,40,.10);
  border-color:var(--oscar-orange-500,#f15a24);
}
.oscar-related-thumb{aspect-ratio:4/3;background:var(--oscar-surface-alt,#f8fafc);overflow:hidden}
.oscar-related-thumb img{width:100%;height:100%;object-fit:cover;display:block;transition:transform 400ms cubic-bezier(.4,0,.2,1)}
.oscar-related-card:hover .oscar-related-thumb img{transform:scale(1.05)}
.oscar-related-thumb-placeholder{display:flex;align-items:center;justify-content:center;color:var(--oscar-ink-400,#94a3b8)}
.oscar-related-thumb-placeholder svg{width:36px;height:36px}
.oscar-related-body{padding:12px;display:flex;flex-direction:column;flex:1;position:relative}
.oscar-related-cat{
  display:inline-block;font-size:10px;font-weight:700;
  color:var(--oscar-orange-700,#c2410c);letter-spacing:.06em;text-transform:uppercase;
  margin-bottom:4px;
}
.oscar-related-body h3{
  font-family:"IBM Plex Sans",sans-serif;
  font-size:14px;font-weight:600;color:var(--oscar-ink-900,#0f172a);
  margin:0 0 4px;line-height:1.4;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;
}
.oscar-related-body time{font-size:12px;color:var(--oscar-ink-500,#64748b)}
.oscar-related-arrow{
  position:absolute;right:12px;bottom:12px;
  width:26px;height:26px;border-radius:50%;
  background:var(--oscar-orange-50,#fff5ec);color:var(--oscar-orange-700,#c2410c);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:13px;font-weight:600;
  transition:background-color 150ms ease-out,color 150ms ease-out,transform 200ms cubic-bezier(.4,0,.2,1);
}
.oscar-related-card:hover .oscar-related-arrow{background:var(--oscar-orange-500,#f15a24);color:#fff;transform:translateX(3px)}

/* ====== Reduced motion ====== */
@media (prefers-reduced-motion:reduce){
  *,::before,::after{animation-duration:.01ms!important;transition-duration:.01ms!important;scroll-behavior:auto!important}
}

/* ====== Responsive: tablet ====== */
@media (max-width:980px){
  .oscar-single-article{padding:32px 28px;border-radius:14px}
  .oscar-related-grid{grid-template-columns:repeat(3,1fr)}
}

@media (max-width:680px){
  .oscar-related-grid{grid-template-columns:repeat(2,1fr)}
}

/* ====== Responsive: mobile ====== */
@media (max-width:768px){
  .oscar-single-article{padding:24px 22px}
  .oscar-single-title{font-size:28px;letter-spacing:-.01em}
  .oscar-single-lead{font-size:17px}
  .oscar-prose{font-size:17px;line-height:1.7}
  .oscar-prose h2{font-size:24px;margin:1.6em 0 .5em;padding-top:.5em}
  .oscar-prose h3{font-size:20px;margin:1.3em 0 .4em}
  .oscar-prose h4{font-size:17px}
  .oscar-prose blockquote{margin:1.2em 0;padding:.9em 1em}
  .oscar-prose table{font-size:.85rem}
  .oscar-prose th,.oscar-prose td{padding:8px 10px}
  .oscar-author-card{flex-direction:column;text-align:center;align-items:center;gap:14px;padding:20px}
  .oscar-author-cta-group{justify-content:center;width:100%}
  .oscar-author-cta{flex:1;min-width:140px}
  .oscar-related h2{font-size:22px}
  .oscar-related-grid{grid-template-columns:repeat(2,1fr);gap:12px}
  .oscar-back-link a{width:100%;justify-content:center}
}

/* ====== Responsive: small mobile ====== */
@media (max-width:520px){
  .oscar-single-main{padding:16px 0 80px}
  .oscar-shell{width:calc(100% - 20px)}
  .oscar-single-article{padding:20px 18px;border-radius:12px}
  .oscar-single-title{font-size:24px;margin-bottom:10px}
  .oscar-single-lead{font-size:16px}
  .oscar-prose{font-size:16px;line-height:1.65}
  .oscar-prose h2{font-size:21px}
  .oscar-prose h3{font-size:18px}
  .oscar-prose h2[id]::before,.oscar-prose h3[id]::before{display:none}
  .oscar-single-meta{font-size:12px;gap:8px}
  .oscar-author-cta{flex:1;min-width:0}
}
</style>

<script>
/* ====== Reading progress bar ====== */
(function(){
  var bar=document.getElementById('oscar-progress-bar');
  if(!bar)return;
  var ticking=false;
  function update(){
    var h=document.documentElement;
    var b=h.getBoundingClientRect();
    var max=h.scrollHeight - window.innerHeight;
    var pct = max > 0 ? Math.min(100, Math.max(0, (-b.top) / max * 100)) : 0;
    bar.style.width = pct + '%';
    ticking=false;
  }
  function onScroll(){if(!ticking){requestAnimationFrame(update);ticking=true;}}
  window.addEventListener('scroll',onScroll,{passive:true});
  window.addEventListener('resize',update);
  update();
})();

/* ====== Anchor heading IDs ====== */
(function(){
  var hs=document.querySelectorAll('.oscar-prose h2,.oscar-prose h3');
  hs.forEach(function(h){
    if(h.id)return;
    var t=h.textContent||'';
    var slug=t.toLowerCase().trim()
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'')
      .replace(/đ/g,'d').replace(/[^a-z0-9\s-]/g,'')
      .replace(/\s+/g,'-').replace(/-+/g,'-').slice(0,60);
    if(slug)h.id=slug;
  });
})();
</script>

<?php get_footer(); ?>
