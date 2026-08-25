import React, { Suspense, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { CheckCircle2, ClipboardCheck, Cpu, LayoutGrid, HardDrive, Headphones, Mail, MapPin, MessageCircle, Monitor, Rows3, PackageCheck, Phone, Search, Share2, ShieldCheck, SlidersHorizontal, Sparkles, Store, Truck, Wrench, X, Zap } from 'lucide-react';
import { banners, branches, contacts, formatCurrency, products, services } from './data.js';
import { copy, categoryLabels, demandLabels, filterOptions } from './catalogConfig.js';
import { discount, matchesCpuFamily, matchesDemand, matchesGpuFamily, matchesScreenSize, matchesSearchQuery, text, isDiscreteGpu } from './productUtils.js';
import { initGA, productParams, trackEvent, trackPageView } from './tracking.js';
import {
  normalizeImagePath,
  normalizeProductImages,
  productImageFallback,
  productIdFromPath,
  productPath,
  slugify,
  themeAssetUrl,
} from './utils.js';
import ProductCard from './components/ProductCard.jsx';
import ProductDetailPage from './components/ProductDetailPage.jsx';
import SearchAutocomplete from './components/SearchAutocomplete.jsx';
import './styles/tokens.css';
import './styles.css';
import './upgrade-builder.css';
import './config-options.css';
import './config-sheet.css';

import AdminProductsPage from './AdminProductsPage.jsx';
import SalesPolicyPage from './SalesPolicyPage.jsx';
import ErrorBoundary from './ErrorBoundary.jsx';

const STORAGE_KEYS = { products: 'oscar-products-v2' };

function App() {
  const [lang, setLang] = useState('vi');
  const [filters, setFilters] = useState({ query: '', category: 'all', brand: 'all', sortBy: 'featured', cpu: 'all', gpu: 'all', screen: 'all', demand: 'all' });
  const [filterOpen, setFilterOpen] = useState(() => typeof window !== 'undefined' ? window.innerWidth > 760 : true);
  const isMobile = () => typeof window !== 'undefined' && window.innerWidth <= 760;
  const [managedProducts, setManagedProducts] = useState(() => {
    try { return normalizeProductImages(JSON.parse(localStorage.getItem(STORAGE_KEYS.products)) || products); } catch { return products; }
  });
  const [selectedProduct, setSelectedProduct] = useState(() => {
    const productId = productIdFromPath();
    return productId ? managedProducts.find((product) => product.id === productId) || null : null;
  });
  // Boss 2026-08-06: MobileDetailSticky lives at App level (alongside
  // <MobileCommerce>) so its position:fixed resolves against the viewport
  // instead of the .product-modal scroll container. ProductDetailPage
  // publishes its current orderTotal via onOrderTotalChange; we mirror it
  // here so the sticky bar shows the live total as addons change.
  const [currentOrderTotal, setCurrentOrderTotal] = useState(0);
  const routeFromHash = () => {
    const hash = window.location.hash.replace('#', '');
    // Boss 2026-08-24: PHP bridge sets `data-oscar-route` on <html> for cart/checkout/my-account
    // clean URLs (avoids depending on window.location.pathname, which is intercepted by
    // WordPress page-id lookup for the static Cart/Checkout/MyAccount pages).
    const dsRoute = typeof document !== 'undefined' ? document.documentElement.dataset.oscarRoute : '';
    if (dsRoute === 'cart' || dsRoute === 'checkout' || dsRoute === 'my-account') return dsRoute;
    if (window.location.pathname.startsWith('/san-pham/') && !hash) return 'product-detail';
    const route = hash || 'products';
    return route.startsWith('policy-') ? 'policy' : route;
  };

  const [page, setPage] = useState(routeFromHash);
  // Boss 2026-08-03: track hash separately so policy-nav (which keeps page='policy'
  // but changes hash #policy-warranty -> #policy-return) still triggers scroll.
  const [routeHash, setRouteHash] = useState(() => (typeof window !== 'undefined' ? window.location.hash : '') || '');
  // Boss 2026-08-03: remember where the catalog list was scrolled before opening
  // detail, so closing detail (or hitting browser Back) returns the user to the
  // exact card they were viewing instead of jumping to the top.
  const [listScrollY, setListScrollY] = useState(0);
  // Boss 2026-08-04: synchronous ref + restore gate. The ref is captured
  // BEFORE scrollTo(0) in openProduct (the state value lag would miss popstate
  // races). wasInDetailRef gates popstate restore so navigating from detail
  // to a non-list page (e.g. about) doesn't drag listScrollY along.
  const listScrollYRef = useRef(0);
  const wasInDetailRef = useRef(false);
  // Boss 2026-08-04: lift Catalog's pagination page out of the component so it
  // survives detail navigation (Catalog unmounts while detail is open; without
  // lifting, page resets to 1 when the user swipes back to the list and they
  // lose their place). Ref captures synchronously; setter keeps ref in sync so
  // popstate can read the latest value without waiting for the next render.
  const [currentPage, setCurrentPageState] = useState(1);
  const currentPageRef = useRef(1);
  const setCurrentPage = (next) => {
    currentPageRef.current = next;
    setCurrentPageState(next);
  };
  const t = copy[lang];

  // Boss 2026-08-03: on mobile, lock body scroll when viewing product detail so
  // the user can't scroll past the (sometimes short) detail page and see body
  // background / Catalog below. Detail's own .detail-scroll (overflow-y:auto) is
  // the only scrollable region; desktop keeps body scroll because the card is
  // tall and scrolling both layers simultaneously confuses the eye.
  //
  // Boss 2026-08-03 (hotfix): cleanup must FORCE empty strings instead of
  // restoring `prev*` values. The filter drawer (Catalog useEffect line 312)
  // ALSO sets body.style.overflow='hidden' on mobile. If the filter is open
  // when the user opens detail (filterOpen state persists across navigation
  // since Catalog is `hidden`d, not unmounted), the body's overflow is already
  // 'hidden' from filter when our lock effect fires. Saving that as prev and
  // restoring on cleanup leaves body permanently 'hidden' — Catalog scrolls
  // become impossible until the user closes the filter. Same trap exists for
  // any future component that locks body. Force-reset breaks the chain.
  //
  // We also close any open filter drawer on entry so it can't pollute future
  // lock cycles and so the filter UI isn't lingering in DOM background.
  // useLayoutEffect (not useEffect) so the scroll-to-top + body-lock pair apply
  // atomically before browser paint — no flash of "stuck at footer" frame.
  useLayoutEffect(() => {
    if (typeof window === 'undefined' || page !== 'product-detail' || !selectedProduct) return undefined;
    const isMobile = window.innerWidth <= 760;
    if (!isMobile) return undefined;
    // Force-close any open filter drawer first. setFilterOpen(false) is async
    // (schedules a re-render), so the body lock below applies cleanly without
    // catalog filter cleanup wiping our lock afterwards.
    setFilterOpen(false);
    // Snapshot scroll position now (used only for close-restore — we do NOT
    // shift body by savedY because that puts the detail page off-screen above
    // the viewport when the user opened detail from a scrolled position like
    // the home page footer). Instead we scroll the page to top first so the
    // detail page renders from viewport top:0, then lock body at top:0.
    const savedY = window.scrollY || window.pageYOffset || 0;
    // Force scroll-to-top synchronously. behavior:'auto' bypasses html's
    // scroll-behavior:smooth so this completes immediately, even on iOS
    // Safari where 'instant' isn't always honored.
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' });
    // Lock: iOS Safari needs html-level; Android/Chrome only need body.
    document.documentElement.style.overflow = 'hidden';
    document.documentElement.style.position = 'fixed';
    document.documentElement.style.width = '100%';
    document.body.style.overflow = 'hidden';
    document.body.style.position = 'fixed';
    document.body.style.width = '100%';
    // top:0 (not -savedY) — see comment above. Saved scroll is restored on
    // cleanup via window.scrollTo(savedY) below.
    document.body.style.top = '0px';
    return () => {
      // FORCE reset to empty — never trust prev values. See header comment.
      document.body.style.overflow = '';
      document.body.style.position = '';
      document.body.style.width = '';
      document.body.style.top = '';
      document.documentElement.style.overflow = '';
      document.documentElement.style.position = '';
      document.documentElement.style.width = '';
      // Boss 2026-08-04: scroll restoration moved to a dedicated popstate
      // handler + closeProduct. The previous savedY in this closure was 0
      // (captured AFTER scrollTo(0) wiped the real value), so doing the
      // scrollTo here was a no-op. This effect is also mobile-only (early
      // return at top), so it never ran on desktop at all.
    };
  }, [page, selectedProduct]);
  useEffect(() => { localStorage.setItem(STORAGE_KEYS.products, JSON.stringify(normalizeProductImages(managedProducts))); }, [managedProducts]);
  // Boss 2026-08-01: re-resolve selectedProduct when managedProducts changes.
  // Without this, a product detail URL like /san-pham/...-p854 (e.g. mouse)
  // renders "Không tìm thấy sản phẩm" on first paint because managedProducts
  // is still the static `products` array from data.js (no mouse); the live
  // /wp-json/oscar/v1/products fetch lands after, but the initial null
  // selectedProduct is never re-resolved.
  useEffect(() => {
    if (selectedProduct) return;
    const productId = productIdFromPath();
    if (!productId) return;
    const found = managedProducts.find((product) => product.id === productId);
    if (found) setSelectedProduct(found);
  }, [managedProducts, selectedProduct]);
  useEffect(() => {
    const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
    fetch(`${restBase}products`)
      .then((response) => response.ok ? response.json() : Promise.reject())
      .then((remoteProducts) => setManagedProducts(normalizeProductImages(remoteProducts)))
      .catch(() => {});
  }, []);
  useEffect(() => {
    initGA();
    // Boss 2026-08-04: disable browser's automatic scroll restoration (BFCache)
    // so iOS Safari doesn't yank the list back to scroll=0 when the user
    // swipe-backs from a product detail page. Our own useLayoutEffect cleanup
    // restores listScrollY via rAF below.
    if (typeof window !== 'undefined' && 'scrollRestoration' in window.history) {
      window.history.scrollRestoration = 'manual';
    }
  }, []);

  useEffect(() => {
    trackPageView(page === 'product-detail' && selectedProduct ? selectedProduct.name : document.title);
  }, [page, selectedProduct]);

  useEffect(() => {
    const syncRoute = () => {
      const hash = window.location.hash;
      const leavingDetail = window.location.pathname.startsWith('/san-pham/') && hash;
      if (leavingDetail) {
        window.history.replaceState({}, '', `/${hash}`);
      }
      setPage(routeFromHash());
      setRouteHash(hash || '');
      const productId = leavingDetail ? null : productIdFromPath();
      setSelectedProduct(productId ? managedProducts.find((product) => product.id === productId) || null : null);
    };
    window.addEventListener('hashchange', syncRoute);
    window.addEventListener('popstate', syncRoute);
    return () => {
      window.removeEventListener('hashchange', syncRoute);
      window.removeEventListener('popstate', syncRoute);
    };
  }, [managedProducts]);
  useEffect(() => {
    const scrollTargets = {
      about: 'about',
      contact: 'store-locator',
      service: 'service',
      blog: 'blog',
      products: 'products',
      policy: 'policy',
      warranty: 'policy-warranty',
      returns: 'policy-return',
      delivery: 'policy-delivery',
      'policy-warranty': 'policy-warranty',
      'policy-return': 'policy-return',
      'policy-exclusion': 'policy-exclusion',
      'policy-delivery': 'policy-delivery',
      'policy-data': 'policy-data',
    };
    // Boss 2026-08-03: prefer hash over page when both could match.
    // e.g. clicking #policy-return while page='policy' must scroll to policy-return,
    // not always to top-level policy section.
    const hashRoute = routeHash.replace('#', '');
    const target = scrollTargets[hashRoute] || scrollTargets[page];
    if (!target) return undefined;
    const timer = window.setTimeout(() => {
      const el = document.getElementById(target);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
    return () => window.clearTimeout(timer);
  }, [page, routeHash]);
  const setFilter = (key, value) => setFilters((current) => ({ ...current, [key]: value }));
  const setFilterValue = (key, value) => {
    setFilter(key, value);
    trackEvent(key === 'query' ? 'search' : 'filter_change', { filter_key: key, filter_value: value });
  };
  const resetFilters = () => {
    trackEvent('filter_clear');
    setFilters({ query: '', category: 'all', brand: 'all', sortBy: 'featured', cpu: 'all', gpu: 'all', screen: 'all', demand: 'all' });
  };
  const openProduct = (product, source = 'product_card') => {
    trackEvent('product_view', productParams(product, { source }));
    setSelectedProduct(product);
    if (typeof window !== 'undefined') {
      // Boss 2026-08-04: capture scrollY BEFORE scrollTo(0) below wipes it.
      // Capturing in the useLayoutEffect cleanup is too late — scrollTo(0)
      // has already fired by the time useLayoutEffect runs, so savedY=0.
      // Sync ref (used by popstate / close handlers) + state (kept for any
      // downstream subscribers) — both updated atomically.
      const y = window.scrollY || window.pageYOffset || 0;
      listScrollYRef.current = y;
      setListScrollY(y);
      // Boss 2026-08-04: capture pagination page (ref is already in sync via
      // setCurrentPage wrapper) so swipe-back restores page=5, not page=1.
      currentPageRef.current = currentPage;
      // Boss 2026-08-04: mark that we entered detail, so the popstate handler
      // (later) knows to restore scroll on browser back.
      wasInDetailRef.current = true;
      // Boss 2026-08-03: clear routeHash so the scroll-effect doesn't try to scroll
      // back to a stale section anchor (e.g. user clicked from #about → opens product).
      setRouteHash('');
      window.history.pushState({ productDetail: product.id }, '', productPath(product));
      setPage('product-detail');
      window.scrollTo({ top: 0, behavior: 'instant' in window ? 'instant' : 'auto' });
      // Boss 2026-08-04: window.scrollTo(0) is a no-op while body is
      // position:fixed top:0 (document scrollY is already 0). The inner
      // .detail-scroll (overflow-y:auto) keeps its scrollTop from the
      // previous product, so the user perceives the new product as mid-page.
      // Reset the inner container too — same rAF defer ensures the new
      // product's content has rendered before we touch scrollTop.
      window.requestAnimationFrame(() => {
        const detailScroll = document.querySelector('.detail-scroll');
        if (detailScroll) detailScroll.scrollTop = 0;
      });
    }
  };
  const closeProduct = () => {
    setSelectedProduct(null);
    // Boss 2026-08-04: clear the popstate restore gate so a future back
    // navigation OFF catalog (not from detail) doesn't trigger scroll restore.
    wasInDetailRef.current = false;
    if (typeof window !== 'undefined' && window.location.pathname.startsWith('/san-pham/')) {
      window.history.pushState({}, '', '/#products');
      setPage('products');
      // Boss 2026-08-04: restore both pagination page and scroll position
      // before the rAF — the page state change triggers Catalog re-mount
      // with the right page, then rAF restores scroll after layout settles.
      setCurrentPage(currentPageRef.current);
      window.requestAnimationFrame(() => {
        window.scrollTo({ top: listScrollYRef.current, left: 0, behavior: 'auto' });
      });
    }
  };
  // Boss 2026-08-04: dedicated popstate handler for browser back/forward.
  // closeProduct only fires on the X button — swipe-back / browser-back goes
  // through popstate only, so we need a separate restore path. Gated by
  // wasInDetailRef so we don't drag listScrollY into a non-list navigation.
  useEffect(() => {
    const onPopState = () => {
      if (!wasInDetailRef.current) return;
      wasInDetailRef.current = false;
      if (typeof window === 'undefined') return;
      // Boss 2026-08-04: restore pagination page alongside scroll. Catalog
      // unmounts while detail is mounted; on popstate Catalog re-mounts and
      // pagedProducts = filteredProducts.slice((currentPage-1)*perPage,...).
      // setCurrentPage BEFORE rAF so React commits the new page in the same
      // render cycle as setPage('products') from syncRoute — Catalog re-mounts
      // already on page=5, then scrollTo places it correctly.
      setCurrentPage(currentPageRef.current);
      window.requestAnimationFrame(() => {
        window.scrollTo({ top: listScrollYRef.current, left: 0, behavior: 'auto' });
      });
    };
    window.addEventListener('popstate', onPopState);
    return () => window.removeEventListener('popstate', onPopState);
  }, []);

  const options = useMemo(() => filterOptions, []);
  const filteredProducts = useMemo(() => {
    // Boss 2026-08-01 (Option D): removed hardcoded `if (product.category === 'phu-kien') return false`.
    // Phụ kiện products now flow through the same filter pipeline; user can opt-in via category chip.
    const result = managedProducts.filter((product) => {
      return matchesSearchQuery(product, lang, filters.query) && (filters.category === 'all' || product.category === filters.category) && (filters.brand === 'all' || product.brand === filters.brand || (filters.brand === 'Other' && !filterOptions.brand.slice(1, -1).includes(product.brand))) && matchesCpuFamily(product.cpu, filters.cpu) && matchesGpuFamily([product.gpu, ...(product.variants || []).map((variant) => variant.gpu)].filter(Boolean).join(' '), filters.gpu) && matchesScreenSize(product.screen, filters.screen) && matchesDemand(product.demand, filters.demand);
    });
    return result.sort((a, b) => filters.sortBy === 'price-asc' ? a.price - b.price : filters.sortBy === 'price-desc' ? b.price - a.price : filters.sortBy === 'name-asc' ? a.name.localeCompare(b.name) : managedProducts.indexOf(a) - managedProducts.indexOf(b));
  }, [filters, lang, managedProducts]);

  const catalogProps = {
    currentPage, filteredProducts, filterOpen, filters, lang, options,
    resetFilters, setCurrentPage, setFilter: setFilterValue,
    setFilterOpen, setSelectedProduct: openProduct, t,
  };
  const detailProps = {
    lang, onClose: closeProduct, product: selectedProduct,
    productList: managedProducts, setProduct: openProduct, t,
    onOrderTotalChange: setCurrentOrderTotal,
  };
  const policyFallback = <div className="shell" style={{ padding: '4rem 0', textAlign: 'center' }}>Loading…</div>;
  const showCatalog = page === 'home' || page === 'products';

  // Boss 2026-08-04 (refactor): single Catalog instance always mounted, hidden via `hidden`
  // when on non-catalog pages. Hero/TrustStrip only on home. Each non-catalog page renders
  // in its own conditional block. This eliminates the duplicate-Catalog that previously
  // existed (pages.home + pages.products each mounted their own Catalog), which caused
  // viewMode/filter-state to reset and skeleton flash on detail nav.
  return <main id="top">
    <Header filterOpen={filterOpen} filters={filters} lang={lang} page={page} productList={managedProducts} setFilter={setFilterValue} setFilterOpen={setFilterOpen} setLang={setLang} setSelectedProduct={openProduct} t={t} />
    {page === 'home' && <><Hero lang={lang} t={t} /><TrustStrip t={t} /></>}
    <div className="page-container" hidden={!showCatalog}>
      <Catalog {...catalogProps} />
    </div>
    {page === 'product-detail' && <div className="page-container"><ProductDetailPage {...detailProps} /></div>}
    {page === 'about' && <div className="page-container"><AboutPage t={t} /></div>}
    {page === 'blog' && <div className="page-container"><TechArticles lang={lang} setFilter={setFilterValue} t={t} /></div>}
    {page === 'service' && <div className="page-container"><ServiceSection lang={lang} t={t} /></div>}
    {page === 'warranty' && <div className="page-container"><SalesPolicyPage initialSection="policy-warranty" t={t} /></div>}
    {page === 'returns' && <div className="page-container"><SalesPolicyPage initialSection="policy-return" t={t} /></div>}
    {page === 'delivery' && <div className="page-container"><SalesPolicyPage initialSection="policy-delivery" t={t} /></div>}
    {page === 'policy' && <div className="page-container"><SalesPolicyPage t={t} /></div>}
    {page === 'contact' && <div className="page-container"><StoreLocator lang={lang} t={t} /><ContactSection lang={lang} t={t} /></div>}
    {page === 'cart' && <div className="page-container">
      <h1 className="page-title">Giỏ hàng</h1>
      <p>Giỏ hàng của bạn đang được xử lý. Vui lòng liên hệ Hotline 0984.496.260 hoặc Zalo OA để được hỗ trợ đặt hàng nhanh nhất.</p>
      <p><a href="https://zalo.me/0984496260" className="cta-button">Mua hàng qua Zalo</a></p>
    </div>}
    {page === 'checkout' && <div className="page-container">
      <h1 className="page-title">Thanh toán</h1>
      <p>Vui lòng liên hệ Hotline 0984.496.260 hoặc Zalo OA để được hỗ trợ thanh toán.</p>
      <p><a href="https://zalo.me/0984496260" className="cta-button">Thanh toán qua Zalo</a></p>
    </div>}
    {page === 'my-account' && <div className="page-container">
      <h1 className="page-title">Tài khoản</h1>
      <p>Vui lòng liên hệ Hotline 0984.496.260 để được hỗ trợ tài khoản và lịch sử đơn hàng.</p>
      <p><a href="https://zalo.me/0984496260" className="cta-button">Liên hệ qua Zalo</a></p>
    </div>}
    {page === 'admin' && <div className="page-container"><AdminProductsPage products={managedProducts} setProducts={setManagedProducts} t={t} /></div>}
    {/* Boss 2026-08-24: <Footer> removed — rendered by PHP (template-parts/footer-business.php) via get_footer() in single.php/index.php */}
    {/* Boss 2026-08-25: <ContactFloat> removed — rendered by PHP (template-parts/contact-float.php) to avoid duplicate DOM on SPA pages (PHP renders on ALL pages via get_footer) */}
    {/* Boss 2026-08-24: <MobileCommerce> removed — replaced by PHP <nav class="oscar-bottom-nav"> in template-parts/footer-business.php (avoids duplicate bottom nav) */}
    {/* Boss 2026-08-06: lifted from inside ProductDetailPage to App level.
        Inside .product-modal (overflow:auto + max-height) the position:fixed
        was snapping to the modal's box instead of the viewport on iOS/Chrome.
        Now it's a true sibling of <MobileCommerce>, so the fixed offset
        stacks cleanly above the bottom tab bar. */}
    {page === 'product-detail' && selectedProduct && (
      <MobileDetailSticky product={selectedProduct} orderTotal={currentOrderTotal} t={t} />
    )}
  </main>;
}

// Boss 2026-08-06: mobile sticky CTA bar — kept lean (price + Zalo CTA) since
// it duplicates MobileCommerce z-index zone above the bottom nav. Rendered at
// App level so position:fixed works against the viewport regardless of which
// PDP scroll-container the user is currently inside.
function MobileDetailSticky({ product, orderTotal, t }) {
  if (!product) return null;
  const price = orderTotal || product.price;
  const hotlineDigits = String(contacts.hotline).replace(/\D/g, '');
  return (
    <div className="mobile-detail-sticky">
      <div className="mobile-sticky-price"><small>Giá sản phẩm</small><strong>{formatCurrency(price)}</strong></div>
      <a className="mobile-sticky-call" href={`tel:${hotlineDigits}`} onClick={() => trackEvent('phone_click', productParams(product, { source: 'sticky_mobile_cta' }))} aria-label={`${t.call} ${contacts.hotline}`}><Phone size={20} /></a>
      <a className="primary zalo-main" href={contacts.zalo} target="_blank" rel="noreferrer" onClick={() => trackEvent('zalo_click', productParams(product, { source: 'sticky_mobile_cta' }))}>
        <MessageCircle size={17} /> {t.messageZalo}
      </a>
    </div>
  );
}

function Header({ filterOpen, filters, lang, page, productList, setFilter, setFilterOpen, setLang, setSelectedProduct, t }) {
  const [searchOpen, setSearchOpen] = useState(false);
  const suggestions = searchOpen && filters.query ? productList.filter((product) => matchesSearchQuery(product, lang, filters.query)).slice(0, 5) : [];
  const canToggleFilter = page === 'home' || page === 'products';
  const brandHref = page === 'product-detail' ? '/#products' : '#home';
  const chooseProduct = (product) => { setSelectedProduct(product); setSearchOpen(false); };
  const chooseKeyword = (key) => { setFilter('query', key); setSearchOpen(false); };
  const toggleFilter = () => { setFilterOpen((open) => !open); window.requestAnimationFrame(() => document.getElementById('products')?.scrollIntoView({ behavior: 'smooth', block: 'start' })); };
  return <header className="site-header pro-header"><div className="utility"><div className="shell utility-inner"><span><Sparkles size={14} /> {t.topDeal}</span><a href="#contact">{t.topStore}</a><a href="#policy">{t.topPolicy}</a></div></div><div className="topbar"><div className="shell nav-shell">{canToggleFilter && <button className={`menu-filter-toggle ${filterOpen ? 'active' : ''}`} aria-label={filterOpen ? t.hideFilters : t.showFilters} title={t.productFilters} onClick={toggleFilter} type="button"><SlidersHorizontal size={21} /></button>}<a className="brand" href={brandHref}><img className="brand-icon" src={themeAssetUrl('/oscar-avatar.webp')} alt="" aria-hidden="true" /><span><strong>OSCAR Thủ Đức</strong><small>{page === 'product-detail' ? t.backToList : t.techPartner}</small></span></a><div className="global-search search-wrap" onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setSearchOpen(false); }}><Search size={18} /><input value={filters.query} onFocus={() => setSearchOpen(true)} onKeyDown={(event) => { if (event.key === 'Escape') setSearchOpen(false); }} onChange={(event) => { setFilter('query', event.target.value); setSearchOpen(true); }} placeholder={t.searchPlaceholder} aria-label={t.searchProductsLabel} />{suggestions.length > 0 && <SearchAutocomplete suggestions={suggestions} chooseProduct={chooseProduct} chooseKeyword={chooseKeyword} t={t} />}</div><span className="header-action hotline" aria-label={t.hotlineLabel}><Phone size={17} />{contacts.hotline}</span><button className="language-toggle" aria-label={t.switchLanguage} title={t.switchLanguage} onClick={() => setLang(lang === 'vi' ? 'en' : 'vi')}><span className={`language-flag ${lang === 'vi' ? 'flag-vn' : 'flag-us'}`} aria-hidden="true"><span></span></span><span>{lang === 'vi' ? 'VI' : 'EN'}</span></button></div></div><nav className="category-menu simple-nav"><div className="shell"><a href="#products">{t.navProducts}</a><a href="#service">{t.navRepair}</a><a href="#blog">{t.navBlog}</a><a href="#contact">{t.contact}</a></div></nav></header>;
}
function Hero({ lang, t }) { return <section className="hero shell"><div className="hero-copy"><span className="eyebrow"><Sparkles size={16} /> {t.heroEyebrow}</span><h1>{t.heroTitle}</h1><p>{t.heroDesc}</p><div className="hero-specs"><span><b>12</b> {t.heroBrands}</span><span><b>{t.heroSteps}</b> {t.heroChecks}</span><span><b>24h</b> {t.heroCityDelivery}</span></div><div className="hero-actions"><a className="primary" href="#products">{t.viewProducts}</a><span className="secondary phone-display">{t.bookRepair}: {contacts.hotline}</span></div></div><div className="banner-stack">{banners.map((banner, index) => <article className={`promo-banner tone-${index}`} key={text(banner.title, lang)}><small>{index === 0 ? t.catalogPick : index === 1 ? t.upgradeLab : t.payment}</small><span>{text(banner.title, lang)}</span><p>{text(banner.desc, lang)}</p><strong>{text(banner.cta, lang)}</strong></article>)}</div></section>; }
function TrustStrip({ t }) { const items = [{ icon: ClipboardCheck, title: t.checked, meta: t.fiveStepTest }, { icon: ShieldCheck, title: t.warranty, meta: t.warrantyMonths }, { icon: Truck, title: t.delivery, meta: t.sameDay }, { icon: Headphones, title: t.support, meta: '9:00-21:00' }]; return <section className="trust shell">{items.map(({ icon: Icon, title, meta }) => <article key={title}><Icon size={22} /><div><strong>{title}</strong><span>{meta}</span></div></article>)}</section>; }

function AboutPage({ t }) {
  const icons = [ShieldCheck, PackageCheck, Headphones];
  const values = t.aboutValues.map((item, index) => ({ ...item, icon: icons[index] }));
  return <section className="about-page shell footer-about-page" id="about"><div className="about-hero"><span className="eyebrow"><Sparkles size={16} /> {t.aboutEyebrow}</span><h1>{t.aboutTitle}</h1><p>{t.aboutDescLong}</p><div className="about-actions"><a className="primary" href="#products">{t.aboutCtaProducts}</a><a className="secondary" href="#contact">{t.aboutCtaContact}</a></div></div><div className="about-story">{t.aboutStory.map((item) => <article key={item.title}><h2>{item.title}</h2><p>{item.desc}</p></article>)}</div><div className="about-values">{values.map(({ icon: Icon, title, desc }) => <article key={title}><Icon size={24} /><h3>{title}</h3><p>{desc}</p></article>)}</div></section>;
}

function Catalog({ currentPage, filteredProducts, filterOpen, filters, lang, options, resetFilters, setCurrentPage, setFilter, setFilterOpen, setSelectedProduct, t }) {
  // FILTER-1: Esc closes drawer. NO body scroll lock here.
  // Boss 2026-08-03 (hotfix #3): the previous body.overflow='hidden' lock raced
  // with App's product-detail lock — when filter was open and user opened a
  // product, our lock effect called setFilterOpen(false), which on the next
  // render fired this cleanup, restoring body.overflow to its filter-time
  // prev (typically ''), overwriting the detail lock we had just set. User
  // could then never scroll the detail page.
  // The filter drawer is `.android-filter-drawer` (position:fixed, covers
  // viewport, internal overflow-y:auto on .drawer-filter-body) — body
  // underneath is visually covered so locking is unnecessary. If iOS Safari
  // rubber-banding later becomes a real complaint, address with
  // `overscroll-behavior:contain` on the drawer, NOT another body lock.
  useEffect(() => {
    if (!filterOpen) return undefined;
    const onKey = (event) => { if (event.key === 'Escape') setFilterOpen(false); };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [filterOpen, setFilterOpen]);
  // Boss 2026-08-04: page/setPage lifted to App so it survives detail nav.
  // Read from `currentPage` prop, write via `setCurrentPage` prop.
  const [viewMode, setViewMode] = useState('grid');
  const [loading, setLoading] = useState(true);
  const active = Object.entries(filters).filter(([key, value]) => key !== 'sortBy' && value && value !== 'all');
  const perPage = 12;
  useEffect(() => {
    setLoading(true);
    const timer = window.setTimeout(() => setLoading(false), 220);
    return () => window.clearTimeout(timer);
  }, [filters.category, filters.brand, filters.cpu, filters.gpu, filters.screen, filters.demand, filters.query, filters.sortBy]);
  const pageCount = Math.max(1, Math.ceil(filteredProducts.length / perPage));
  const safePage = Math.min(Math.max(currentPage, 1), pageCount);
  const pagedProducts = filteredProducts.slice((safePage - 1) * perPage, safePage * perPage);
  const setFilterAndPage = (key, value) => { setCurrentPage(1); setFilter(key, value); };
  const resetAll = () => { setCurrentPage(1); resetFilters(); };
  const chipNames = { brand: t.filterBrand, cpu: 'CPU', gpu: 'GPU', screen: t.screenLabel, demand: t.demand, category: t.category };
  const gpuLabels = { 'gpu-roi': t.filterGpuDiscrete, workstation: t.filterWorkstation, 'GTX/MX': 'GTX/MX', Radeon: 'Radeon', onboard: t.filterOnboard, 'Intel Arc': 'Intel Arc' };
  const screenLabels = { 12: '12" / 12.4"', 13: '13" / 13.3" / 13.4"', 14: '14" / 14.5"', 15: '15" / 15.6"', 16: '16"', 17: '17" / 17.3"', 18: '18"' };
  const sortLabels = { featured: t.sortFeatured, 'price-asc': t.sortPriceAsc, 'price-desc': t.sortPriceDesc, 'name-asc': t.sortNameAsc };
  const valueLabel = (key, value) => key === 'demand' ? (demandLabels[lang]?.[value] || value) : key === 'category' ? (categoryLabels[lang]?.[value] || value) : key === 'gpu' ? gpuLabels[value] || value : key === 'screen' ? screenLabels[value] || value : key === 'sortBy' ? sortLabels[value] || value : value;
  const chipLabel = ([key, value]) => key === 'query' ? `${t.keyword}: ${value}` : `${chipNames[key] || key}: ${valueLabel(key, value)}`;
  const optionLabel = (item, key) => item === 'all' ? t.all : valueLabel(key, item);
  const filterCount = active.length;
  const quickChips = [
    ['brand', 'Dell', 'Dell'],
    ['query', 'thinkpad', 'ThinkPad'],
    ['brand', 'HP', 'HP'],
    ['cpu', 'i5', 'Core i5'],
    ['cpu', 'i7', 'Core i7'],
    ['demand', 'office', t.office],
    ['demand', 'thin-light', t.thinLight],
  ];
  const selectGroup = (label, key, values) => <label className="compact-select-filter"><span>{label}</span><select value={filters[key]} onChange={(event) => setFilterAndPage(key, event.target.value)}>{values.map((item) => <option key={item} value={item}>{optionLabel(item, key)}</option>)}</select></label>;
  const isAccessoryCategory = filters.category === 'phu-kien';
  const filterPanel = <aside className={`filter-panel advanced checkbox-filter product-filter-sidebar compact-filter-panel android-filter-drawer ${filterOpen ? 'open' : 'closed'}`} aria-hidden={!filterOpen}><div className="drawer-grip" /><div className="filter-drawer-head"><div><strong>{t.productFilters}</strong><span>{t.filterChooseConfig}</span></div><button className="filter-close" aria-label={t.closeFilters} onClick={() => setFilterOpen(false)}><X size={18} /></button></div><div className="compact-filter-stack drawer-filter-body">{selectGroup(t.category, 'category', options.category)}{selectGroup(t.filterBrand, 'brand', options.brand)}{!isAccessoryCategory && selectGroup('CPU', 'cpu', options.cpu)}{!isAccessoryCategory && selectGroup('GPU', 'gpu', options.gpu)}{!isAccessoryCategory && selectGroup(t.screenLabel, 'screen', options.screen)}{!isAccessoryCategory && selectGroup(t.demand, 'demand', options.demand)}{selectGroup(t.sort, 'sortBy', ['featured', 'price-asc', 'price-desc', 'name-asc'])}</div><div className="filter-sheet-actions drawer-filter-footer"><button className="clear-filter" onClick={resetAll}>{t.clear}</button><button className="apply-filter" onClick={() => setFilterOpen(false)}>{t.applyFilterPrefix} {filteredProducts.length} {t.productCount}</button></div></aside>;

  return <section className="section shell catalog tech-catalog" id="products"><div className="breadcrumb">{t.homeBreadcrumb} / {t.catalogBreadcrumb} / {t.mobileProducts}</div><div className="section-heading split-heading"><div><span className="eyebrow"><SlidersHorizontal size={16} /> {t.catalogEyebrow}</span><h2>{t.catalogTitle}</h2></div>{t.catalogDesc && <p>{t.catalogDesc}</p>}</div><div className="mobile-filter-strip"><button className="android-filter-trigger" type="button" aria-label={t.productFilters} onClick={() => { trackEvent('filter_open', { source: 'mobile_drawer' }); setFilterOpen(true); }}><SlidersHorizontal size={17} /> {t.filterShort}{filterCount ? ` · ${filterCount}` : ''}</button><div className="quick-filter-chips">{quickChips.map(([key, value, label]) => <button key={`${key}-${value}`} className={filters[key] === value ? 'active' : ''} type="button" aria-label={`${t.productFilters} ${label}`} aria-pressed={filters[key] === value} onClick={() => setFilterAndPage(key, filters[key] === value ? 'all' : value)}>{label}</button>)}</div></div><div className="sort-bar catalog-toolbar"><span>{filteredProducts.length} {t.productCount}</span><select value={filters.sortBy} onChange={(e) => setFilterAndPage('sortBy', e.target.value)}><option value="featured">{t.featured}</option><option value="price-asc">{t.priceAsc}</option><option value="price-desc">{t.priceDesc}</option><option value="name-asc">{t.nameAsc}</option></select><div className="view-toggle" aria-label={t.displayMode}><button className={viewMode === 'grid' ? 'active' : ''} aria-label={t.gridView} title={t.grid} onClick={() => setViewMode('grid')}><LayoutGrid size={19} strokeWidth={2.4} /></button><button className={viewMode === 'list' ? 'active' : ''} aria-label={t.listView} title={t.list} onClick={() => setViewMode('list')}><Rows3 size={19} strokeWidth={2.4} /></button></div></div><div className="active-chips">{active.map((entry) => <button key={entry.join('-')} onClick={() => setFilterAndPage(entry[0], entry[0] === 'query' ? '' : 'all')}>{chipLabel(entry)} <X size={13} /></button>)}{active.length > 0 && <button className="clear-chip" onClick={resetAll}>{t.clearAll}</button>}</div>{filterOpen && <button className="filter-scrim android-drawer-scrim" aria-label={t.closeFilters} onClick={() => setFilterOpen(false)} />}<div className={`catalog-layout ${filterOpen ? 'filters-visible' : 'filters-hidden'}`}>{filterPanel}<div className="product-area">{loading ? (<div className="product-grid" aria-busy="true" aria-label="Đang tải sản phẩm">{Array.from({ length: 8 }).map((_, idx) => (<div key={`skeleton-${idx}`} className="product-card-skeleton" aria-hidden="true"><div className="skeleton-art" /><div className="skeleton-line skeleton-line-title" /><div className="skeleton-line skeleton-line-meta" /><div className="skeleton-line skeleton-line-price" /></div>))}</div>) : (<div className={`product-grid ${viewMode === 'list' ? 'list-mode' : ''}`}>{pagedProducts.length ? pagedProducts.map((product) => <ProductCard product={product} lang={lang} t={t} key={product.id} setSelectedProduct={setSelectedProduct} />) : <div className="empty-state catalog-empty"><Search size={38} /><h3>{t.noResults}</h3><p>{t.noResultsDesc}</p><div><button onClick={resetAll}>{t.noResultsClear}</button><span className="phone-display">Hotline: {contacts.hotline}</span></div></div>}</div>)}{pagedProducts.length > 0 && <div className="pagination">{Array.from({ length: pageCount }).map((_, index) => <button className={safePage === index + 1 ? 'active' : ''} key={index} aria-label={`Trang ${index + 1}`} aria-current={safePage === index + 1 ? 'page' : undefined} onClick={() => setCurrentPage(index + 1)}>{index + 1}</button>)}</div>}</div></div></section>;
}

// ProductCard moved to ./components/ProductCard.jsx (T3 refactor). Heuristic
// (`isAccessory = product.category === 'phu-kien' || (!product.cpu && !product.ram && !product.ssd && !product.screen)`)
// re-applied inside that file to handle legacy data where accessories were
// filed under laptop-cu (origin: src tree commit 7729804).
function TechArticles({ lang, setFilter, t }) {
  const [posts, setPosts] = useState([]);
  const [loading, setLoading] = useState(true);
  const [fetchError, setFetchError] = useState(false);
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    fetch('/wp-json/wp/v2/posts?per_page=20&_embed=1&orderby=date&order=desc')
      .then((response) => { if (!response.ok) throw new Error(`HTTP ${response.status}`); return response.json(); })
      .then((data) => { if (!cancelled) { setPosts(data); setLoading(false); } })
      .catch(() => { if (!cancelled) { setFetchError(true); setLoading(false); } });
    return () => { cancelled = true; };
  }, []);
  const stripHtml = (html) => { if (!html) return ''; const tmp = document.createElement('div'); tmp.innerHTML = html; return (tmp.textContent || tmp.innerText || '').trim(); };
  const extractBullets = (html) => { if (!html) return []; const tmp = document.createElement('div'); tmp.innerHTML = html; return Array.from(tmp.querySelectorAll('li')).map((li) => (li.textContent || '').trim()).filter(Boolean); };
  const parseDate = (iso, locale) => { try { const d = new Date(iso); return d.toLocaleDateString(locale === 'en' ? 'en-GB' : 'vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }); } catch (e) { return iso || ''; } };
  const fallbackImages = ['/product-images/017-photo-1496181133206-80ce9b88a853-32b1f75b24ba.webp', '/product-images/029-photo-1597872200969-2b65d56bd16b-c1156a99f9f4.webp', '/product-images/019-photo-1516321318423-f06f85e504b3-35654f97d864.webp', '/product-images/028-photo-1593642632823-8f785ba67e45-6dc880aa0936.webp', '/product-images/027-photo-1588872657578-7efd1f1555ed-31988d89da28.webp', '/product-images/020-photo-1517336714731-489689fd1ca8-b23482fd7555.webp', '/product-images/023-photo-1541807084-5c52f3a3c8c8.webp', '/product-images/021-photo-1527443224154-c4a3942d3acf-aebf60d7d3c1.webp', '/product-images/025-photo-1555066931-4365d14bab8c-edac264c4fba.webp', '/product-images/030-photo-1609091839311-d5365f9ff1c5-c0c85440345d.webp', '/product-images/016-photo-1484788984921-03950022c9ef-86eba85350c8.webp', '/product-images/018-photo-1515879218367-8466d910aaa4-c45f51f17907.webp'];
  const tagLabel = (slug, locale) => {
    const map = {
      vi: { office: 'Kinh nghiệm', ram: 'Nâng cấp', battery: 'Bảo dưỡng', ssd: 'Công nghệ', test: 'Checklist', student: 'Sinh viên', screen: 'Màn hình', programmer: 'Lập trình', runtime: 'Pin laptop', business: 'Doanh nhân', windows: 'Windows', cooler: 'Bảo dưỡng' },
      en: { office: 'Tips', ram: 'Upgrade', battery: 'Maintenance', ssd: 'Tech', test: 'Checklist', student: 'Student', screen: 'Display', programmer: 'Programming', runtime: 'Battery', business: 'Business', windows: 'Windows', cooler: 'Maintenance' }
    };
    return (map[locale] && map[locale][slug]) || (locale === 'en' ? 'Article' : 'Bài viết');
  };
  const articleList = posts.map((post, index) => {
    const meta = post.meta || {};
    const isEn = lang === 'en';
    const rawContent = isEn ? meta._oscar_content_en : (post.content && post.content.rendered);
    const rawExcerpt = isEn ? meta._oscar_excerpt_en : (post.excerpt && post.excerpt.rendered);
    const title = isEn ? (meta._oscar_title_en || stripHtml(post.title.rendered)) : stripHtml(post.title.rendered);
    const desc = stripHtml(rawExcerpt) || stripHtml(rawContent);
    const bullets = extractBullets(rawContent);
    const query = meta._oscar_query || '';
    const featured = post._embedded && post._embedded['wp:featuredmedia'] && post._embedded['wp:featuredmedia'][0];
    const image = (featured && featured.source_url) || fallbackImages[index % fallbackImages.length];
    return { id: post.id, title, desc, bullets, image, tag: tagLabel(query, lang), date: parseDate(post.date, lang), query, link: post.link };
  });
  const articleListFinal = fetchError ? t.techArticles.map((article, index) => ({ ...article, image: fallbackImages[index % fallbackImages.length] })) : articleList;
  const [activeTag, setActiveTag] = useState(t.allPosts);
  const [selectedArticle, setSelectedArticle] = useState(null);
  const tags = [t.allPosts, ...Array.from(new Set(articleListFinal.map((article) => article.tag).filter(Boolean)))];
  const visible = activeTag === t.allPosts ? articleListFinal : articleListFinal.filter((article) => article.tag === activeTag);
  useEffect(() => {
    if (!selectedArticle) return undefined;
    const closeOnEscape = (event) => { if (event.key === 'Escape') setSelectedArticle(null); };
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [selectedArticle]);
  return <section className="section shell tech-articles expanded-blog article-list-section" id="blog"><div className="section-heading split-heading"><div><span className="eyebrow">{t.blogEyebrow}</span><h2>{t.blogTitle}</h2></div><p>{t.blogDesc}</p></div>{loading && <div className="article-list-loading" style={{ padding: '2rem', textAlign: 'center', color: '#888' }}>{t.loadingPosts || 'Loading...'}</div>}<div className="article-tabs">{tags.map((tag) => <button className={activeTag === tag ? 'active' : ''} key={tag} onClick={() => setActiveTag(tag)}>{tag}</button>)}</div><div className="article-list">{visible.map((post) => <article key={post.id || post.title} role="link" tabIndex={0} aria-label={`${t.readArticle} ${post.title}`} onClick={() => { window.location.href = post.link; }} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); window.location.href = post.link; } }}><figure><img src={normalizeImagePath(post.image)} alt={post.title} loading="lazy" onError={productImageFallback} /></figure><div className="article-list-content"><div><span>{post.tag}</span><small>{post.date}</small></div><h3>{post.title}</h3><p>{post.desc}</p>{post.bullets.length > 0 && <ul>{post.bullets.slice(0, 2).map((item, idx) => <li key={`${idx}-${item.slice(0, 20)}`}>{item}</li>)}</ul>}</div></article>)}</div>{selectedArticle && <div className="article-modal-backdrop" onClick={() => setSelectedArticle(null)}><article className="article-modal" role="dialog" aria-modal="true" aria-labelledby="article-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" aria-label={t.closeArticle} onClick={() => setSelectedArticle(null)}><X size={18} /></button><img className="article-modal-image" src={normalizeImagePath(selectedArticle.image)} alt={selectedArticle.title} onError={productImageFallback} /><span>{selectedArticle.tag} • {selectedArticle.date}</span><h2 id="article-modal-title">{selectedArticle.title}</h2><p>{selectedArticle.desc}</p>{selectedArticle.bullets.length > 0 && <ul>{selectedArticle.bullets.map((item, idx) => <li key={`${idx}-${item.slice(0, 20)}`}>{item}</li>)}</ul>}<p className="article-note">{t.articleNote}</p></article></div>}</section>;
}


function ServiceSection({ lang, t }) { return <section className="repair" id="service"><div className="shell repair-layout"><div className="repair-panel"><span className="eyebrow"><Wrench size={16} /> {t.serviceEyebrow}</span><h2>{t.repairTitle}</h2><p>{t.repairDesc}</p><span className="primary light-button phone-display">{t.callTech}: {contacts.warranty}</span></div><div className="service-list">{services.map((service, index) => <article className="service-card" key={text(service.title, lang)}><span>0{index + 1}</span><div><h3>{text(service.title, lang)}</h3><p>{text(service.desc, lang)}</p></div><strong>{text(service.price, lang)}</strong></article>)}</div></div></section>; }
function StoreLocator({ lang, t }) {
  const mapEmbedUrl = `https://maps.google.com/maps?q=${encodeURIComponent(contacts.address)}&z=16&output=embed`;
  return <section className="section shell store-locator" id="store-locator"><div className="section-heading"><span className="eyebrow">{t.storeLocator}</span><h2>{t.storeTitle}</h2></div><div className="store-map"><div className="map-frame"><iframe title={t.storeMapTitle} src={mapEmbedUrl} loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen /></div><div>{branches.map((branch) => <article key={branch.name}><h3>{branch.name}</h3><p>{branch.address}</p><span>{text(contacts.hours, lang)}</span><a className="direction-link" href={branch.mapUrl || contacts.mapUrl} target="_blank" rel="noreferrer">{t.direction}</a></article>)}</div></div></section>;
}
function ContactSection({ lang, t }) { return <section className="contact-section" id="contact"><div className="shell contact-layout"><div className="contact-card main-contact"><span className="eyebrow"><Store size={16} /> Laptop OSCAR Thủ Đức</span><h2>{t.contactTitle}</h2><div className="contact-lines"><p><Phone size={18} /> {t.salesHotline}: <strong>{contacts.hotline}</strong></p><p><Wrench size={18} /> {t.repairHotline}: <strong>{contacts.warranty}</strong></p><p><Mail size={18} /> Email: <strong>{contacts.email}</strong></p><p><MapPin size={18} /> {t.mainAddress}: <strong>{contacts.address}</strong></p></div><small>{t.openHours}: {text(contacts.hours, lang)}</small></div><div className="branch-list">{branches.map((branch) => <article className="contact-card" key={branch.name}><h3>{branch.name}</h3><p>{branch.address}</p><span className="branch-phone">{branch.phone}</span><a className="direction-link" href={branch.mapUrl || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(branch.address)}`} target="_blank" rel="noreferrer">{t.direction}</a></article>)}</div></div></section>; }
// Boss 2026-08-24: Footer and MobileCommerce components removed.
// Footer is now rendered by PHP (template-parts/footer-business.php)
// via get_footer() in single.php/index.php. Bottom mobile nav is
// oscar-bottom-nav in the same PHP template. SPA no longer renders
// either — avoids duplicate nav + duplicate scroll-to-top button.
//
// See git history for the components that were here.

function RootBoundary() {
  // Boss 2026-08-01: locationKey forces App (not ErrorBoundary) to remount on
  // navigation, clearing any sticky route-local state (filters, openProduct, etc.).
  //
  // Boss 2026-08-04: keep ErrorBoundary mounted across navigation. Earlier this
  // function used `key={locationKey}` on <ErrorBoundary>, which forced the entire
  // boundary subtree to remount on every URL change. After a lazy route
  // (/#warranty, /#policy, /#admin) had triggered an error inside the chunk
  // (font 404 etc.) and ErrorBoundary caught it, remounting the boundary on the
  // way back to `/` left a STALE <main id="top"> in the DOM next to the new
  // one — 2 mains stacked, page height 2× normal (Bug #6).
  //
  // New approach:
  //   - <ErrorBoundary ref={ref}> stays mounted. Its `error` state is reset
  //     imperatively via `ref.current.resetError()` on URL change.
  //   - <App key={locationKey}> still remounts on URL change, so per-route
  //     React state (filters, currentPage, openProduct, etc.) resets cleanly.
  //   - Net effect: ErrorBoundary doesn't unmount → no stale DOM leak; App
  //     still gets a fresh tree per route.
  const [locationKey, setLocationKey] = useState(() => window.location.pathname + window.location.hash);
  const errorBoundaryRef = useRef(null);

  useEffect(() => {
    const sync = () => {
      const nextKey = window.location.pathname + window.location.hash;
      setLocationKey((prev) => {
        if (prev === nextKey) return prev;
        // Reset ErrorBoundary error state without remounting it.
        if (errorBoundaryRef.current && typeof errorBoundaryRef.current.resetError === 'function') {
          errorBoundaryRef.current.resetError();
        }
        return nextKey;
      });
    };
    const onHashClick = (event) => {
      const a = event.target.closest && event.target.closest('a[href]');
      if (!a) return;
      const href = a.getAttribute('href');
      if (!href || !href.startsWith('#') || href.length <= 1) return;
      // Boss 2026-08-01: hash-only link — normalize URL to /<hash> (no path pollution).
      // Without this, clicking #products while on /cart yields /cart#products.
      event.preventDefault();
      const targetHash = href.slice(1);
      window.history.pushState(null, '', '/#' + targetHash);
      window.dispatchEvent(new HashChangeEvent('hashchange'));
    };
    // Boss 2026-08-01: on cold-load of any non-root path (/cart, /gio-hang, /foo),
    // clean the URL back to / so SPA route stays canonical.
    // Boss 2026-08-06: /san-pham/ is the canonical product-detail deep link — keep it.
    // Without this guard, refreshing on a product detail page would rewrite the URL
    // to / while the SPA stayed on product-detail (state already resolved from pathname),
    // so the address bar showed home but the screen showed a product. Refresh again
    // and the home page actually loaded.
    const coldPath = window.location.pathname;
    if (coldPath !== '/' && coldPath !== '' && !coldPath.startsWith('/san-pham/')) {
      history.replaceState(null, '', '/' + (window.location.hash || ''));
    }
    window.addEventListener('hashchange', sync);
    window.addEventListener('popstate', sync);
    document.addEventListener('click', onHashClick, true);
    return () => {
      window.removeEventListener('hashchange', sync);
      window.removeEventListener('popstate', sync);
      document.removeEventListener('click', onHashClick, true);
    };
  }, []);

  return (
    <ErrorBoundary ref={errorBoundaryRef}>
      <App key={locationKey} />
    </ErrorBoundary>
  );
}

createRoot(document.getElementById('root')).render(<RootBoundary />);

