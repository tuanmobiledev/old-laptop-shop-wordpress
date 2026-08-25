<?php
/**
 * Contact Float - Zalo + Messenger + ScrollTop floating buttons
 *
 * Boss 2026-08-24: Migrated from React (src/main.jsx ContactFloat) to PHP so it
 * renders on ALL pages including PHP-only templates (archive, contact, 404).
 * Contact info mirrors src/data.js `contacts` export.
 *
 * Boss 2026-08-25: Wrapped all 3 FABs in a single visual frame (white bg + shadow).
 * Reordered so ScrollTop sits at the bottom (was middle between Zalo + Messenger).
 *
 * @package oscar-shop
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<aside class="contact-float" aria-label="Liên hệ nhanh">
  <a class="zalo" href="https://zalo.me/2560332514093378750" target="_blank" rel="noreferrer" aria-label="Nhắn Zalo Laptop OSCAR Thủ Đức">Zalo</a>
  <a class="messenger" href="https://www.facebook.com/laptoposcar.thuduc" target="_blank" rel="noreferrer" aria-label="Nhắn Messenger Laptop OSCAR Thủ Đức">
    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>
  </a>
  <button class="oscar-scroll-top" type="button" aria-label="Lên đầu trang" title="Lên đầu trang">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
  </button>
</aside>
<script>
(function(){
  // Scroll-to-top: Boss 2026-08-25 — always visible, no show/hide on scroll.
  // Click handler kept; removed scroll-based opacity toggle.
  //
  // Boss 2026-08-25 bug fix: on mobile product detail, body is locked with
  // position:fixed; overflow:hidden (useLayoutEffect in main.jsx). window
  // scrollTo(0) was a no-op because the actual scroll container is
  // `article.product-modal` (CSS: overflow:auto + max-height:90vh at ≤640px).
  // Now: prefer the modal if it exists AND is scrollable, else fall back to
  // window scroll (desktop / non-product pages). Same selector as the SPA's
  // openProduct reset so behavior is consistent.
  var btn=document.querySelector('.oscar-scroll-top');
  if (!btn) return;
  btn.addEventListener('click', function(){
    var modal = document.querySelector('article.product-modal');
    if (modal && modal.scrollHeight > modal.clientHeight) {
      modal.scrollTo({top: 0, behavior: 'smooth'});
    } else {
      window.scrollTo({top: 0, behavior: 'smooth'});
    }
  });
})();

// Bottom-nav active state tracker (Boss 2026-08-25: hash + scroll-aware)
// Boss 2026-08-25 v2 fix: defer init until .oscar-bottom-nav is in DOM.
// Previous version ran at parse time and exited silently if nav not found yet
// (SPA mount can delay nav insertion). Now we wait for nav, then attach listeners.
(function(){
  var links = [];
  var sections = {};
  var attached = false;
  var scrollBound = false;
  var ticking = false;

  function setActive(name){
    if (!links.length) return;
    links.forEach(function(l){
      var isMatch = l.dataset.section === name;
      l.classList.toggle('is-active', isMatch);
      // Mirror to aria-current for screen readers (Boss 2026-08-25 UX audit)
      if (isMatch) {
        l.setAttribute('aria-current', 'page');
      } else {
        l.removeAttribute('aria-current');
      }
    });
  }

  function sectionEls(){
    var map = {};
    links.forEach(function(l){
      var id = l.dataset.section;
      var el = document.getElementById(id);
      if (el) map[id] = el;
    });
    return map;
  }

  function onScrollNav(){
    var ids = Object.keys(sections);
    if (!ids.length) return;
    var hash = (window.location.hash || '').replace('#','');
    // If hash is set, hash wins regardless of scroll position (Boss 2026-08-25 v4 behavior)
    if (hash) {
      setActive(hash);
      return;
    }
    var viewportCenter = window.scrollY + window.innerHeight * 0.35;
    var closest = null;
    var closestDist = Infinity;
    ids.forEach(function(id){
      var el = sections[id];
      var rect = el.getBoundingClientRect();
      var elTop = rect.top + window.scrollY;
      if (rect.top <= window.innerHeight * 0.5){
        var dist = Math.abs(elTop - viewportCenter);
        if (dist < closestDist){ closest = id; closestDist = dist; }
      }
    });
    if (closest) setActive(closest);
  }

  function attachListeners(){
    if (attached) return false;
    var nav = document.querySelector('.oscar-bottom-nav');
    if (!nav) return false;
    var found = nav.querySelectorAll('a[data-section]');
    if (!found.length) return false;
    links = Array.prototype.slice.call(found);
    sections = sectionEls();

// Click handler — Boss 2026-08-25 spec:
//   - Each nav = /#<section> hash navigation
//   - On home + active "Sản phẩm": 1st click → scroll top, 2nd click within 800ms → reload
//   - All other cases: default browser behavior (navigate to /#<section>)
    links.forEach(function(l){
      l.addEventListener('click', function(e){
        var id = l.dataset.section;
        var path = window.location.pathname;
        var onHome = (path === '/' || path === '');
        var isActive = l.classList.contains('is-active');

        // CASE A: Active "Sản phẩm" on home → scroll top / reload on 2nd click
        if (isActive && onHome) {
          e.preventDefault();
          var lastClick = parseInt(l.dataset.lastClick) || 0;
          var now = Date.now();
          l.dataset.lastClick = now;
          if (now - lastClick < 800) {
            window.location.reload();
          } else {
            window.scrollTo({top: 0, behavior: 'smooth'});
          }
          return;
        }

        // CASE B: Everything else → let browser do default (navigate to /#<section>)
      });
    });

    attached = true;
    if (!scrollBound){
      window.addEventListener('scroll', function(){
        if (!ticking){
          requestAnimationFrame(function(){
            onScrollNav();
            ticking = false;
          });
          ticking = true;
        }
      }, {passive:true});
      scrollBound = true;
    }
    return true;
  }

function init(){
    if (attachListeners()) sections = sectionEls();
    var hash = (window.location.hash || '').replace('#','');
    if (hash) setActive(hash);
    else onScrollNav();
  }

  // Try multiple times to handle SPA mount delays
  // (attached === true will short-circuit on subsequent calls)
  setTimeout(init, 50);
  setTimeout(init, 300);
  setTimeout(init, 800);
  setTimeout(init, 1500);

  // Also observe DOM in case nav appears later or sections mount after
  try {
    new MutationObserver(function(){
      sections = sectionEls();
      attachListeners();
      onScrollNav();
    }).observe(document.body, {childList:true, subtree:true});
  } catch(err) {}

  window.addEventListener('hashchange', function(){
    var hash = (window.location.hash || '').replace('#','');
    if (hash) setActive(hash);
  });
})();
</script>
