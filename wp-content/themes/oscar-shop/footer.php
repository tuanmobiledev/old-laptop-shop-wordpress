<?php
/**
 * Theme footer wrapper.
 *
 * Delegates the actual rendering to `template-parts/footer-business.php`
 * so the footer is a SINGLE source of truth shared between:
 *   - Blog detail (`single.php` → `get_footer()`)
 *   - All SPA pages (`index.php` → `get_footer()` after React mount)
 *   - All WC / static / archive pages that fall through to `index.php`
 *
 * To edit the footer (markup, links, copy, CSS), modify only
 * `template-parts/footer-business.php`.
 *
 * @package Oscar_Shop
 */
defined('ABSPATH') || exit;
?>
</div><!-- #content -->
</main><!-- #top -->

<?php
/**
 * Single source of truth — see template-parts/footer-business.php header doc.
 * Loaded via `locate_template()` so child themes can override if ever needed.
 */
$oscar_footer_business = locate_template('template-parts/footer-business.php', false, false);
if ($oscar_footer_business) {
    load_template($oscar_footer_business, false);
} else {
    echo '<!-- oscar-shop: template-parts/footer-business.php not found -->';
}
?>

<?php wp_footer(); ?>
</body>
</html>