<?php
/**
 * Plugin Name: Oscar Product CTA Sticky
 * Description: Mobile sticky CTA bar for WC single-product pages.
 *              Mirrors the SPA's `.mobile-detail-sticky` (price + call + Zalo)
 *              so blog-post product clicks land on a page whose CTA is above
 *              the fold on mobile (≤768px). On desktop the bar is hidden to
 *              match SPA behavior.
 * Author: OSCAR Thủ Đức
 * Version: 1.0.0
 *
 * Depends on: WooCommerce (uses is_product(), wc_get_product(), WC_Product)
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Inject the sticky CTA into the WC single-product footer.
 *
 * - Mobile (≤768px): shows the bar (overrides display:none from SPA base CSS).
 * - Desktop (>768px): bar stays hidden, native WC content is the visual.
 *
 * Price precedence: sale > regular > "Liên hệ".
 * Hotline/Zalo values mirror SPA bundle constants (M.hotline, M.zalo).
 */
add_action( 'wp_footer', static function () {
    if ( is_admin() ) return;
    if ( ! function_exists( 'is_product' ) || ! is_product() ) return;

    global $product;
    if ( ! $product instanceof WC_Product ) {
        $product = wc_get_product( get_the_ID() );
    }
    if ( ! $product instanceof WC_Product ) return;

    $sale    = $product->get_sale_price();
    $regular = $product->get_regular_price();
    $price   = '';

    if ( $sale !== '' && $sale !== null ) {
        $price = (string) $sale;
    } elseif ( $regular !== '' && $regular !== null ) {
        $price = (string) $regular;
    }

    $price_display = $price !== ''
        ? number_format( (float) $price, 0, ',', '.' ) . ' ₫'
        : 'Liên hệ';

    // Keep these in lockstep with the SPA bundle constants.
    $hotline_plain  = '0984496260';
    $hotline_show   = '0984.496.260';
    $zalo_url       = 'https://zalo.me/2560332514093378750';
    $product_id     = (int) $product->get_id();
    $product_name   = (string) $product->get_name();
    ?>
    <div class="mobile-detail-sticky" data-oscar-product-cta
         data-product-id="<?php echo esc_attr( $product_id ); ?>"
         aria-label="Liên hệ nhanh">
      <div class="mobile-sticky-price">
        <small>Giá sản phẩm</small>
        <strong><?php echo esc_html( $price_display ); ?></strong>
      </div>
      <a class="mobile-sticky-call"
         href="tel:<?php echo esc_attr( $hotline_plain ); ?>"
         aria-label="Gọi <?php echo esc_attr( $hotline_show ); ?>"
         data-oscar-cta="phone">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M13.832 16.568a1 1 0 0 0 1.213-.303l.355-.465A2 2 0 0 1 17 15h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2A18 18 0 0 1 2 4a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v3a2 2 0 0 1-.8 1.6l-.468.351a1 1 0 0 0-.292 1.233 14 14 0 0 0 6.392 6.384"></path>
        </svg>
      </a>
      <a class="primary zalo-main"
         href="<?php echo esc_url( $zalo_url ); ?>"
         target="_blank" rel="noreferrer"
         aria-label="Nhắn Zalo tư vấn sản phẩm <?php echo esc_attr( $product_name ); ?>"
         data-oscar-cta="zalo">
        <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
        </svg>
        Zalo
      </a>
    </div>
    <style id="oscar-product-cta-sticky-css">
      /* Bar visibility: hidden by default (SPA base), shown on mobile ≤768px. */
      .mobile-detail-sticky { display: none; }
      @media (max-width: 768px) {
        .mobile-detail-sticky {
          display: grid !important;
          left: 10px; right: 10px;
          bottom: calc(82px + env(safe-area-inset-bottom));
          z-index: 60;
          -webkit-backdrop-filter: blur(14px);
          backdrop-filter: blur(14px);
          background: rgba(255,255,255,.96);
          border: 1px solid #e2e8f0;
          border-radius: 18px;
          grid-template-columns: 1fr 44px minmax(130px, 1.25fr) !important;
          align-items: center !important;
          gap: 8px !important;
          padding: 9px 10px calc(9px + env(safe-area-inset-bottom)) !important;
          box-shadow: 0 -10px 30px rgba(13,24,40,.18) !important;
          position: fixed;
        }
        .mobile-detail-sticky .zalo-main {
          color: #fff !important;
          background: linear-gradient(135deg,#0fb36f,#0678d8) !important;
          border: 0 !important;
          border-radius: 10px !important;
          box-shadow: 0 14px 28px rgba(8,140,135,.32) !important;
          justify-content: center !important;
          align-items: center !important;
          min-height: 42px !important;
          padding: 0 10px !important;
          font-size: .9rem !important;
          font-weight: 700 !important;
          display: inline-flex !important;
          gap: 6px !important;
          text-decoration: none !important;
        }
        .mobile-detail-sticky .mobile-sticky-call {
          justify-content: center !important;
          align-items: center !important;
          background: #eef7f4;
          color: #14946f;
          border-radius: 12px;
          width: 44px; height: 42px;
          display: inline-flex !important;
          text-decoration: none !important;
        }
        .mobile-detail-sticky .mobile-sticky-price {
          min-width: 0;
          display: flex; flex-direction: column;
          justify-content: center;
          line-height: 1.1;
        }
        .mobile-detail-sticky .mobile-sticky-price small {
          font-size: 11px; color: #64748b; letter-spacing: .02em;
          text-transform: uppercase; font-weight: 600;
        }
        .mobile-detail-sticky .mobile-sticky-price strong {
          font-size: 1.05rem; color: #0b5eb8; font-weight: 700;
          margin-top: 2px;
          white-space: nowrap;
          overflow: hidden;
          text-overflow: ellipsis;
        }
      }

      /* WC gallery main image: shrink so price/CTA appears above fold */
      @media (max-width: 768px) {
        .woocommerce-product-gallery__image {
          max-height: 320px;
          overflow: hidden;
        }
        .woocommerce-product-gallery__image > a,
        .woocommerce-product-gallery__image img {
          max-height: 320px !important;
          width: 100% !important;
          height: auto !important;
          object-fit: contain !important;
        }
        /* Hide sale badge overlay when image is constrained */
        .woocommerce-product-gallery__image .onsale {
          z-index: 2;
        }
        /* Reduce vertical spacing so price area hits the fold quickly */
        .single-product div.product {
          margin-top: 8px !important;
        }
        .product_meta, .summary .product_meta { margin-top: 14px !important; }
      }
    </style>
    <script>
    (function () {
      var bar = document.querySelector('[data-oscar-product-cta]');
      if (!bar) return;
      try {
        var productId = bar.getAttribute('data-product-id') || '';
        var productName = <?php echo wp_json_encode( $product_name ); ?>;
        var event = function (name) {
          try {
            if (typeof window.gtag === 'function') {
              window.gtag('event', name, {
                product_id: productId,
                product_name: productName,
                source: 'product_sticky_mobile_cta'
              });
            }
          } catch (e) { /* swallow */ }
        };
        var callBtn = bar.querySelector('[data-oscar-cta="phone"]');
        var zaloBtn = bar.querySelector('[data-oscar-cta="zalo"]');
        if (callBtn) callBtn.addEventListener('click', function () { event('phone_click'); });
        if (zaloBtn) zaloBtn.addEventListener('click', function () { event('zalo_click'); });
      } catch (e) { /* no-op */ }
    })();
    </script>
    <?php
}, 99 );
