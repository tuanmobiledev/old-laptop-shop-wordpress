<?php
/**
 * Footer template matching SPA homepage exactly.
 * Uses identical class names (footer, business-footer, footer-grid,
 * footer-bottom, footer-subscribe, pay-badges) so visual design transfers 1:1.
 * Includes floating bottom nav for mobile (app-style).
 *
 * @package Oscar_Shop
 */
?>
</div><!-- #content -->
</main><!-- #top -->

<style id="oscar-blog-footer-inline">
/* ====== Footer (matches SPA business-footer) ====== */
.footer.business-footer {
  color: #d8e2ed;
  background: #0f172a;
  padding: 46px 0;
  font-family: "IBM Plex Sans", sans-serif;
}
.footer-grid {
  width: min(1180px, 100% - 32px);
  margin: 0 auto;
  grid-template-columns: 1.2fr repeat(4, 1fr);
  gap: 28px;
  display: grid;
}
.footer-grid > div > strong {
  color: #fff;
  font-size: 1.06rem;
  display: block;
  margin-bottom: 8px;
  font-weight: 700;
}
.footer-grid p {
  margin: 0 0 12px;
  font-size: .9rem;
  color: #cbd5e1;
  line-height: 1.55;
}
.footer-grid h3 {
  color: #fff;
  font-size: .95rem;
  margin: 0 0 12px;
  font-weight: 700;
  letter-spacing: .02em;
}
.footer-grid a {
  color: #d8e2ed;
  text-decoration: none;
  display: block;
  padding: 5px 0;
  font-size: .9rem;
  transition: color .15s;
}
.footer-grid a:hover { color: var(--brand-500); }

.footer-subscribe {
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 8px; margin: 16px 0 10px;
  display: grid;
}
.footer-subscribe input {
  color: #fff;
  background: rgba(15,23,42,.82);
  border: 1px solid rgba(148,163,184,.32);
  border-radius: 14px;
  min-width: 0; min-height: 44px;
  padding: 0 13px;
  font-family: inherit; font-size: .95rem;
  outline: 0;
}
.footer-subscribe input:focus {
  border-color: var(--brand-500);
  background: rgba(15,23,42,.65);
}
.footer-subscribe button {
  background: var(--brand-500); color: #fff;
  border: 0; border-radius: 14px;
  padding: 0 18px; min-height: 44px;
  font-weight: 700; cursor: pointer;
  font-family: inherit; font-size: .95rem;
  transition: background .15s;
}
.footer-subscribe button:hover { background: var(--brand-700, #c2410c); }

.pay-badges {
  flex-wrap: wrap; gap: 8px;
  margin: 12px 0;
  display: flex;
}
.pay-badges span {
  color: #e2e8f0;
  background: rgba(255,255,255,.12);
  border-radius: 999px;
  padding: 6px 9px;
  font-size: 12px;
  font-weight: 500;
}
.footer-grid > div > a[href="#top"] {
  display: inline-block;
  color: #93c5fd;
  margin-top: 12px;
  font-size: .85rem;
}
.footer-grid > div > a[href="#top"]:hover { color: #fff; }

.footer-bottom {
  color: #e2e8f0;
  border-top: 1px solid rgba(148,163,184,.2);
  flex-wrap: wrap; gap: 12px 18px;
  margin: 26px auto 0;
  padding: 18px 0 0;
  font-size: .9rem;
  display: flex;
  width: min(1180px, 100% - 32px);
}
.footer-bottom a {
  color: #e0f2fe;
  text-decoration: none;
}
.footer-bottom a:hover { text-decoration: underline; }

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

/* ====== Scroll-to-top button (single.php) ====== */
.oscar-scroll-top {
  position: fixed; right: 18px; bottom: 84px;
  width: 44px; height: 44px;
  display: flex; align-items: center; justify-content: center;
  background: var(--brand-500); color: #fff;
  border-radius: 50%;
  border: 0; cursor: pointer;
  box-shadow: 0 10px 24px rgba(241,90,36,.4);
  opacity: 0; pointer-events: none;
  transition: opacity .25s, transform .2s;
  z-index: 90;
  font-family: inherit;
}
.oscar-scroll-top.show { opacity: 1; pointer-events: auto; }
.oscar-scroll-top:hover { transform: translateY(-2px); background: #d44e15; }
@media (max-width: 880px) { .oscar-scroll-top { bottom: 96px; right: 14px; } }
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
      <a href="#top">Lên đầu trang ↑</a>
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
    <span>0984.496.260</span>
    <a href="mailto:maytinhoscar@gmail.com">maytinhoscar@gmail.com</a>
    <span>33a Đường số 17, Thủ Đức, Hồ Chí Minh, Việt Nam</span>
  </div>
</footer>

<!-- Mobile bottom nav (4 items, app-style) -->
<nav class="oscar-bottom-nav" aria-label="Menu mobile">
  <div class="oscar-bottom-nav-inner">
    <a href="<?php echo esc_url( home_url( '/#products' ) ); ?>">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
      <span>Sản phẩm</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/#service' ) ); ?>">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
      <span>Sửa chữa</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/#blog' ) ); ?>" data-nav="blog">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg>
      <span>Bài viết</span>
    </a>
    <a href="<?php echo esc_url( home_url( '/#contact' ) ); ?>">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
      <span>Liên hệ</span>
    </a>
  </div>
</nav>

<!-- Scroll-to-top button -->
<button class="oscar-scroll-top" type="button" aria-label="Lên đầu trang" title="Lên đầu trang">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
</button>
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
})();
</script>

<?php wp_footer(); ?>
</body>
</html>