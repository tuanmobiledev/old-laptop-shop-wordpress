<?php
/**
 * Single source of truth for site-wide footer.
 *
 * Renders for:
 *   - Blog detail (`single.php` → `get_footer()`)
 *   - All SPA pages (`index.php` → `get_footer()` after React mount point)
 *   - All WC pages (`/cart/`, `/checkout/`, `/my-account/`, `/shop/`)
 *   - Static pages, archives, search, 404 (all fall through to `index.php`)
 *
 * Includes:
 *   - Inline `<style>` for footer + mobile bottom-nav + scroll-top button
 *   - `<footer class="footer business-footer">` with 5-col grid + bottom row
 *   - `<nav class="oscar-bottom-nav">` mobile app-style 4-item menu
 *   - Scroll-to-top FAB button + JS show/hide handler
 *
 * @package Oscar_Shop
 */
defined('ABSPATH') || exit;
?>

<style id="oscar-blog-footer-inline">
/* ====== Footer (business-footer, site-wide) ======
   Scope prefix `.business-footer` on every rule (specificity 0,3,0+) so PHP
   always wins over SPA bundle's `.footer form button` (0,2,1) and
   `.footer-subscribe button` (0,2,0) selectors that target the same elements. */
.footer.business-footer,
.business-footer {
  color: #d8e2ed;
  background: #0f172a;
  padding: 46px 0;
  font-family: "IBM Plex Sans", sans-serif;
}
.business-footer .footer-grid {
  width: min(1180px, 100% - 32px);
  margin: 0 auto;
  grid-template-columns: 1.2fr repeat(4, 1fr);
  gap: 28px;
  display: grid;
}
.business-footer .footer-grid > div > strong {
  color: #fff;
  font-size: 1.06rem;
  display: block;
  margin-bottom: 8px;
  font-weight: 700;
  font-family: "IBM Plex Sans", sans-serif;
}
.business-footer .footer-grid p {
  margin: 0 0 12px;
  font-size: .9rem;
  color: #cbd5e1;
  line-height: 1.55;
}
.business-footer .footer-grid h3 {
  color: #fff;
  font-size: .95rem;
  margin: 0 0 12px;
  font-weight: 700;
  letter-spacing: .02em;
  font-family: "IBM Plex Sans", sans-serif;
}
.business-footer .footer-grid a {
  color: #d8e2ed;
  text-decoration: none;
  display: block;
  padding: 5px 0;
  font-size: .9rem;
  transition: color .15s;
}
.business-footer .footer-grid a:hover { color: var(--brand-500); }

.business-footer .footer-subscribe {
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px; margin: 16px 0 10px;
  display: grid;
}
.business-footer .footer-subscribe input {
  color: #fff;
  background: rgba(15,23,42,.82);
  border: 1px solid rgba(148,163,184,.32);
  border-radius: 14px;
  min-width: 0; min-height: 44px;
  padding: 0 13px;
  font-family: inherit; font-size: .95rem;
  outline: 0;
}
.business-footer .footer-subscribe input:focus {
  border-color: var(--brand-500);
  background: rgba(15,23,42,.65);
}
.business-footer .footer-subscribe button,
.business-footer .footer form button {
  background: var(--brand-500); color: #fff;
  border: 0; border-radius: 14px;
  padding: 0 18px; min-height: 44px;
  font-weight: 700; cursor: pointer;
  font-family: inherit; font-size: .95rem;
  transition: background .15s;
}
.business-footer .footer-subscribe button:hover,
.business-footer .footer form button:hover { background: var(--brand-700, #c2410c); }

.business-footer .pay-badges {
  flex-wrap: wrap; gap: 8px;
  margin: 12px 0;
  display: flex;
}
.business-footer .pay-badges span {
  color: #e2e8f0;
  background: rgba(255,255,255,.12);
  border-radius: 999px;
  padding: 6px 9px;
  font-size: 12px;
  font-weight: 500;
}
.business-footer .footer-bottom {
  color: #e2e8f0;
  border-top: 1px solid rgba(148,163,184,.2);
  flex-wrap: wrap; gap: 12px 18px;
  margin: 26px auto 0;
  padding: 18px 0 0;
  font-size: .9rem;
  display: flex;
  width: min(1180px, 100% - 32px);
}
.business-footer .footer-bottom a {
  color: #e0f2fe;
  text-decoration: none;
}
.business-footer .footer-bottom a:hover { text-decoration: underline; }
.business-footer .footer-bottom .footer-contact-inline {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 14px;
}
.business-footer .footer-bottom .footer-contact-inline span,
.business-footer .footer-bottom .footer-contact-inline a {
  white-space: nowrap;
}

/* ====== Floating bottom nav (mobile app-style) ====== */
.oscar-bottom-nav {
  display: none;
  position: fixed;
  left: 0; right: 0; bottom: 0;
  z-index: 100;
  background: rgba(255,255,255,.96);
  backdrop-filter: blur(16px) saturate(180%);
  -webkit-backdrop-filter: blur(16px) saturate(180%);
  border-top: 1px solid var(--line);
  padding: 6px 4px calc(6px + env(safe-area-inset-bottom));
  box-shadow: 0 -10px 30px rgba(13,24,40,.08);
}
.oscar-bottom-nav-inner {
  width: min(560px, 100% - 16px);
  margin: 0 auto;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 4px;
}
.oscar-bottom-nav a {
  display: flex; flex-direction: column;
  align-items: center; justify-content: center;
  gap: 3px;
  padding: 8px 4px;
  border-radius: 12px;
  color: #475569;
  text-decoration: none;
  transition: color .15s, background .15s, transform .1s;
  font-size: .72rem;
  font-weight: 600;
  min-height: 56px;
}
.oscar-bottom-nav a:active { transform: scale(.94); }
.oscar-bottom-nav a.is-active { color: var(--brand-500); }
.oscar-bottom-nav a.is-active svg { color: var(--brand-500); }
.oscar-bottom-nav svg { width: 22px; height: 22px; flex: 0 0 auto; }

@media (max-width: 880px) {
  .oscar-bottom-nav { display: block; }
  .oscar-site-content { padding-bottom: 80px; }
}
@media (max-width: 380px) {
  .oscar-bottom-nav a { font-size: .68rem; }
  .oscar-bottom-nav a svg { width: 20px; height: 20px; }
}

/* ====== Contact-float (Boss 2026-08-25: transparent container, 3 buttons stacked) ======
   Boss prefers a clear (no background/shadow) container so the 3 FABs float
   on the page background. Each button keeps its own shadow.
   Order: Zalo (top) → Messenger (middle) → ScrollTop (cam, bottom) */
.contact-float {
  z-index: 120;
  position: fixed;
  right: 10px;  /* Boss 2026-08-25: was 14px — bring buttons closer to viewport edge */
  bottom: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  background: transparent;
  /* No padding, no border-radius, no box-shadow, no backdrop-filter */
}
.contact-float a,
.contact-float .oscar-scroll-top {
  position: relative;
  display: grid;
  place-items: center;
  color: #fff;
  border-radius: 50%;
  text-decoration: none;
  cursor: pointer;
  border: 0;
  font-family: inherit;
  transition: transform .18s, box-shadow .18s, opacity .25s;
  box-shadow: 0 10px 24px rgba(15, 23, 42, .18);
}
.contact-float a:hover,
.contact-float .oscar-scroll-top:hover {
  transform: translateY(-2px) scale(1.04);
  box-shadow: 0 14px 30px rgba(15, 23, 42, .26);
}
.contact-float .zalo {
  width: 56px; height: 56px;
  letter-spacing: -.8px;
  text-shadow: 0 1px 2px #0759a6;
  background: linear-gradient(145deg, #21d4fd 0%, #1e8fff 52%, #086de5 100%);
  font-family: Arial, sans-serif;
  font-size: 15px;
  font-weight: 700;
}
.contact-float .messenger {
  width: 56px; height: 56px;
  background: radial-gradient(circle at 30% 20%, #ff4fd8 0, #8a3ffc 42%, #0aa4ff 100%);
}
.contact-float .oscar-scroll-top {
  width: 44px; height: 44px;
  background: var(--brand-500);
  /* Boss 2026-08-25: always visible — removed opacity:0 + .show toggle */
}
.contact-float .oscar-scroll-top:hover { background: #d44e15; }

@media (max-width: 880px) {
  .contact-float {
    right: 10px;
    bottom: 88px; /* bottom-nav (~80px) + 8px gap */
    gap: 10px;
  }
  .contact-float .zalo,
  .contact-float .messenger {
    width: 48px; height: 48px;
  }
  .contact-float .oscar-scroll-top {
    width: 40px; height: 40px;
  }
}

/* ====== Bottom nav active state ====== */
.oscar-bottom-nav a.is-active {
  color: var(--brand-500);
  background: rgba(241, 90, 36, .08);
}
.oscar-bottom-nav a.is-active svg { color: var(--brand-500); }
.oscar-bottom-nav a.is-active span { font-weight: 700; }
</style>

<footer class="footer business-footer">
  <div class="shell footer-grid">
    <div>
      <strong>Laptop OSCAR Thủ Đức</strong>
      <p>Tech Trusted Partner - laptop, phụ kiện và sửa chữa laptop.</p>
      <form class="footer-subscribe" onsubmit="event.preventDefault(); alert('Cảm ơn bạn đã đăng ký!');">
        <input placeholder="Email nhận ưu đãi" aria-label="Email nhận ưu đãi" required type="email">
        <button type="submit">Đăng ký</button>
      </form>
      <div class="pay-badges">
        <span>COD</span><span>Chuyển khoản</span><span>Visa/Mastercard</span><span>Trả góp 0%</span>
      </div>
    </div>
    <div>
      <h3>Laptop OSCAR Thủ Đức</h3>
      <a href="<?php echo esc_url( home_url( '/#about' ) ); ?>">Giới thiệu</a>
      <a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>">Hệ thống cửa hàng</a>
      <a href="mailto:maytinhoscar@gmail.com?subject=Ung%20tuyen%20Laptop%20OSCAR%20Thu%20Duc">Tuyển dụng</a>
      <a href="<?php echo esc_url( home_url( '/#blog' ) ); ?>">Tin công nghệ</a>
    </div>
    <div>
      <h3>Hỗ trợ khách hàng</h3>
      <a href="<?php echo esc_url( home_url( '/#warranty' ) ); ?>">Chính sách bảo hành</a>
      <a href="<?php echo esc_url( home_url( '/#returns' ) ); ?>">Chính sách đổi trả</a>
      <a href="<?php echo esc_url( home_url( '/#delivery' ) ); ?>">Chính sách giao hàng</a>
    </div>
    <div>
      <h3>Dịch vụ</h3>
      <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>">Sửa laptop</a>
      <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>">Nâng cấp RAM/SSD</a>
      <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>">Vệ sinh laptop</a>
      <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>">Cài Windows</a>
    </div>
    <div>
      <h3>Kết nối</h3>
      <a href="https://www.facebook.com/laptoposcar.thuduc" target="_blank" rel="noreferrer">Facebook</a>
      <a href="https://zalo.me/2560332514093378750" target="_blank" rel="noreferrer">Zalo</a>
      <a href="mailto:maytinhoscar@gmail.com">Email hỗ trợ</a>
    </div>
  </div>
  <div class="shell footer-bottom">
    <span>© 2026 Laptop OSCAR Thủ Đức</span>
    <div class="footer-contact-inline">
      <span>0984.496.260</span>
      <a href="mailto:maytinhoscar@gmail.com">maytinhoscar@gmail.com</a>
    </div>
    <span>33a Đường số 17, Thủ Đức, Hồ Chí Minh, Việt Nam</span>
  </div>
</footer>

<!-- Mobile bottom nav (4 items, app-style) -->
<nav class="oscar-bottom-nav" aria-label="Menu mobile">
  <div class="oscar-bottom-nav-inner">
    <?php
      // Detect current page for active state (WP conditionals only — strpos was too loose and matched /shop/ as /blog)
      // NOTE: Oscar's front page (/) is set to latest posts, so is_home()=true on / => Bài viết active there.
      //
      // Boss 2026-08-25 fix: OSCAR Whitelist mu-plugin uses template_redirect (not WP_Query rewrite)
      // so /san-pham/<slug>-p<id>/ URLs don't set is_singular('product'). We must
      // match the URL pattern directly to set "Sản phẩm" active there.
      $req_path        = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
      $req_path        = rtrim($req_path, '/') === '' ? '/' : rtrim($req_path, '/');
      $is_product_url  = (bool) preg_match('#^/san-pham/[^/]+-p[0-9]+/?$#', $req_path);

      $is_products = is_singular('product') || is_shop() || is_product_taxonomy() || is_page('shop') || is_page('san-pham') || $is_product_url;
      $is_blog     = is_singular('post') || (is_home() && !$is_product_url) || is_category() || is_tag() || is_page('blog');
    ?>
<a href="<?php echo esc_url( home_url( '/#products' ) ); ?>" data-nav="products" data-section="products" class="<?php echo $is_products ? 'is-active' : ''; ?>"<?php echo $is_products ? ' aria-current="page"' : ''; ?>>
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
      <span>Sản phẩm</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>" data-nav="service" data-section="service">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"></path></svg>
      <span>Sửa chữa</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/#blog' ) ); ?>" data-nav="blog" data-section="blog" class="<?php echo $is_blog ? 'is-active' : ''; ?>"<?php echo $is_blog ? ' aria-current="page"' : ''; ?>>
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><path d="M14 2v6h6"></path><path d="M16 13H8"></path><path d="M16 17H8"></path><path d="M10 9H8"></path></svg>
      <span>Bài viết</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>" data-nav="contact" data-section="contact">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path></svg>
      <span>Liên hệ</span>
    </a>
  </div>
</nav>

<script>
(function(){
  var btn=document.querySelector('.oscar-scroll-top');
  if(!btn)return;
  var t=false;
  function onScroll(){
    if(!t){
      requestAnimationFrame(function(){
        btn.classList.toggle('show', window.scrollY>400);
        t=false;
      });
      t=true;
    }
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  btn.addEventListener('click', function(){
    window.scrollTo({top:0, behavior:'smooth'});
  });
  onScroll();

  // Boss 2026-08-27 hotfix: blog detail renders PHP (no React Router), so hash-only
  // links like <a href="/#products"> just change the URL hash without reloading the
  // page. Override clicks on .oscar-bottom-nav + footer grid links to force a full
  // navigation back to the SPA homepage. On every other page these are hash links
  // handled by React Router (or the SPA itself), so no override is needed.
  if (!document.body.classList.contains('single-post')) return;
  var targets = document.querySelectorAll('.oscar-bottom-nav a, .business-footer .footer-grid a');
  targets.forEach(function(a){
    var href = a.getAttribute('href') || '';
    // Only intercept same-domain hash anchors (e.g. https://site/#products)
    if (/^https?:\/\/[^/]+\/#.+$/.test(href) || href.indexOf('/#') === 0) {
      a.addEventListener('click', function(event){
        // Honor modifier keys / middle-click (open in new tab) — let browser handle
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.button === 1) return;
        event.preventDefault();
        var parts = href.split('#');
        var hash = parts[1] || '';
        // Boss 2026-08-27 hotfix: blog post is served at "/" path (WordPress rewrite),
        // so href="...#anchor" on current path "/" is a same-path hash change with NO
        // reload. Set hash then force reload() to actually navigate back to SPA.
        if (window.location.pathname === '/' || window.location.pathname === '') {
          window.location.hash = hash ? '#' + hash : '';
          window.location.reload();
        } else {
          window.location.href = (parts[0] || '/') + (hash ? '#' + hash : '');
        }
      });
    }
  });
})();
</script>