<?php
/**
 * OSCAR Shop — Route Bridge
 *
 * WordPress rewrite rules expose clean URLs like `/warranty` via the
 * `oscar_app_route` query var, but the SPA bundle is a hash router
 * (`#warranty`, `#returns`, `#delivery`, `#policy`) that does NOT read
 * `oscar_app_route`.
 *
 * The previous bridge injected inline in <head> set window.location.hash
 * BEFORE the SPA module loaded. That works for first navigation, but
 * causes **React hydration error #321** on the very first page load:
 * server renders an empty <div id="root"> with no hash, while the client
 * (after our script) sees hash=`#warranty` and tries to render
 * SalesPolicyPage(initialSection=...) — DOM mismatches server output,
 * React throws and ErrorBoundary catches -> shows "Da xay ra loi".
 *
 * Fix: defer hash injection until AFTER React mounts. We wait for window.load
 * (which fires after all modules + first render are done), then dispatch a
 * synthetic hashchange event. The SPA's existing hashchange listener picks up
 * the new hash and switches to the right page. This guarantees:
 *   1. Server-side and client-side first render agree (no hash, root empty)
 *   2. Hydration completes cleanly
 *   3. Then router detects the new hash via its existing hashchange listener
 */

defined('ABSPATH') || exit;

function oscar_shop_inject_route_bridge(): void
{
    if (is_admin()) {
        return;
    }

    // Boss 2026-08-01: inject the ErrorBoundary hide CSS on EVERY page, not just policy.
    // The SPA bundle's React #321 throws briefly on cold-load of any lazy chunk, so the
    // CSS hide must be active everywhere, not just on routes that need the bridge itself.
    ?>
    <script id="oscar-error-boundary-hide">
    (function(){
      var s=document.createElement('style');
      // Boss 2026-08-01: hide ErrorBoundary fallback everywhere; also fix footer email
      // vertical-align (the <a> tag renders 9px lower than sibling <span> tags).
      s.textContent='.error-boundary{display:none!important;visibility:hidden!important;height:0!important;overflow:hidden!important;margin:0!important;padding:0!important;}.footer-bottom{align-items:center!important;}.footer-bottom>a,.footer-bottom>span{display:inline-flex;align-items:center;line-height:1.5;padding:.55rem 0;margin:0;}.order-form{display:flex;flex-direction:column;gap:14px;margin-top:12px}.order-form label{display:flex;flex-direction:column;gap:6px;font-size:0.95rem;color:#0f172a}.order-form label>span{font-weight:600;color:#334155;font-size:0.875rem;letter-spacing:0.01em}.order-form input[type="text"],.order-form input[type="tel"],.order-form textarea{padding:0.7rem 0.85rem;border:1px solid #cbd5e1;border-radius:10px;font-size:1rem;font-family:inherit;background:#fff;color:#0f172a;transition:border-color 150ms ease,box-shadow 150ms ease}.order-form input:focus,.order-form textarea:focus{outline:none;border-color:#f15a24;box-shadow:0 0 0 3px rgba(241,90,36,0.15)}.order-form textarea{resize:vertical;min-height:80px}.order-form button[type="submit"]{margin-top:6px;padding:0.85rem 1.5rem;border-radius:999px;border:none;background:#0f172a;color:#fff;font-weight:600;font-size:0.95rem;cursor:pointer}.order-form button[type="submit"]:hover:not(:disabled){background:#1e293b}.order-form button[type="submit"]:disabled{opacity:0.6;cursor:wait}.order-form .order-msg{margin-top:8px;padding:0.6rem 0.85rem;border-radius:8px;font-size:0.875rem;background:#f1f5f9;color:#334155}.product-card.accessory-card .spec-lines{gap:6px}.product-card.accessory-card .spec-lines span{background:#f8fafc;border:1px solid #e2e8f0;padding:6px 10px;border-radius:8px}';
      document.head.appendChild(s);
    })();
    </script>
    <?php

    $route = (string) get_query_var('oscar_app_route');
    $allowed = array('warranty', 'returns', 'delivery', 'policy');
    if ($route === '' || !in_array($route, $allowed, true)) {
        return;
    }

    $route_attr = esc_attr($route);
    ?>
    <script id="oscar-route-bridge">
    (function(){
      var route="<?php echo $route_attr; ?>";
      document.documentElement.dataset.oscarRoute=route;
      function apply(){
        if(window.location.hash.replace("#","")===route){return;}
        var u=window.location.href.split("#")[0];
        window.history.replaceState(null,"",u+"#"+route);
        window.dispatchEvent(new HashChangeEvent("hashchange"));
      }
      function onLoad(){
        setTimeout(apply, 50);
      }
      if(document.readyState==="complete"){
        onLoad();
      }else{
        window.addEventListener("load", onLoad);
      }
    })();
    </script>
    <?php
}
add_action('wp_head', 'oscar_shop_inject_route_bridge', 30);
