<?php
/**
 * Skeleton header — server-rendered placeholder visible BEFORE the SPA bundle
 * (455KB) finishes downloading on slow networks.
 *
 * Why: Without this, JS-disabled / cold-load users see a blank page above the
 * fold for 4-46 seconds on Slow 3G because the entire <header> is mounted by
 * the React bundle into <div id="root">.
 *
 * Single source of truth (Boss 2026-08-31): all PHP templates (index.php,
 * single.php, …) wrap this skeleton INSIDE <div id="root">. The React bundle
 * then mounts and replaces this skeleton with the interactive <Header />
 * component — same class names ensure no layout shift.
 *
 * Mirrors footer-business.php pattern: dedicated template-parts file,
 * injected by every page that has the SPA shell.
 *
 * @package Oscar_Shop
 */
defined('ABSPATH') || exit;
?>
<!-- oscar-skeleton-header: visible <1s on slow networks, replaced by React -->
<header class="site-header pro-header oscar-skeleton-header" aria-hidden="true">
  <div class="utility">
    <div class="shell utility-inner">
      <span class="oscar-skel-bar oscar-skel-bar--utility"></span>
      <span class="oscar-skel-bar oscar-skel-bar--utility-short"></span>
      <span class="oscar-skel-bar oscar-skel-bar--utility-short"></span>
    </div>
  </div>

  <div class="topbar">
    <div class="shell nav-shell">
      <span class="oscar-skel-square oscar-skel-toggle"></span>

      <span class="oscar-skel-brand">
        <span class="oscar-skel-square oscar-skel-logo"></span>
        <span class="oscar-skel-lines">
          <span class="oscar-skel-bar oscar-skel-bar--brand"></span>
          <span class="oscar-skel-bar oscar-skel-bar--brand-sm"></span>
        </span>
      </span>

      <span class="oscar-skel-bar oscar-skel-search"></span>

      <span class="oscar-skel-bar oscar-skel-phone"></span>
      <span class="oscar-skel-bar oscar-skel-lang"></span>
    </div>
  </div>

  <nav class="category-menu simple-nav" aria-hidden="true">
    <div class="shell">
      <span class="oscar-skel-bar oscar-skel-nav-item"></span>
      <span class="oscar-skel-bar oscar-skel-nav-item"></span>
      <span class="oscar-skel-bar oscar-skel-nav-item"></span>
      <span class="oscar-skel-bar oscar-skel-nav-item"></span>
    </div>
  </nav>
</header>

<style id="oscar-skeleton-header-css">
/* Critical CSS — inline để FCP tức thì, không phụ thuộc bundle */
.oscar-skeleton-header {
  position: sticky;
  top: 0;
  z-index: 40;
  border-bottom: 1px solid rgba(160, 176, 195, 0.35);
  background: rgba(250, 253, 255, 0.92);
  box-shadow: 0 10px 30px rgba(13, 24, 40, .05);
  font-family: "IBM Plex Sans", sans-serif;
  color: #0f172a;
}
.oscar-skeleton-header *,
.oscar-skeleton-header *::before,
.oscar-skeleton-header *::after { box-sizing: border-box; }
.oscar-skeleton-header .utility {
  color: #eaf1f8;
  background: linear-gradient(90deg, #0d1828, #133251 62%, #0a5c67);
  font-size: .86rem;
}
.oscar-skeleton-header .utility-inner {
  align-items: center; gap: 22px; min-height: 34px;
  width: min(1180px, 100% - 32px); margin: 0 auto;
  display: flex;
}
.oscar-skeleton-header .topbar { background: rgba(255,255,255,.76); }
.oscar-skeleton-header .nav-shell {
  align-items: center; gap: 14px; min-height: 72px;
  width: min(1180px, 100% - 32px); margin: 0 auto;
  display: flex;
}
.oscar-skeleton-header .category-menu {
  background: #fff7ec;
  border-top: 1px solid #ffe0b6;
}
.oscar-skeleton-header .category-menu .shell {
  width: min(1180px, 100% - 32px); margin: 0 auto;
  align-items: center; gap: 28px; min-height: 42px;
  display: flex; justify-content: center;
}

.oscar-skeleton-header .oscar-skel-bar,
.oscar-skeleton-header .oscar-skel-square {
  display: inline-block;
  background: linear-gradient(90deg,
    rgba(15, 23, 42, .08) 0%,
    rgba(15, 23, 42, .14) 50%,
    rgba(15, 23, 42, .08) 100%);
  background-size: 200% 100%;
  animation: oscar-skel-pulse 1.4s ease-in-out infinite;
  border-radius: 6px;
  flex: 0 0 auto;
}
.oscar-skeleton-header .utility .oscar-skel-bar {
  background: linear-gradient(90deg,
    rgba(234, 241, 248, .12) 0%,
    rgba(234, 241, 248, .28) 50%,
    rgba(234, 241, 248, .12) 100%);
  background-size: 200% 100%;
}
.oscar-skeleton-header .oscar-skel-bar--utility { width: 280px; height: 14px; }
.oscar-skeleton-header .oscar-skel-bar--utility-short { width: 90px; height: 14px; }

.oscar-skeleton-header .oscar-skel-toggle { width: 42px; height: 42px; border-radius: 14px; }
.oscar-skeleton-header .oscar-skel-brand {
  display: inline-flex; align-items: center; gap: 10px;
  flex: 0 0 auto;
}
.oscar-skeleton-header .oscar-skel-logo {
  width: 46px; height: 46px; border-radius: 14px;
}
.oscar-skeleton-header .oscar-skel-lines {
  display: inline-flex; flex-direction: column; gap: 4px;
}
.oscar-skeleton-header .oscar-skel-bar--brand { width: 110px; height: 14px; }
.oscar-skeleton-header .oscar-skel-bar--brand-sm { width: 80px; height: 10px; }

.oscar-skeleton-header .oscar-skel-search {
  flex: 1 1 0%; height: 46px; border-radius: 14px; min-width: 200px;
}
.oscar-skeleton-header .oscar-skel-phone { width: 110px; height: 42px; border-radius: 13px; }
.oscar-skeleton-header .oscar-skel-lang { width: 72px; height: 42px; border-radius: 13px; }

.oscar-skeleton-header .oscar-skel-nav-item { width: 80px; height: 16px; border-radius: 4px; }

@keyframes oscar-skel-pulse {
  0%   { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* Boss 2026-08-31 P1: mobile collapse giống SPA bundle để match layout (no CLS) */
@media (max-width: 980px) {
  .oscar-skeleton-header .nav-shell { flex-wrap: wrap; padding: 12px 0; }
  .oscar-skeleton-header .oscar-skel-search {
    flex-basis: 100%; order: 5;
  }
  .oscar-skeleton-header .oscar-skel-toggle { display: none; }
  .oscar-skeleton-header .oscar-skel-phone { display: none; }
}
@media (max-width: 760px) {
  .oscar-skeleton-header .utility-inner { white-space: nowrap; overflow-x: auto; }
  .oscar-skeleton-header .topbar { padding: 6px 0; }
  .oscar-skeleton-header .nav-shell { padding: 6px 0 8px; min-height: 56px; }
  .oscar-skeleton-header .oscar-skel-logo { border-radius: 12px; width: 40px; height: 40px; }
  .oscar-skeleton-header .category-menu .shell { gap: 18px; justify-content: flex-start; }
}
@media (max-width: 640px) {
  .oscar-skeleton-header .oscar-skel-lang { margin-left: auto; }
  .oscar-skeleton-header .oscar-skel-phone { display: none; }
  .oscar-skeleton-header .oscar-skel-search {
    flex-basis: 100%; order: 5;
    margin-top: 2px;
    border-radius: 12px; height: 48px;
  }
  .oscar-skeleton-header .utility { display: none; }
  .oscar-skeleton-header .category-menu { display: none; }
  .oscar-skeleton-header .oscar-skel-bar--brand-sm { display: none; }
  .oscar-skeleton-header .nav-shell { flex-wrap: wrap; padding: 6px 0; min-height: 56px; gap: 8px; }
}

/* Reduced motion respect (a11y) */
@media (prefers-reduced-motion: reduce) {
  .oscar-skeleton-header .oscar-skel-bar,
  .oscar-skeleton-header .oscar-skel-square { animation: none; }
}
</style>
