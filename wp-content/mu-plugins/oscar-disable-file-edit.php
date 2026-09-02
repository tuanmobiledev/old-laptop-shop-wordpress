<?php
/**
 * Plugin Name: Oscar Disable File Editor
 * Description: Boss 2026-09-02 — Block theme/plugin file editor in WP admin
 *              (equivalent to DISALLOW_FILE_EDIT in wp-config.php but
 *              deployable via image — wp-config.php is not under our control
 *              since it ships with the wordpress:6.9 base image).
 *              Hides Appearance → Theme File Editor + Plugins → Plugin File Editor
 *              submenu links and 403s direct URL access.
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;

add_action('admin_init', static function (): void {
    global $pagenow;
    if (in_array($pagenow, ['theme-editor.php', 'plugin-editor.php'], true)) {
        wp_die(
            esc_html__('File editing is disabled for security.', 'oscar'),
            esc_html__('File editing disabled', 'oscar'),
            ['response' => 403]
        );
    }
}, 1);

add_action('admin_menu', static function (): void {
    // Hide editor submenu items so admins can't see/access them.
    remove_submenu_page('themes.php', 'theme-editor.php');
    remove_submenu_page('plugins.php', 'plugin-editor.php');
}, 999);