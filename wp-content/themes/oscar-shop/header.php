<?php
/**
 * Header template matching SPA homepage exactly.
 * Uses identical class names (site-header, pro-header, utility, topbar, nav-shell,
 * brand, global-search, header-action, hotline, language-toggle, category-menu,
 * simple-nav) so the SPA's visual design transfers 1:1.
 *
 * @package Oscar_Shop
 */
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="icon" type="image/webp" href="<?php echo esc_url( get_template_directory_uri() . '/assets/images/oscar-avatar.webp' ); ?>">
<?php wp_head(); ?>
<style id="oscar-blog-inline">
:root {
  --ink: #0d1828;
  --text: #0f172a;
  --line: #d9e4ee;
  --blue: #0b5eb8;
  --brand-500: #f15a24;
  --w-content: 1280px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-full: 9999px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-6: 24px;
  --shadow-sm: 0 1px 2px rgba(15,23,42,.05);
  --shadow-md: 0 4px 6px rgba(15,23,42,.08);
  --soft-shadow: 0 14px 38px rgba(13,24,40,.08);
}
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; font-family: "IBM Plex Sans", sans-serif; color: var(--text); background: #fff; }
body { line-height: 1.6; }

/* ===== Header (matches SPA site-header pro-header) ===== */
.site-header.pro-header {
  z-index: 40;
  backdrop-filter: blur(18px);
  -webkit-backdrop-filter: blur(18px);
  border-bottom: 1px solid var(--line);
  background: rgba(255,255,255,.92);
  box-shadow: 0 10px 30px rgba(13,24,40,.05);
  position: sticky; top: 0;
}
.utility {
  color: #eaf1f8;
  background: linear-gradient(90deg, #0d1828, #133251 62%, #0a5c67);
  font-size: .86rem;
}
.utility-inner {
  align-items: center; gap: 22px; min-height: 34px;
  width: min(1180px, 100% - 32px); margin: 0 auto;
  display: flex;
}
.utility-inner span {
  align-items: center; gap: 6px; margin-right: auto;
  display: inline-flex;
}
.utility-inner a {
  color: #eaf1f8; text-decoration: none;
  transition: color .15s;
}
.utility-inner a:hover { color: #fff; }

.topbar { background: rgba(255,255,255,.76); }
.nav-shell {
  align-items: center; gap: 14px; min-height: 72px;
  width: min(1180px, 100% - 32px); margin: 0 auto;
  display: flex;
}

.menu-filter-toggle {
  color: #155f4b; cursor: pointer; background: #eef7f4;
  border: 0; border-radius: 14px;
  justify-content: center; align-items: center;
  width: 42px; height: 42px;
  display: inline-flex;
  flex: 0 0 auto;
}
.menu-filter-toggle:hover { color: #fff; background: #14946f; }

.brand {
  align-items: center; gap: 10px;
  font-family: Archivo, sans-serif; font-size: 1.16rem;
  display: inline-flex; flex: 0 0 auto;
  color: inherit; text-decoration: none;
}
.brand-icon {
  object-fit: cover; border-radius: 14px;
  flex: 0 0 auto; width: 46px; height: 46px;
  box-shadow: 0 14px 30px rgba(16,25,35,.18);
}
.brand strong { display: block; line-height: 1.1; }
.brand small { display: block; font-size: .68rem; font-weight: 600; color: #64748b; letter-spacing: .04em; margin-top: 2px; }

.global-search {
  border: 2px solid var(--blue); background: #fff;
  border-radius: 14px; flex: 1 1 0%;
  align-items: center; gap: 10px;
  min-width: 200px; height: 46px; padding: 0 14px;
  display: flex;
  position: relative;
}
.global-search svg { color: #64748b; flex: 0 0 auto; }
.global-search input {
  border: 0; outline: 0; width: 100%;
  font-family: inherit; font-size: 1rem; background: transparent;
  color: var(--text);
}

.header-action, .language-toggle {
  border: 1px solid var(--line); background: #fff;
  border-radius: 13px; align-items: center; gap: 7px;
  height: 42px; padding: 0 12px; font-weight: 700;
  display: inline-flex;
  font-family: inherit; font-size: 1rem;
  color: inherit; text-decoration: none;
  flex: 0 0 auto;
}
.header-action.hotline { color: var(--blue); }
.language-toggle { color: var(--blue); min-width: 72px; justify-content: center; cursor: pointer; }
.language-flag {
  width: 20px; height: 14px; border-radius: 2px; overflow: hidden;
  display: inline-block; position: relative; flex: 0 0 auto;
}
.flag-vn { background: #da251d; }
.flag-vn::before {
  content: "★"; position: absolute; left: 50%; top: 50%;
  transform: translate(-50%,-50%); color: #ff0; font-size: 10px;
}

.category-menu {
  background: #fff7ec;
  border-top: 1px solid #ffe0b6;
}
.category-menu .shell {
  width: min(1180px, 100% - 32px); margin: 0 auto;
  white-space: nowrap; align-items: center;
  gap: 28px; min-height: 42px;
  font-size: .94rem; font-weight: 700;
  display: flex; overflow-x: auto;
  justify-content: center;
}
.category-menu.simple-nav a {
  color: #0f172a; padding: 14px 8px;
  font-weight: 700; text-decoration: none;
  position: relative;
  transition: color .15s;
}
.category-menu.simple-nav a:hover { color: var(--brand-500); }
.category-menu.simple-nav a.is-active {
  color: var(--brand-500);
}
.category-menu.simple-nav a.is-active::after {
  content: ""; position: absolute; left: 8px; right: 8px; bottom: 4px;
  height: 2px; background: var(--brand-500); border-radius: 2px;
}

/* ===== Mobile responsive ===== */
@media (max-width: 980px) {
  .nav-shell { flex-wrap: wrap; padding: 12px 0; }
  .global-search { flex-basis: 100%; order: 5; }
  .menu-filter-toggle { display: none; }
  .hotline { display: none; }
}
@media (max-width: 760px) {
  .utility-inner { white-space: nowrap; overflow-x: auto; }
  .topbar { padding: 6px 0; }
  .nav-shell { padding: 6px 0 8px; min-height: 56px; }
  .brand-icon { border-radius: 12px; width: 40px; height: 40px; }
  .brand { font-size: 1rem; }
  .category-menu .shell { gap: 18px; justify-content: flex-start; }
}
@media (max-width: 640px) {
  .language-toggle { margin-left: auto; }
  .header-action:not(.language-toggle) { display: none; }
  /* Boss 2026-08-27 P1: collapse blog header on mobile - utility bar + global search
     + category-menu đã ẩn để tiết kiệm viewport. Nav-shell chỉ còn brand + lang.
     Trước: topbar=128px + utility=34px + nav=53px = 216px (~27% viewport 812).
     Sau: topbar=~56px + 0 + 0 = 56px (~7%). */
  .utility { display: none; }
  .category-menu { display: none; }
  .global-search { display: none; }
  .brand small { display: none; }
  .nav-shell { justify-content: space-between; padding: 10px 0; min-height: 56px; }
}
</style>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="site-header pro-header" id="oscar-site-header">
  <div class="utility">
    <div class="shell utility-inner">
      <span>
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11.017 2.814a1 1 0 0 1 1.966 0l1.051 5.558a2 2 0 0 0 1.594 1.594l5.558 1.051a1 1 0 0 1 0 1.966l-5.558 1.051a2 2 0 0 0-1.594 1.594l-1.051 5.558a1 1 0 0 1-1.966 0l-1.051-5.558a2 2 0 0 0-1.594-1.594l-5.558-1.051a1 1 0 0 1 0-1.966l5.558-1.051a2 2 0 0 0 1.594-1.594z"></path><path d="M20 2v4"></path><path d="M22 4h-4"></path><circle cx="4" cy="20" r="2"></circle></svg>
        Miễn phí giao hàng nội thành cho đơn từ 5 triệu
      </span>
      <a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>">Hệ thống cửa hàng</a>
      <a href="<?php echo esc_url( home_url( '/#policy' ) ); ?>">Chính sách</a>
    </div>
  </div>

  <div class="topbar">
    <div class="shell nav-shell">
      <button class="menu-filter-toggle active" aria-label="Bộ lọc" type="button">
        <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 5H3"></path><path d="M12 19H3"></path><path d="M14 3v4"></path><path d="M16 17v4"></path><path d="M21 12h-9"></path><path d="M21 19h-5"></path><path d="M21 5h-7"></path><path d="M8 10v4"></path><path d="M8 12H3"></path></svg>
      </button>

      <a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
        <img class="brand-icon" alt="" aria-hidden="true"
             src="<?php echo esc_url( get_template_directory_uri() . '/assets/images/oscar-avatar.webp' ); ?>">
        <span>
          <strong>OSCAR Thủ Đức</strong>
          <small>Tech Trusted Partner</small>
        </span>
      </a>

      <div class="global-search search-wrap" role="search">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21 21-4.34-4.34"></path><circle cx="11" cy="11" r="8"></circle></svg>
        <input type="search" placeholder="Tìm laptop, Dell Latitude, ThinkPad, workstation..." aria-label="Tìm sản phẩm" name="s">
      </div>

      <span class="header-action hotline" aria-label="Hotline Laptop OSCAR Thu Duc">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path></svg>
        0984.496.260
      </span>

      <button class="language-toggle" aria-label="Đổi sang tiếng Anh" type="button">
        <span class="language-flag flag-vn" aria-hidden="true"></span>
        <span>VI</span>
      </button>
    </div>
  </div>

  <nav class="category-menu simple-nav" aria-label="Danh mục chính">
    <div class="shell">
      <a href="<?php echo esc_url( home_url( '/#products' ) ); ?>">Sản phẩm</a>
      <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>">Sửa chữa</a>
      <a href="<?php echo esc_url( home_url( '/#blog' ) ); ?>" data-nav="blog">Bài viết</a>
      <a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>">Liên hệ</a>
    </div>
  </nav>
</header>

<main id="top" class="oscar-site-main">
<div id="content" class="oscar-site-content">
<script>
(function(){
  function init(){
    // Sticky header shadow on scroll
    var topbar=document.querySelector('.site-header.pro-header');
    if(topbar){
      var ticking=false;
      function onScroll(){
        if(!ticking){
          requestAnimationFrame(function(){
            topbar.classList.toggle('is-scrolled', window.scrollY>8);
            ticking=false;
          });
          ticking=true;
        }
      }
      window.addEventListener('scroll', onScroll, {passive:true});
      onScroll();
    }

    // Mobile bottom nav: mark active based on URL/body class
    // (prev: matched ANY single-segment URL like /shop/ as single-post — wrong)
    var cls = document.body.classList;
    var hash = window.location.hash || '';
    var onBlog = cls.contains('single-post') || cls.contains('single-post-oscar')
              || cls.contains('page-id-17')
              || cls.contains('category') || cls.contains('tag');
    document.querySelectorAll('.oscar-bottom-nav a').forEach(function(a){
      var href = a.getAttribute('href') || '';
      var navKey = a.getAttribute('data-nav') || '';
      var isActive = false;
      // Only the Bài viết anchor uses JS highlight (PHP handles the rest).
      if (navKey === 'blog' && onBlog) isActive = true;
      if(isActive) a.classList.add('is-active');
    });

    // Nav "Bài viết" highlight (Boss 2026-08-24: was using undefined `isSinglePost` — use `cls` + window.location.hash)
    var navBlog = document.querySelector('.category-menu.simple-nav a[data-nav="blog"]');
    if (navBlog && (cls.contains('single-post') || cls.contains('single-post-oscar') || hash === '#blog')) {
      navBlog.classList.add('is-active');
    }

    // Search box: focus → toggle search-wrap
    var search = document.querySelector('.global-search');
    if (search) {
      var input = search.querySelector('input');
      input && input.addEventListener('focus', function(){
        search.style.boxShadow = '0 0 0 3px rgba(11,94,184,.18)';
      });
      input && input.addEventListener('blur', function(){
        search.style.boxShadow = '';
      });
    }
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>