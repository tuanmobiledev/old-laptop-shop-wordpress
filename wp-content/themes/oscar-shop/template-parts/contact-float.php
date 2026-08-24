<?php
/**
 * Contact Float - Zalo + Messenger floating buttons
 *
 * Boss 2026-08-24: Migrated from React (src/main.jsx ContactFloat) to PHP so it
 * renders on ALL pages including PHP-only templates (archive, contact, 404).
 * Contact info mirrors src/data.js `contacts` export.
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
</aside>