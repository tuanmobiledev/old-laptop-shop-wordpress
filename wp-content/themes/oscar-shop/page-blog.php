<?php
/**
 * Boss 2026-08-26: Blog section lives at /#blog (SPA hash route).
 * /blog/ is kept for backward-compat (external links, SEO) and 301-redirects
 * to the SPA blog page so users always land on the unified blog surface.
 */
wp_safe_redirect( home_url( '/#blog' ), 301 );
exit;