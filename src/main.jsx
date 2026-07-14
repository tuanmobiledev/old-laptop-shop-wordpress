import React, { Suspense, useEffect, useMemo, useState } from 'react';
import { createPortal } from 'react-dom';
import { createRoot } from 'react-dom/client';
import { CheckCircle2, ClipboardCheck, Cpu, LayoutGrid, HardDrive, Headphones, Mail, MapPin, Menu, MessageCircle, Monitor, Rows3, PackageCheck, Phone, Search, Share2, ShieldCheck, SlidersHorizontal, Sparkles, Store, Truck, Wrench, X, Zap } from 'lucide-react';
import { banners, branches, contacts, formatCurrency, products, services } from './data.js';
import { copy, demandLabels, filterOptions } from './catalogConfig.js';
import { discount, matchesCpuFamily, matchesDemand, matchesGpuFamily, matchesScreenSize, matchesSearchQuery, text, isDiscreteGpu } from './productUtils.js';
import { initGA, productParams, trackEvent, trackPageView } from './tracking.js';
import { productMediaMap } from './product-media-map.js';
import './styles.css';
import './upgrade-builder.css';
import './config-options.css';
import './config-sheet.css';

const AdminProductsPage = React.lazy(() => import('./AdminProductsPage.jsx'));
const SalesPolicyPage = React.lazy(() => import('./SalesPolicyPage.jsx'));
import ErrorBoundary from './ErrorBoundary.jsx';

const STORAGE_KEYS = { products: 'oscar-products-v2' };

const themeAssetUrl = (path) => {
  if (typeof path !== 'string' || !path.startsWith('/')) return path;
  const themeUrl = window.OSCAR_WP?.themeUrl?.replace(/\/$/, '');
  if (!themeUrl) return path;
  if (path.startsWith('/product-images/')) {
    const filename = path.slice('/product-images/'.length).split(/[?#]/)[0];
    return productMediaMap[filename] || `${themeUrl}/assets/images/products/${filename}`;
  }
  if (path === '/oscar-cover.webp' || path === '/oscar-avatar.webp') return `${themeUrl}/assets/images${path}`;
  return path;
};
const normalizeImagePath = (path) => themeAssetUrl(typeof path === 'string' && path.startsWith('/product-images/') ? path.replace(/\.jpg(?=($|[?#]))/i, '.webp') : path);
const uniqueList = (items) => [...new Set((items || []).filter(Boolean))];
const normalizeProductImages = (items) => Array.isArray(items)
  ? items.map((product) => {
    const catalogProduct = products.find((item) => item.id === product.id) || products.find((item) => item.name === product.name) || {};
    const images = uniqueList([
      ...(Array.isArray(product.images) ? product.images : []),
      product.image,
      ...(Array.isArray(catalogProduct.images) ? catalogProduct.images : []),
      catalogProduct.image,
    ].map(normalizeImagePath));
    const image = normalizeImagePath(product.image || images[0]) || '/oscar-cover.webp';
    return { ...product, image, images: uniqueList([image, ...images]), video: product.video || '' };
  })
  : products;
const productImageFallback = (event) => {
  const img = event.currentTarget;
  if (img.dataset.fallbackApplied === 'true') return;
  img.dataset.fallbackApplied = 'true';
  const fallback = products.find((product) => product.name === img.alt)?.image || '/oscar-cover.webp';
  img.src = normalizeImagePath(fallback);
};

const slugify = (value) => String(value || '')
  .toLowerCase()
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/đ/g, 'd')
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '')
  .slice(0, 90);
const productPath = (product) => `/san-pham/${slugify(product.name)}-p${product.id}`;
const productIdFromPath = () => {
  if (typeof window === 'undefined') return null;
  const match = window.location.pathname.match(/^\/san-pham\/.+-p(\d+)\/?$/);
  return match ? Number(match[1]) : null;
};

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
  const routeFromHash = () => {
    const hash = window.location.hash.replace('#', '');
    if (window.location.pathname.startsWith('/san-pham/') && !hash) return 'product-detail';
    const route = hash || 'products';
    return route.startsWith('policy-') ? 'policy' : route;
  };

  const [page, setPage] = useState(routeFromHash);
  const t = copy[lang];
  useEffect(() => { localStorage.setItem(STORAGE_KEYS.products, JSON.stringify(normalizeProductImages(managedProducts))); }, [managedProducts]);
  useEffect(() => {
    const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
    fetch(`${restBase}products`)
      .then((response) => response.ok ? response.json() : Promise.reject())
      .then((remoteProducts) => setManagedProducts(normalizeProductImages(remoteProducts)))
      .catch(() => {});
  }, []);
  useEffect(() => {
    initGA();
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
    const target = scrollTargets[page];
    if (!target) return undefined;
    const timer = window.setTimeout(() => {
      document.getElementById(target)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 120);
    return () => window.clearTimeout(timer);
  }, [page]);
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
      window.history.pushState({ productDetail: product.id }, '', productPath(product));
      setPage('product-detail');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  };
  const closeProduct = () => {
    setSelectedProduct(null);
    if (typeof window !== 'undefined' && window.location.pathname.startsWith('/san-pham/')) {
      window.history.pushState({}, '', '/#products');
      setPage('products');
    }
  };

  const options = useMemo(() => filterOptions, []);
  const filteredProducts = useMemo(() => {
    const result = managedProducts.filter((product) => {
      if (product.category === 'phu-kien') return false;
      return matchesSearchQuery(product, lang, filters.query) && (filters.category === 'all' || product.category === filters.category) && (filters.brand === 'all' || product.brand === filters.brand || (filters.brand === 'Other' && !filterOptions.brand.slice(1, -1).includes(product.brand))) && matchesCpuFamily(product.cpu, filters.cpu) && matchesGpuFamily([product.gpu, ...(product.variants || []).map((variant) => variant.gpu)].filter(Boolean).join(' '), filters.gpu) && matchesScreenSize(product.screen, filters.screen) && matchesDemand(product.demand, filters.demand);
    });
    return result.sort((a, b) => filters.sortBy === 'price-asc' ? a.price - b.price : filters.sortBy === 'price-desc' ? b.price - a.price : filters.sortBy === 'name-asc' ? a.name.localeCompare(b.name) : managedProducts.indexOf(a) - managedProducts.indexOf(b));
  }, [filters, lang, managedProducts]);

  const pages = {
    home: <><Hero lang={lang} t={t} /><TrustStrip t={t} /><Catalog filteredProducts={filteredProducts} filterOpen={filterOpen} filters={filters} lang={lang} options={options} resetFilters={resetFilters} setFilter={setFilterValue} setFilterOpen={setFilterOpen} setSelectedProduct={openProduct} t={t} /></>,
    products: <Catalog filteredProducts={filteredProducts} filterOpen={filterOpen} filters={filters} lang={lang} options={options} resetFilters={resetFilters} setFilter={setFilterValue} setFilterOpen={setFilterOpen} setSelectedProduct={openProduct} t={t} />, 
    'product-detail': <ProductDetailPage lang={lang} onClose={closeProduct} product={selectedProduct} productList={managedProducts} setProduct={openProduct} t={t} />, 
    about: <AboutPage t={t} />,
    blog: <TechArticles setFilter={setFilterValue} t={t} />,
    service: <ServiceSection lang={lang} t={t} />,
    warranty: <Suspense fallback={<div className="shell" style={{ padding: '4rem 0', textAlign: 'center' }}>Loading…</div>}><SalesPolicyPage initialSection="policy-warranty" t={t} /></Suspense>,
    returns: <Suspense fallback={<div className="shell" style={{ padding: '4rem 0', textAlign: 'center' }}>Loading…</div>}><SalesPolicyPage initialSection="policy-return" t={t} /></Suspense>,
    delivery: <Suspense fallback={<div className="shell" style={{ padding: '4rem 0', textAlign: 'center' }}>Loading…</div>}><SalesPolicyPage initialSection="policy-delivery" t={t} /></Suspense>,
    policy: <Suspense fallback={<div className="shell" style={{ padding: '4rem 0', textAlign: 'center' }}>Loading…</div>}><SalesPolicyPage t={t} /></Suspense>,
    contact: <><StoreLocator lang={lang} t={t} /><ContactSection lang={lang} t={t} /></>,
    admin: <Suspense fallback={<div className="shell" style={{ padding: '4rem 0', textAlign: 'center' }}>Loading…</div>}><AdminProductsPage products={managedProducts} setProducts={setManagedProducts} t={t} /></Suspense>,
  };

  return <main id="top"><Header filterOpen={filterOpen} filters={filters} lang={lang} page={page} productList={managedProducts} setFilter={setFilterValue} setFilterOpen={setFilterOpen} setLang={setLang} setSelectedProduct={openProduct} t={t} />{pages[page] || pages.products}<Footer t={t} /><ContactFloat t={t} /><MobileCommerce page={page} t={t} /></main>;
}

function Header({ filterOpen, filters, lang, page, productList, setFilter, setFilterOpen, setLang, setSelectedProduct, t }) {
  const [searchOpen, setSearchOpen] = useState(false);
  const suggestions = searchOpen && filters.query ? productList.filter((product) => matchesSearchQuery(product, lang, filters.query)).slice(0, 5) : [];
  const canToggleFilter = page === 'home' || page === 'products';
  const brandHref = page === 'product-detail' ? '/#products' : '#home';
  const chooseProduct = (product) => { setSelectedProduct(product); setSearchOpen(false); };
  const chooseKeyword = (key) => { setFilter('query', key); setSearchOpen(false); };
  const toggleFilter = () => { setFilterOpen((open) => !open); window.requestAnimationFrame(() => document.getElementById('products')?.scrollIntoView({ behavior: 'smooth', block: 'start' })); };
  return <header className="site-header pro-header"><div className="utility"><div className="shell utility-inner"><span><Sparkles size={14} /> {t.topDeal}</span><a href="#contact">{t.topStore}</a><a href="#policy">{t.topPolicy}</a></div></div><div className="topbar"><div className="shell nav-shell">{canToggleFilter && <button className={`menu-filter-toggle ${filterOpen ? 'active' : ''}`} aria-label={filterOpen ? t.hideFilters : t.showFilters} title={t.productFilters} onClick={toggleFilter} type="button"><SlidersHorizontal size={21} /></button>}<a className="brand" href={brandHref}><img className="brand-icon" src={themeAssetUrl('/oscar-avatar.webp')} alt="" aria-hidden="true" /><span><strong>OSCAR Thủ Đức</strong><small>{page === 'product-detail' ? t.backToList : t.techPartner}</small></span></a><div className="global-search search-wrap" onBlur={(event) => { if (!event.currentTarget.contains(event.relatedTarget)) setSearchOpen(false); }}><Search size={18} /><input value={filters.query} onFocus={() => setSearchOpen(true)} onKeyDown={(event) => { if (event.key === 'Escape') setSearchOpen(false); }} onChange={(event) => { setFilter('query', event.target.value); setSearchOpen(true); }} placeholder={t.searchPlaceholder} aria-label={t.searchProductsLabel} />{suggestions.length > 0 && <div className="search-suggestions rich-search">{suggestions.map((product) => <button key={product.id} onMouseDown={(event) => event.preventDefault()} onClick={() => chooseProduct(product)} aria-label={`${t.viewProductDetail} ${product.name}`}><img src={normalizeImagePath(product.image)} alt="" loading="lazy" onError={productImageFallback} /><span>{product.name}<small>{product.brand} • {product.cpu}</small></span><strong>{formatCurrency(product.price)}</strong></button>)}<div className="popular-keywords">{t.popularKeywords.map((key) => <button key={key} onMouseDown={(event) => event.preventDefault()} onClick={() => chooseKeyword(key)}>{key}</button>)}</div></div>}</div><span className="header-action hotline" aria-label={t.hotlineLabel}><Phone size={17} />{contacts.hotline}</span><button className="language-toggle" aria-label={t.switchLanguage} title={t.switchLanguage} onClick={() => setLang(lang === 'vi' ? 'en' : 'vi')}><span className={`language-flag ${lang === 'vi' ? 'flag-vn' : 'flag-us'}`} aria-hidden="true"><span></span></span><span>{lang === 'vi' ? 'VI' : 'EN'}</span></button></div></div><nav className="category-menu simple-nav"><div className="shell"><a href="#products">{t.navProducts}</a><a href="#service">{t.navRepair}</a><a href="#blog">{t.navBlog}</a><a href="#contact">{t.contact}</a></div></nav></header>;
}
function Hero({ lang, t }) { return <section className="hero shell"><div className="hero-copy"><span className="eyebrow"><Sparkles size={16} /> {t.heroEyebrow}</span><h1>{t.heroTitle}</h1><p>{t.heroDesc}</p><div className="hero-specs"><span><b>12</b> {t.heroBrands}</span><span><b>{t.heroSteps}</b> {t.heroChecks}</span><span><b>24h</b> {t.heroCityDelivery}</span></div><div className="hero-actions"><a className="primary" href="#products">{t.viewProducts}</a><span className="secondary phone-display">{t.bookRepair}: {contacts.hotline}</span></div></div><div className="banner-stack">{banners.map((banner, index) => <article className={`promo-banner tone-${index}`} key={text(banner.title, lang)}><small>{index === 0 ? t.catalogPick : index === 1 ? t.upgradeLab : t.payment}</small><span>{text(banner.title, lang)}</span><p>{text(banner.desc, lang)}</p><strong>{text(banner.cta, lang)}</strong></article>)}</div></section>; }
function TrustStrip({ t }) { const items = [{ icon: ClipboardCheck, title: t.checked, meta: t.fiveStepTest }, { icon: ShieldCheck, title: t.warranty, meta: t.warrantyMonths }, { icon: Truck, title: t.delivery, meta: t.sameDay }, { icon: Headphones, title: t.support, meta: '9:00-21:00' }]; return <section className="trust shell">{items.map(({ icon: Icon, title, meta }) => <article key={title}><Icon size={22} /><div><strong>{title}</strong><span>{meta}</span></div></article>)}</section>; }

function AboutPage({ t }) {
  const icons = [ShieldCheck, PackageCheck, Headphones];
  const values = t.aboutValues.map((item, index) => ({ ...item, icon: icons[index] }));
  return <section className="about-page shell footer-about-page" id="about"><div className="about-hero"><span className="eyebrow"><Sparkles size={16} /> {t.aboutEyebrow}</span><h1>{t.aboutTitle}</h1><p>{t.aboutDescLong}</p><div className="about-actions"><a className="primary" href="#products">{t.aboutCtaProducts}</a><a className="secondary" href="#contact">{t.aboutCtaContact}</a></div></div><div className="about-story">{t.aboutStory.map((item) => <article key={item.title}><h2>{item.title}</h2><p>{item.desc}</p></article>)}</div><div className="about-values">{values.map(({ icon: Icon, title, desc }) => <article key={title}><Icon size={24} /><h3>{title}</h3><p>{desc}</p></article>)}</div></section>;
}

function Catalog({ filteredProducts, filterOpen, filters, lang, options, resetFilters, setFilter, setFilterOpen, setSelectedProduct, t }) {
  const [page, setPage] = useState(1);
  const [viewMode, setViewMode] = useState('grid');
  const active = Object.entries(filters).filter(([key, value]) => key !== 'sortBy' && value && value !== 'all');
  const perPage = 12;
  const pageCount = Math.max(1, Math.ceil(filteredProducts.length / perPage));
  const safePage = Math.min(page, pageCount);
  const pagedProducts = filteredProducts.slice((safePage - 1) * perPage, safePage * perPage);
  const setFilterAndPage = (key, value) => { setPage(1); setFilter(key, value); };
  const resetAll = () => { setPage(1); resetFilters(); };
  const chipNames = { brand: t.filterBrand, cpu: 'CPU', gpu: 'GPU', screen: t.screenLabel, demand: t.demand, category: t.category };
  const gpuLabels = { 'gpu-roi': t.filterGpuDiscrete, workstation: t.filterWorkstation, 'GTX/MX': 'GTX/MX', Radeon: 'Radeon', onboard: t.filterOnboard, 'Intel Arc': 'Intel Arc' };
  const screenLabels = { 12: '12" / 12.4"', 13: '13" / 13.3" / 13.4"', 14: '14" / 14.5"', 15: '15" / 15.6"', 16: '16"', 17: '17" / 17.3"', 18: '18"' };
  const sortLabels = { featured: t.sortFeatured, 'price-asc': t.sortPriceAsc, 'price-desc': t.sortPriceDesc, 'name-asc': t.sortNameAsc };
  const valueLabel = (key, value) => key === 'demand' ? (demandLabels[lang]?.[value] || value) : key === 'gpu' ? gpuLabels[value] || value : key === 'screen' ? screenLabels[value] || value : key === 'sortBy' ? sortLabels[value] || value : value;
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
  const filterPanel = <aside className={`filter-panel advanced checkbox-filter product-filter-sidebar compact-filter-panel android-filter-drawer ${filterOpen ? 'open' : 'closed'}`} aria-hidden={!filterOpen}><div className="drawer-grip" /><div className="filter-drawer-head"><div><strong>{t.productFilters}</strong><span>{t.filterChooseConfig}</span></div><button className="filter-close" aria-label={t.closeFilters} onClick={() => setFilterOpen(false)}><X size={18} /></button></div><div className="compact-filter-stack drawer-filter-body">{selectGroup(t.filterBrand, 'brand', options.brand)}{selectGroup('CPU', 'cpu', options.cpu)}{selectGroup('GPU', 'gpu', options.gpu)}{selectGroup(t.screenLabel, 'screen', options.screen)}{selectGroup(t.demand, 'demand', options.demand)}{selectGroup(t.sort, 'sortBy', ['featured', 'price-asc', 'price-desc', 'name-asc'])}</div><div className="filter-sheet-actions drawer-filter-footer"><button className="clear-filter" onClick={resetAll}>{t.clear}</button><button className="apply-filter" onClick={() => setFilterOpen(false)}>{t.applyFilterPrefix} {filteredProducts.length} {t.productCount}</button></div></aside>;

  return <section className="section shell catalog tech-catalog" id="products"><div className="breadcrumb">{t.homeBreadcrumb} / {t.catalogBreadcrumb} / {t.mobileProducts}</div><div className="section-heading split-heading"><div><span className="eyebrow"><SlidersHorizontal size={16} /> {t.catalogEyebrow}</span><h2>{t.catalogTitle}</h2></div>{t.catalogDesc && <p>{t.catalogDesc}</p>}</div><div className="mobile-filter-strip"><button className="android-filter-trigger" type="button" onClick={() => { trackEvent('filter_open', { source: 'mobile_drawer' }); setFilterOpen(true); }}><SlidersHorizontal size={17} /> {t.filterShort}{filterCount ? ` · ${filterCount}` : ''}</button><div className="quick-filter-chips">{quickChips.map(([key, value, label]) => <button key={`${key}-${value}`} className={filters[key] === value ? 'active' : ''} type="button" onClick={() => setFilterAndPage(key, filters[key] === value ? 'all' : value)}>{label}</button>)}</div></div><div className="sort-bar catalog-toolbar"><span>{filteredProducts.length} {t.productCount}</span><select value={filters.sortBy} onChange={(e) => setFilterAndPage('sortBy', e.target.value)}><option value="featured">{t.featured}</option><option value="price-asc">{t.priceAsc}</option><option value="price-desc">{t.priceDesc}</option><option value="name-asc">{t.nameAsc}</option></select><div className="view-toggle" aria-label={t.displayMode}><button className={viewMode === 'grid' ? 'active' : ''} aria-label={t.gridView} title={t.grid} onClick={() => setViewMode('grid')}><LayoutGrid size={19} strokeWidth={2.4} /></button><button className={viewMode === 'list' ? 'active' : ''} aria-label={t.listView} title={t.list} onClick={() => setViewMode('list')}><Rows3 size={19} strokeWidth={2.4} /></button></div></div><div className="active-chips">{active.map((entry) => <button key={entry.join('-')} onClick={() => setFilterAndPage(entry[0], entry[0] === 'query' ? '' : 'all')}>{chipLabel(entry)} <X size={13} /></button>)}{active.length > 0 && <button className="clear-chip" onClick={resetAll}>{t.clearAll}</button>}</div>{filterOpen && <button className="filter-scrim android-drawer-scrim" aria-label={t.closeFilters} onClick={() => setFilterOpen(false)} />}<div className={`catalog-layout ${filterOpen ? 'filters-visible' : 'filters-hidden'}`}>{filterPanel}<div className="product-area"><div className={`product-grid ${viewMode === 'list' ? 'list-mode' : ''}`}>{pagedProducts.length ? pagedProducts.map((product) => <ProductCard product={product} lang={lang} t={t} key={product.id} setSelectedProduct={setSelectedProduct} />) : <div className="empty-state catalog-empty"><Search size={38} /><h3>{t.noResults}</h3><p>{t.noResultsDesc}</p><div><button onClick={resetAll}>{t.noResultsClear}</button><span className="phone-display">Hotline: {contacts.hotline}</span></div></div>}</div>{pagedProducts.length > 0 && <div className="pagination">{Array.from({ length: pageCount }).map((_, index) => <button className={safePage === index + 1 ? 'active' : ''} key={index} onClick={() => setPage(index + 1)}>{index + 1}</button>)}</div>}</div></div></section>;
}

function ProductCard({ product, lang, t, setSelectedProduct, compact = false }) {
  const openDetail = () => setSelectedProduct(product, compact ? 'related_product' : 'product_card');
  const cpuFromName = product.name.match(/(?:i[3579]|Core\s*i[3579]|Ryzen\s*[3579]|Ultra\s*[579]|Xeon)[-\s]?[A-Z0-9]{3,6}[A-Z]?/i)?.[0];
  const cpuLabel = (cpuFromName || (product.cpu && product.cpu !== 'N/A' && product.cpu !== 'Liên hệ' ? product.cpu : t.updatedSoon)).replace(/^Core\s+/i, '').replace(/^(i[3579])\s+(\d)/i, '$1-$2');
  const gpuLabel = isDiscreteGpu(product.gpu) ? product.gpu : '';
  const ramLabel = product.ram && product.ram !== 'N/A' && product.ram !== 'Liên hệ' ? product.ram : '8GB';
  const ssdLabel = product.ssd && product.ssd !== 'N/A' && product.ssd !== 'Liên hệ' ? product.ssd.replace(/(\d)(GB|TB)$/i, '$1 $2') : '256 GB';
  const screenLabel = product.screen && product.screen !== 'N/A' && product.screen !== 'Liên hệ' ? product.screen : t.updatedSoon;
  const storageLabel = `${ramLabel} / ${ssdLabel}`;
  const specRows = [
    { label: 'CPU', value: cpuLabel, icon: <Cpu size={14} /> },
    gpuLabel ? { label: 'GPU', value: gpuLabel, icon: <Zap size={14} /> } : null,
    { label: 'RAM/SSD', value: storageLabel, icon: <HardDrive size={14} /> },
    { label: t.screenLabel, value: screenLabel, icon: <Monitor size={14} /> },
  ].filter(Boolean);
  return <article className={`product-card showcase-card ${compact ? 'compact' : ''}`} onClick={openDetail} role="button" tabIndex="0" aria-label={`${t.viewProductDetail} ${product.name}`} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); openDetail(); } }}><div className="product-art" style={{ '--accent': product.color }}><span className="deal-badge">-{discount(product)}%</span><img src={normalizeImagePath(product.image)} alt={product.name} loading="lazy" onError={productImageFallback} /></div><div className="product-body"><div className="product-title-row"><div><h3 title={product.name}>{product.name}</h3></div></div><div className="spec-lines">{specRows.map((item) => <span key={item.label}>{item.icon}<small>{item.label}</small><b title={item.value}>{item.value}</b></span>)}</div><div className="price-row"><div><strong>{formatCurrency(product.price)}</strong><del>{formatCurrency(product.oldPrice)}</del></div></div></div></article>;
}

function ContactFloat({ t }) { return <aside className="contact-float" aria-label={t.quickContact}><a className="zalo" href={contacts.zalo} target="_blank" rel="noreferrer" aria-label={t.contactZaloLabel}>Zalo</a><a className="messenger" href={contacts.facebook} target="_blank" rel="noreferrer" aria-label={t.contactMessengerLabel}><MessageCircle size={24} /></a></aside>; }
function TechArticles({ setFilter, t }) {
  const articles = t.techArticles;
  const articleImages = [
    '/product-images/017-photo-1496181133206-80ce9b88a853-32b1f75b24ba.webp',
    '/product-images/029-photo-1597872200969-2b65d56bd16b-c1156a99f9f4.webp',
    '/product-images/019-photo-1516321318423-f06f85e504b3-35654f97d864.webp',
    '/product-images/028-photo-1593642632823-8f785ba67e45-6dc880aa0936.webp',
    '/product-images/027-photo-1588872657578-7efd1f1555ed-31988d89da28.webp',
    '/product-images/020-photo-1517336714731-489689fd1ca8-b23482fd7555.webp',
    '/product-images/023-photo-1541807084-5c52b6b3adef-3bb79c3b2c8c.webp',
    '/product-images/021-photo-1527443224154-c4a3942d3acf-aebf60d7d3c1.webp',
    '/product-images/025-photo-1555066931-4365d14bab8c-edac264c4fba.webp',
    '/product-images/030-photo-1609091839311-d5365f9ff1c5-c0c85440345d.webp',
    '/product-images/016-photo-1484788984921-03950022c9ef-86eba85350c8.webp',
    '/product-images/018-photo-1515879218367-8466d910aaa4-c45f51f17907.webp',
  ];
  const articleList = articles.map((article, index) => ({ ...article, image: articleImages[index % articleImages.length] }));
  const [activeTag, setActiveTag] = useState(t.allPosts);
  const [selectedArticle, setSelectedArticle] = useState(null);
  const tags = [t.allPosts, ...Array.from(new Set(articleList.map((article) => article.tag)))];
  const visible = activeTag === t.allPosts ? articleList : articleList.filter((article) => article.tag === activeTag);
  useEffect(() => {
    if (!selectedArticle) return undefined;
    const closeOnEscape = (event) => { if (event.key === 'Escape') setSelectedArticle(null); };
    window.addEventListener('keydown', closeOnEscape);
    return () => window.removeEventListener('keydown', closeOnEscape);
  }, [selectedArticle]);
  return <section className="section shell tech-articles expanded-blog article-list-section" id="blog"><div className="section-heading split-heading"><div><span className="eyebrow">{t.blogEyebrow}</span><h2>{t.blogTitle}</h2></div><p>{t.blogDesc}</p></div><div className="article-tabs">{tags.map((tag) => <button className={activeTag === tag ? 'active' : ''} key={tag} onClick={() => setActiveTag(tag)}>{tag}</button>)}</div><div className="article-list">{visible.map((post) => <article key={post.title} role="button" tabIndex={0} aria-label={`${t.readArticle} ${post.title}`} onClick={() => setSelectedArticle(post)} onKeyDown={(event) => { if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); setSelectedArticle(post); } }}><figure><img src={normalizeImagePath(post.image)} alt={post.title} loading="lazy" onError={productImageFallback} /></figure><div className="article-list-content"><div><span>{post.tag}</span><small>{post.date}</small></div><h3>{post.title}</h3><p>{post.desc}</p><ul>{post.bullets.slice(0, 2).map((item) => <li key={item}>{item}</li>)}</ul></div></article>)}</div>{selectedArticle && <div className="article-modal-backdrop" onClick={() => setSelectedArticle(null)}><article className="article-modal" role="dialog" aria-modal="true" aria-labelledby="article-modal-title" onClick={(event) => event.stopPropagation()}><button className="modal-close" aria-label={t.closeArticle} onClick={() => setSelectedArticle(null)}><X size={18} /></button><img className="article-modal-image" src={normalizeImagePath(selectedArticle.image)} alt={selectedArticle.title} onError={productImageFallback} /><span>{selectedArticle.tag} • {selectedArticle.date}</span><h2 id="article-modal-title">{selectedArticle.title}</h2><p>{selectedArticle.desc}</p><ul>{selectedArticle.bullets.map((item) => <li key={item}>{item}</li>)}</ul><p className="article-note">{t.articleNote}</p></article></div>}</section>;
}

function ServiceSection({ lang, t }) { return <section className="repair" id="service"><div className="shell repair-layout"><div className="repair-panel"><span className="eyebrow"><Wrench size={16} /> {t.serviceEyebrow}</span><h2>{t.repairTitle}</h2><p>{t.repairDesc}</p><span className="primary light-button phone-display">{t.callTech}: {contacts.warranty}</span></div><div className="service-list">{services.map((service, index) => <article className="service-card" key={text(service.title, lang)}><span>0{index + 1}</span><div><h3>{text(service.title, lang)}</h3><p>{text(service.desc, lang)}</p></div><strong>{text(service.price, lang)}</strong></article>)}</div></div></section>; }
function StoreLocator({ lang, t }) {
  const mapEmbedUrl = `https://maps.google.com/maps?q=${encodeURIComponent(contacts.address)}&z=16&output=embed`;
  return <section className="section shell store-locator" id="store-locator"><div className="section-heading"><span className="eyebrow">{t.storeLocator}</span><h2>{t.storeTitle}</h2></div><div className="store-map"><div className="map-frame"><iframe title={t.storeMapTitle} src={mapEmbedUrl} loading="lazy" referrerPolicy="no-referrer-when-downgrade" allowFullScreen /></div><div>{branches.map((branch) => <article key={branch.name}><h3>{branch.name}</h3><p>{branch.address}</p><span>{text(contacts.hours, lang)}</span><a className="direction-link" href={branch.mapUrl || contacts.mapUrl} target="_blank" rel="noreferrer">{t.direction}</a></article>)}</div></div></section>;
}
function ContactSection({ lang, t }) { return <section className="contact-section" id="contact-details"><div className="shell contact-layout"><div className="contact-card main-contact"><span className="eyebrow"><Store size={16} /> Laptop OSCAR Thủ Đức</span><h2>{t.contactTitle}</h2><div className="contact-lines"><p><Phone size={18} /> {t.salesHotline}: <strong>{contacts.hotline}</strong></p><p><Wrench size={18} /> {t.repairHotline}: <strong>{contacts.warranty}</strong></p><p><Mail size={18} /> Email: <strong>{contacts.email}</strong></p><p><MapPin size={18} /> {t.mainAddress}: <strong>{contacts.address}</strong></p></div><small>{t.openHours}: {text(contacts.hours, lang)}</small></div><div className="branch-list">{branches.map((branch) => <article className="contact-card" key={branch.name}><h3>{branch.name}</h3><p>{branch.address}</p><span className="branch-phone">{branch.phone}</span><a className="direction-link" href={branch.mapUrl || `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(branch.address)}`} target="_blank" rel="noreferrer">{t.direction}</a></article>)}</div></div></section>; }
function Footer({ t }) {
  const [email, setEmail] = useState('');
  const [subscribed, setSubscribed] = useState(false);
  const [newsletterError, setNewsletterError] = useState('');
  const footerHref = (link) => {
    const map = {
      about: '#about', stores: '#contact', careers: `mailto:${contacts.email}?subject=${t.careerEmailSubject}`, blog: '#blog',
      warranty: '#warranty', returns: '#returns', delivery: '#delivery', repair: '#service', upgrade: '#service', cleaning: '#service', windows: '#service',
      facebook: contacts.facebook, zalo: contacts.zalo, email: `mailto:${contacts.email}`,
    };
    return map[link.hrefKey] || '#top';
  };
  const subscribe = async (event) => {
    event.preventDefault();
    setNewsletterError('');
    const cleanEmail = email.trim();
    if (!cleanEmail) return;
    try {
      const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
      const response = await fetch(`${restBase}newsletter`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email: cleanEmail }),
      });
      if (!response.ok) throw new Error('Newsletter submit failed');
      setSubscribed(true);
      setEmail('');
    } catch (error) {
      console.error(error);
      setNewsletterError(t.newsletterError);
    }
  };
  return <footer className="footer business-footer"><div className="shell footer-grid"><div><strong>Laptop OSCAR Thủ Đức</strong><p>{t.footerDesc}</p><form className="footer-subscribe" onSubmit={subscribe}><input type="email" value={email} onChange={(event) => { setEmail(event.target.value); setSubscribed(false); }} placeholder={t.newsletterPlaceholder} aria-label={t.newsletterPlaceholder} required /><button type="submit">{t.subscribe}</button></form>{subscribed && <small className="subscribe-success">{t.subscribed}</small>}{newsletterError && <small className="subscribe-error">{newsletterError}</small>}<div className="pay-badges"><span>{t.payCOD}</span><span>{t.payBanking}</span><span>{t.payVisa}</span><span>{t.payInstallment}</span></div><a href="#top">{t.backTop}</a></div>{t.footerColumns.map((col) => <div key={col.title}><h3>{col.title}</h3>{col.links.map((link) => <a href={footerHref(link)} key={link.label} target={footerHref(link).startsWith('http') ? '_blank' : undefined} rel={footerHref(link).startsWith('http') ? 'noreferrer' : undefined}>{link.label}</a>)}</div>)}</div><div className="shell footer-bottom"><span>© 2026 Laptop OSCAR Thủ Đức</span><span>{contacts.hotline}</span><a href={`mailto:${contacts.email}`}>{contacts.email}</a><span>{contacts.address}</span></div></footer>;
}
function ProductDetailPage({ lang, onClose, product, productList, setProduct, t }) {
  const [activeMedia, setActiveMedia] = useState({ type: 'image', src: normalizeImagePath(product?.image) || '/oscar-cover.webp' });
  const [selectedVariantIndex, setSelectedVariantIndex] = useState(0);
  const [copied, setCopied] = useState(false);
  const [addons, setAddons] = useState([]);
  const [selectedAddons, setSelectedAddons] = useState([]);
  const [orderForm, setOrderForm] = useState({ name: '', phone: '', note: '' });
  const [orderState, setOrderState] = useState({ loading: false, message: '', orderId: null });
  const [configOpen, setConfigOpen] = useState(false);
  useEffect(() => {
    if (!configOpen) return undefined;
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    const closeOnEscape = (event) => { if (event.key === 'Escape') setConfigOpen(false); };
    window.addEventListener('keydown', closeOnEscape);
    return () => { document.body.style.overflow = previousOverflow; window.removeEventListener('keydown', closeOnEscape); };
  }, [configOpen]);
  useEffect(() => {
    const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
    fetch(`${restBase}addons`).then((response) => response.ok ? response.json() : []).then(setAddons).catch(() => setAddons([]));
  }, []);
  useEffect(() => {
    if (!product) return undefined;
    setActiveMedia({ type: 'image', src: normalizeImagePath(product.image || product.images?.[0]) || '/oscar-cover.webp' });
    setSelectedVariantIndex(0);
    setSelectedAddons([]);
    setConfigOpen(false);
    setOrderState({ loading: false, message: '', orderId: null });
    return undefined;
  }, [product?.id, product?.image, product?.images]);
  useEffect(() => {
    if (!product) return;
    const ld = document.createElement('script');
    ld.type = 'application/ld+json';
    ld.id = 'product-ld';
    ld.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: product.name,
      description: `${product.brand} ${product.cpu} / ${product.ram} / ${product.ssd} — ${text(product.condition, lang)}`,
      image: (product.images?.length ? product.images : [product.image]).map(normalizeImagePath),
      brand: { '@type': 'Brand', name: product.brand },
      offers: {
        '@type': 'Offer',
        price: product.price,
        priceCurrency: 'VND',
        availability: 'https://schema.org/InStock',
        url: `https://maytinhthuduc.com/san-pham/${slugify(product.name)}-p${product.id}`,
      },
    });
    const prev = document.getElementById('product-ld');
    if (prev) prev.remove();
    document.head.appendChild(ld);
    return () => { const el = document.getElementById('product-ld'); if (el) el.remove(); };
  }, [product?.id, lang]);
  if (!product) return <section className="section shell product-detail-page"><div className="section-heading"><h1>{t.notFoundTitle}</h1><p>{t.notFoundDesc}</p></div><a className="primary" href="/#products" onClick={onClose}>{t.otherProducts}</a></section>;
  const variants = Array.isArray(product.variants) ? product.variants : [];
  const selectedVariant = variants[selectedVariantIndex] || null;
  const displayProduct = selectedVariant ? { ...product, ...selectedVariant } : product;
  const shareUrl = `${window.location.origin}${productPath(product)}`;
  const copyToClipboard = async (value) => {
    try {
      await navigator.clipboard.writeText(value);
      return true;
    } catch {
      return false;
    }
  };
  const shareProduct = async () => {
    const shareText = `Laptop OSCAR Thủ Đức - ${product.name}\n${t.sharePrice}: ${formatCurrency(displayProduct.price)}\n${shareUrl}`;
    const copiedOk = await copyToClipboard(shareText);
    trackEvent(copiedOk ? 'share_click' : 'clipboard_copy_failed', productParams(product, { source: 'detail_share', method: 'copy_link' }));
    setCopied(copiedOk);
    window.setTimeout(() => setCopied(false), 1800);
  };
  const similar = productList.filter((item) => item.category === product.category && item.id !== product.id).slice(0, 4);
  const upgrade = product.upgradeability || {};
  const addonAllowed = upgrade.confidence === 'high';
  const visibleAddons = addons;
  const ramType = String(upgrade.ramType || '').toUpperCase();
  const storageType = String(upgrade.storageType || '').toUpperCase();
  const compatibleRam = upgrade.ramMode === 'soldered' || !/DDR[45]/.test(ramType)
    ? []
    : visibleAddons.filter((item) => item.sku.startsWith(`RAM-LAP-${ramType.match(/DDR[45]/)?.[0]}-`));
  const compatibleSsd = upgrade.storageMode === 'service_only' || !/22(?:30|80)/.test(storageType)
    ? []
    : visibleAddons.filter((item) => item.sku.startsWith(`SSD-NVME-${storageType.match(/22(?:30|80)/)?.[0]}-`));
  const addonSections = [
    { key: 'ram', label: 'RAM', items: compatibleRam },
    { key: 'ssd', label: 'Ổ CỨNG SSD', items: compatibleSsd },
    { key: 'warranty', label: 'GÓI BẢO HÀNH', items: visibleAddons.filter((item) => item.type === 'warranty') },
  ].filter((section) => section.items.length);
  const toggleAddon = (addon) => setSelectedAddons((current) => {
    const isSameGroup = (item) => {
      if (addon.type === 'warranty') return item.type === 'warranty';
      if (addon.sku.startsWith('RAM-')) return item.sku.startsWith('RAM-');
      if (addon.sku.startsWith('SSD-')) return item.sku.startsWith('SSD-');
      return false;
    };
    const isSingleChoice = addon.type === 'warranty' || addon.sku.startsWith('RAM-') || addon.sku.startsWith('SSD-');
    if (current.includes(addon.wooId)) return current.filter((id) => id !== addon.wooId);
    const base = isSingleChoice ? current.filter((id) => {
      const item = addons.find((entry) => entry.wooId === id);
      return item && !isSameGroup(item);
    }) : current;
    return [...base, addon.wooId];
  });
  const chosenAddons = addons.filter((addon) => selectedAddons.includes(addon.wooId));
  const orderTotal = Number(displayProduct.price || 0) + chosenAddons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
  const configurationText = [`${product.name} - ${formatCurrency(displayProduct.price)}`, selectedVariant ? `Phiên bản: ${[selectedVariant.cpu, selectedVariant.ram, selectedVariant.ssd, selectedVariant.screen].filter(Boolean).join(' / ')}` : null, ...chosenAddons.map((addon) => `${addon.name}: ${formatCurrency(addon.price)}`), `Tạm tính: ${formatCurrency(orderTotal)}`, shareUrl].filter(Boolean).join('\n');
  const contactWithConfiguration = async (url, method) => {
    await copyToClipboard(configurationText);
    trackEvent('configuration_contact', productParams(product, { method, addon_count: chosenAddons.length }));
    window.open(url, '_blank', 'noopener,noreferrer');
  };
  const submitOrder = async (event) => {
    event.preventDefault(); setOrderState({ loading: true, message: '', orderId: null });
    try {
      const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
      const response = await fetch(`${restBase}orders`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: orderForm.name, phone: orderForm.phone, note: orderForm.note, productIds: [product.wooId, ...selectedAddons], variantIndex: selectedVariant ? selectedVariantIndex : null }) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể gửi yêu cầu.');
      setOrderState({ loading: false, message: `Đã tạo yêu cầu #${result.orderId}. OSCAR sẽ gọi xác nhận.`, orderId: result.orderId });
    } catch (error) { setOrderState({ loading: false, message: error.message, orderId: null }); }
  };
  const productImages = (displayProduct.images?.length ? displayProduct.images : [displayProduct.image]).map(normalizeImagePath).filter(Boolean);
  const mediaItems = [...productImages.map((src) => ({ type: 'image', src, label: product.name })), ...(displayProduct.video ? [{ type: 'video', src: displayProduct.video, label: `${product.name} video` }] : [])];
  const detailRam = displayProduct.ram && displayProduct.ram !== 'N/A' && displayProduct.ram !== 'Liên hệ' ? displayProduct.ram : '8GB';
  const detailSsd = displayProduct.ssd && displayProduct.ssd !== 'N/A' && displayProduct.ssd !== 'Liên hệ' ? displayProduct.ssd.replace(/(\d)(GB|TB)$/i, '$1 $2') : '256 GB';
  const detailCpu = displayProduct.cpu && displayProduct.cpu !== 'N/A' && displayProduct.cpu !== 'Liên hệ' ? displayProduct.cpu : t.updatedSoon;
  const detailGpu = isDiscreteGpu(displayProduct.gpu) ? displayProduct.gpu : '';
  const detailScreen = displayProduct.screen && displayProduct.screen !== 'N/A' ? displayProduct.screen : t.updatedSoon;
  const runtimeLabel = product.batteryRuntime && lang === 'en' ? product.batteryRuntime.replace('giờ', 'hours') : product.batteryRuntime;
  const batteryLabel = product.batteryWh ? `${product.batteryWh}Wh · ${runtimeLabel || t.batteryRuntimeFallback}` : t.batteryUpdating;
  const health = [
    [t.appearance, t.checkedUsedMachine],
    ['Pin', batteryLabel],
    [t.screenLabel, t.screenChecked],
    [t.keyboardPorts, t.keyboardPortsChecked],
  ];
  const detailSpecs = [
    ['CPU', detailCpu],
    detailGpu ? ['GPU', detailGpu] : null,
    ['RAM', detailRam],
    ['SSD', detailSsd],
    [t.screenLabel, detailScreen],
    ['Pin', batteryLabel],
    [t.appearance, t.selectedUsedMachine],
    [t.warranty, text(product.badge, lang) || t.hardwareWarranty6],
    [t.brandLabel, product.brand],
    [t.fitNeed, product.demand || t.office],
  ].filter(Boolean);
  return <section className="product-detail-page landing-detail"><div className="shell"><article className="product-modal detail-view pro-detail sales-detail landing-detail-card" aria-labelledby="product-modal-title"><div className="detail-gallery tech-gallery"><div className="product-glow" />{activeMedia.type === 'video' ? <video className="detail-main-video" src={activeMedia.src} controls playsInline /> : <img src={normalizeImagePath(activeMedia.src)} alt={product.name} onError={productImageFallback} />}<div className="gallery-thumbs">{mediaItems.map((item, index) => <button type="button" className={activeMedia.src === item.src ? 'active' : ''} key={`${item.type}-${index}`} onClick={() => { trackEvent(item.type === 'video' ? 'product_video_click' : 'product_image_click', productParams(product, { image_index: index })); setActiveMedia(item); }}>{item.type === 'video' ? <span className="video-thumb">▶ Video</span> : <img src={normalizeImagePath(item.src)} alt={item.label} loading="lazy" onError={productImageFallback} />}</button>)}</div><aside className="detail-services"><span><ShieldCheck size={16} /> {t.electronicWarranty}</span><span><Wrench size={16} /> {t.shopUpgradeSupport}</span><span><PackageCheck size={16} /> {t.checkBeforeReceive}</span></aside></div><div className="detail-scroll"><div className="detail-info buy-box"><div className="breadcrumb">{t.homeBreadcrumb} / {product.brand} / {product.name}</div><span className="eyebrow"><PackageCheck size={15} /> {text(product.condition, lang)}</span><h2 id="product-modal-title">{product.name}</h2><strong className="detail-price">{formatCurrency(displayProduct.price)}</strong><div className="detail-fit-line"><span>{t.suitableFor}: {product.demand || t.office}</span><span>{t.inStockThuDuc}</span></div><div className="detail-offer"><Sparkles size={16} /> {t.detailOffer}</div>{variants.length > 0 && <div className="variant-picker"><div className="variant-picker-title"><strong>{t.chooseVariant}</strong><span>{variants[selectedVariantIndex]?.label || `${t.configPrefix} ${selectedVariantIndex + 1}`}</span></div><div>{variants.map((variant, index) => { const active = selectedVariantIndex === index; return <button type="button" aria-pressed={active} className={active ? 'active' : ''} key={`${product.id}-variant-${index}`} onClick={() => { trackEvent('variant_select', productParams(product, { variant_label: variant.label || `${t.configPrefix} ${index + 1}` })); setSelectedVariantIndex(index); }}><span className="variant-check" aria-hidden="true">{active ? '✓' : ''}</span><span className="variant-name">{variant.cpu || variant.label || `${t.configPrefix} ${index + 1}`}</span><small>{[variant.ram, variant.ssd, variant.screen].filter(Boolean).join(' / ')}</small><b>{formatCurrency(variant.price || product.price)}</b>{variant.stockStatus && <em className={variant.stockStatus.toLowerCase().includes('sẵn') ? 'variant-stock ready' : 'variant-stock'}>{variant.stockStatus}</em>}</button>; })}</div></div>}<div className="detail-cta-row sales-cta"><a className="primary zalo-main" href={contacts.zalo} target="_blank" rel="noreferrer" onClick={() => trackEvent('zalo_click', productParams(product, { source: 'detail_main_cta' }))}><MessageCircle size={17} /> {t.askZalo}</a><span className="secondary dark phone-display" onClick={() => trackEvent('phone_click', productParams(product, { source: 'detail_main_cta' }))}>{t.callNow}: {contacts.hotline}</span><button className="secondary dark share-link" type="button" onClick={shareProduct}><Share2 size={16} /> {copied ? t.shareCopied : t.share}</button></div><div className="detail-spec-strip"><span><Cpu size={18} /><small>CPU</small><b>{detailCpu}</b></span>{detailGpu && <span><Zap size={18} /><small>GPU</small><b>{detailGpu}</b></span>}<span><HardDrive size={18} /><small>RAM/SSD</small><b>{detailRam} / {detailSsd}</b></span><span><Monitor size={18} /><small>{t.screenLabel}</small><b>{detailScreen}</b></span></div><button type="button" className="upgrade-summary upgrade-trigger" aria-expanded={configOpen} onClick={() => setConfigOpen(true)}><span className="upgrade-summary-icon"><Wrench size={19} /></span><span className="upgrade-summary-copy"><b>Lựa chọn cấu hình tùy chỉnh</b><small>{chosenAddons.length ? `${chosenAddons.length} lựa chọn · ${formatCurrency(orderTotal)}` : 'RAM, SSD và bảo hành'}</small></span><span className="upgrade-summary-action">{chosenAddons.length ? 'Thay đổi' : 'Lựa chọn'}</span></button>{configOpen && createPortal(<><button type="button" className="config-sheet-backdrop" aria-label="Đóng lựa chọn cấu hình" onClick={() => setConfigOpen(false)} /><div className="config-sheet" role="dialog" aria-modal="true" aria-label="Lựa chọn cấu hình tùy chỉnh"><div className="config-sheet-handle" /><button type="button" className="config-sheet-close" onClick={() => setConfigOpen(false)} aria-label="Đóng">×</button><div className="upgrade-panel-heading"><div><span className="eyebrow"><Wrench size={15} /> Cấu hình & dịch vụ</span><h3>Hoàn thiện chiếc máy của bạn</h3></div><strong>{formatCurrency(orderTotal)}</strong></div><div className="addon-options">{addonSections.map((section) => <section className={`addon-section addon-section-${section.key}`} key={section.key}><h4>{section.label}</h4><div>{section.items.map((addon) => { const active=selectedAddons.includes(addon.wooId); const optionName=section.key==='ram'?(addon.name.match(/DDR\d\s+\d+GB/i)?.[0]||addon.name):section.key==='ssd'?(addon.name.match(/(?:256|512)GB|1TB/i)?.[0]||addon.name):addon.name; return <button type="button" aria-pressed={active} className={active?'selected':''} key={addon.wooId} onClick={()=>toggleAddon(addon)}><span className="addon-corner">{active?'✓':''}</span><b>{optionName}</b><small>{addon.price?`+${formatCurrency(addon.price)}`:'Miễn phí'}</small></button>; })}</div></section>)}</div></div></>, document.body)}</div><div className="mobile-detail-sticky"><div className="mobile-sticky-price"><small>Giá sản phẩm</small><strong>{formatCurrency(orderTotal)}</strong></div><a className="mobile-sticky-call" href={`tel:${String(contacts.hotline).replace(/\D/g,'')}`} onClick={() => trackEvent('phone_click', productParams(product, { source: 'sticky_mobile_cta' }))} aria-label={`${t.call} ${contacts.hotline}`}><Phone size={20} /></a><a className="primary zalo-main" href={contacts.zalo} target="_blank" rel="noreferrer" onClick={() => trackEvent('zalo_click', productParams(product, { source: 'sticky_mobile_cta' }))}><MessageCircle size={17} /> Gửi yêu cầu</a></div><div className="detail-tabs detail-full"><section><h3>{t.specTable}</h3><table className="spec-table"><tbody>{detailSpecs.map(([label, value]) => <tr key={label}><td>{label}</td><td>{value}</td></tr>)}</tbody></table></section><section><h3>{t.machineCondition}</h3><div className="health-grid condition-grid">{health.map(([label, value]) => <span key={label}><CheckCircle2 size={16} /><b>{label}</b><em>{value}</em></span>)}</div></section></div><div className="similar-products detail-related"><div className="related-head"><h3>{t.similarProducts}</h3></div><div className="related-grid">{similar.map((item) => <button className="related-card" key={item.id} onClick={() => setProduct(item, 'related_product')}><span className="related-image"><img src={normalizeImagePath(item.image)} alt="" loading="lazy" onError={productImageFallback} /><em>{item.brand}</em></span><span className="related-info"><b>{item.name}</b><small>{item.cpu} • {item.ram} • {item.ssd}</small><strong>{formatCurrency(item.price)}</strong></span></button>)}</div></div></div></article></div></section>;
}

function MobileCommerce({ page, t }) {
  const items = [
    { href: '#products', icon: Menu, key: 'products', label: t.mobileProducts },
    { href: '#service', icon: Wrench, key: 'service', label: t.mobileRepair },
    { href: '#blog', icon: ClipboardCheck, key: 'blog', label: t.mobileBlog },
    { href: '#contact', icon: MessageCircle, key: 'contact', label: t.mobileContact },
  ];
  return <nav className="mobile-commerce pro-mobile">{items.map(({ href, icon: Icon, key, label }) => <a className={page === key ? 'active' : ''} href={href} key={key}><Icon size={19} />{label}</a>)}</nav>;
}
createRoot(document.getElementById('root')).render(<ErrorBoundary><App /></ErrorBoundary>);
