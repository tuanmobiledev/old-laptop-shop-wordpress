<?php
/**
 * Plugin Name: Oscar Mobile Layout Fix
 * Description: Fix horizontal overflow issues on mobile viewports
 *              (≤520px): breadcrumb truncate, header utility bar, footer grid
 *              stacking, WC product gallery wrapper width.
 * Version: 1.0.0
 */

if ( ! defined('ABSPATH') ) exit;

add_action('wp_head', static function () {
    if ( is_admin() ) return;
    ?>
    <style id="oscar-mobile-fix">
      /* === Mobile layout fixes (≤768px) === */

      /* Footer: stack columns on tablet/mobile */
      @media (max-width: 768px) {
        .footer-grid {
          grid-template-columns: 1fr 1fr !important;
          gap: 28px 20px !important;
        }
        .business-footer { padding: 36px 0 100px !important; }
        .footer-subscribe { grid-template-columns: 1fr !important; }
        .footer-subscribe button { width: 100% !important; }
      }
      @media (max-width: 480px) {
        .footer-grid {
          grid-template-columns: 1fr !important;
          gap: 24px !important;
        }
      }

      /* Header utility: hide right-side utility links on very small screens */
      @media (max-width: 480px) {
        .utility-inner { font-size: .75rem !important; }
        .utility-inner > span:first-child {
          max-width: 100%;
          overflow: hidden;
          text-overflow: ellipsis;
          white-space: nowrap;
        }
        .utility-inner > a[href*="cua-hang"],
        .utility-inner > a[href*="chinh-sach"],
        .utility-inner > a[href*="stores"],
        .utility-inner > a[href*="policy"] {
          display: none !important;
        }
      }

      /* Single post breadcrumb: allow current page title to wrap */
      @media (max-width: 520px) {
        .oscar-breadcrumb {
          font-size: 12px !important;
          margin-bottom: 14px !important;
        }
        .oscar-breadcrumb ol {
          flex-wrap: wrap !important;
          gap: 4px !important;
        }
        .oscar-breadcrumb li[aria-current="page"] {
          max-width: 100% !important;
          white-space: normal !important;
          overflow-wrap: anywhere !important;
          word-break: break-word !important;
          flex-basis: 100% !important;
          margin-top: 4px !important;
        }
      }

      /* WC product gallery: prevent 1000% wrapper width overflow */
      @media (max-width: 768px) {
        .woocommerce-product-gallery {
          max-width: 100% !important;
          overflow: hidden !important;
        }
        .woocommerce-product-gallery .flex-viewport {
          max-width: 100% !important;
          overflow: hidden !important;
        }
        .woocommerce-product-gallery__wrapper {
          width: 100% !important;
          max-width: 100% !important;
        }
        .woocommerce-product-gallery__image {
          width: 100% !important;
          max-width: 100% !important;
          float: none !important;
        }
        .woocommerce-product-gallery__image img {
          width: 100% !important;
          height: auto !important;
          max-width: 100% !important;
        }
        .woocommerce-product-gallery .flex-control-thumbs {
          max-width: 100% !important;
          overflow-x: auto !important;
          flex-wrap: wrap !important;
          justify-content: center !important;
        }
        /* zoomImg is plugin-injected; clip */
        img.zoomImg { display: none !important; }
      }

      /* Single product summary: prevent price/title overflow */
      @media (max-width: 520px) {
        .product_title, .summary .product_title {
          font-size: 22px !important;
          line-height: 1.25 !important;
          word-break: break-word !important;
        }
        .summary { max-width: 100% !important; }
        .summary .price { font-size: 1.1rem !important; }
        .woocommerce-tabs ul.tabs {
          flex-wrap: wrap !important;
          gap: 4px !important;
        }
        .woocommerce-tabs ul.tabs li a {
          padding: 8px 10px !important;
          font-size: 13px !important;
        }
      }

      /* Blog card mobile padding */
      @media (max-width: 480px) {
        .oscar-blog-card { border-radius: 12px !important; }
        .oscar-blog-thumb { aspect-ratio: 4 / 3 !important; }
      }

      /* Global: prevent any horizontal scroll caused by inline images or pre/code */
      .oscar-site-content {
        overflow-x: hidden !important;
      }
      .oscar-site-content img {
        max-width: 100% !important;
        height: auto !important;
      }
      .oscar-site-content pre, .oscar-site-content code {
        white-space: pre-wrap !important;
        word-break: break-word !important;
        overflow-x: auto !important;
        max-width: 100% !important;
      }
      .oscar-site-content table {
        display: block !important;
        overflow-x: auto !important;
        max-width: 100% !important;
      }
    </style>
    <?php
}, 99);