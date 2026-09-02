// ProductDetailPage — dispatches between Laptop and Accessory detail
// templates based on `product.category`. Lives in its own file because
// the two templates plus their dispatcher totalled ~400 lines and were
// the largest single block in main.jsx (T3 refactor).
//
// Boss 2026-08-01 (Option D): dispatch to accessory template when
// `product.category === 'phu-kien'`. Previously laptop-only.

import { createPortal } from 'react-dom';
import { useEffect, useState } from 'react';
import {
  CheckCircle2,
  Cpu,
  HardDrive,
  MessageCircle,
  Monitor,
  PackageCheck,
  Share2,
  ShieldCheck,
  Sparkles,
  Store,
  Truck,
  Wrench,
  Zap,
} from 'lucide-react';
import { contacts, formatCurrency, formatTel } from '../data.js';
import { isDiscreteGpu, text } from '../productUtils.js';
import { productParams, trackEvent } from '../tracking.js';
import {
  isValidPhoneVN,
  normalizeImagePath,
  ORDER_FIELD_EMPTY,
  productPath,
  slugify,
} from '../utils.js';
import { SmartImage } from './SmartImage.jsx';

// Temporary rollout switch: keep configurator code/data intact while
// hiding its UI. Mirrors the flag in main.jsx — deliberately re-declared
// here because the configurator portal lives only inside LaptopProductDetail.
const CUSTOM_CONFIGURATION_ENABLED = false;

function AccessoryProductDetail({ lang, onClose, product, productList, setProduct, t }) {
  const [activeMedia, setActiveMedia] = useState({ type: 'image', src: normalizeImagePath(product?.image) || '/oscar-cover.webp' });
  const [copied, setCopied] = useState(false);
  const [orderForm, setOrderForm] = useState(ORDER_FIELD_EMPTY);
  const [formError, setFormError] = useState({ name: '', phone: '' });
  const [orderState, setOrderState] = useState({ loading: false, message: '', orderId: null });
  useEffect(() => {
    if (!product) return undefined;
    setActiveMedia({ type: 'image', src: normalizeImagePath(product.image || product.images?.[0]) || '/oscar-cover.webp' });
    setOrderState({ loading: false, message: '', orderId: null });
    return undefined;
  }, [product?.id, product?.image, product?.images]);
  // Boss 2026-08-30 P0-3: preload hero image to cut LCP from 6.1s → <4s on mobile.
  useEffect(() => {
    if (!product?.image) return undefined;
    const img = normalizeImagePath(product.image);
    const link = document.createElement('link');
    link.rel = 'preload';
    link.as = 'image';
    link.href = img;
    link.setAttribute('fetchpriority', 'high');
    document.head.appendChild(link);
    return () => link.remove();
  }, [product?.id]);
  useEffect(() => {
    if (!product) return undefined;
    const ld = document.createElement('script');
    ld.type = 'application/ld+json';
    ld.id = 'product-ld';
    ld.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'Product',
      name: product.name,
      description: (product.specs?.[lang] || []).filter((s) => s && s !== t.updatedSoon).join(' / ') || product.name,
      image: (product.images?.length ? product.images : [product.image]).map(normalizeImagePath),
      brand: { '@type': 'Brand', name: product.brand || 'OSCAR' },
      offers: {
        '@type': 'Offer',
        price: product.price,
        priceCurrency: 'VND',
        availability: (Number(product.stock) || 0) > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        url: `https://maytinhthuduc.com/san-pham/${slugify(product.name)}-p${product.id}`,
      },
    });
    const prev = document.getElementById('product-ld');
    if (prev) prev.remove();
    document.head.appendChild(ld);
    return () => { const el = document.getElementById('product-ld'); if (el) el.remove(); };
  }, [product?.id, lang]);
  if (!product) return <section className="section shell product-detail-page"><div className="section-heading"><h1>{t.notFoundTitle}</h1><p>{t.notFoundDesc}</p></div><a className="primary" href="/#products" onClick={onClose}>{t.otherProducts}</a></section>;
  const shareUrl = `${window.location.origin}${productPath(product)}`;
  const copyToClipboard = async (value) => { try { await navigator.clipboard.writeText(value); return true; } catch { return false; } };
  const shareProduct = async () => {
    const shareText = `Laptop OSCAR Thủ Đức - ${product.name}\n${t.sharePrice}: ${formatCurrency(product.price)}\n${shareUrl}`;
    const copiedOk = await copyToClipboard(shareText);
    trackEvent(copiedOk ? 'share_click' : 'clipboard_copy_failed', productParams(product, { source: 'detail_share', method: 'copy_link' }));
    setCopied(copiedOk);
    window.setTimeout(() => setCopied(false), 1800);
  };
  const validateOrderForm = () => {
    const next = { name: '', phone: '' };
    if (!orderForm.name.trim()) next.name = lang === 'en' ? 'Please enter your name' : 'Vui lòng nhập họ tên';
    if (!orderForm.phone.trim()) next.phone = lang === 'en' ? 'Please enter your phone' : 'Vui lòng nhập số điện thoại';
    else if (!isValidPhoneVN(orderForm.phone)) next.phone = lang === 'en' ? 'Phone must be 10-11 digits, start with 0' : 'Số điện thoại phải có 10-11 chữ số, bắt đầu bằng 0';
    setFormError(next);
    return !next.name && !next.phone;
  };
  const updateOrderField = (field, value) => { setOrderForm((prev) => ({ ...prev, [field]: value })); setFormError((prev) => prev[field] ? { ...prev, [field]: '' } : prev); };
  const submitOrder = async (event) => {
    event.preventDefault();
    if (!validateOrderForm()) { setOrderState({ loading: false, message: '', orderId: null }); return; }
    setOrderState({ loading: true, message: '', orderId: null });
    try {
      const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
      const response = await fetch(`${restBase}orders`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: orderForm.name, phone: orderForm.phone, note: orderForm.note, productIds: [product.wooId] }),
      });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể gửi yêu cầu.');
      setOrderState({ loading: false, message: `Đã tạo yêu cầu #${result.orderId}. OSCAR sẽ gọi xác nhận.`, orderId: result.orderId });
      setOrderForm(ORDER_FIELD_EMPTY); setFormError({ name: '', phone: '' });
    } catch (error) { setOrderState({ loading: false, message: error.message, orderId: null }); }
  };
  const productImages = (product.images?.length ? product.images : [product.image]).map(normalizeImagePath).filter(Boolean);
  const mediaItems = [...productImages.map((src) => ({ type: 'image', src, label: product.name })), ...(product.video ? [{ type: 'video', src: product.video, label: `${product.name} video` }] : [])];
  const accessorySpecs = [
    product.brand ? [t.brandLabel, product.brand] : null,
    [t.warranty, text(product.badge, lang) || t.hardwareWarranty6],
  ].filter(Boolean);
  const descriptionLines = (product.specs?.[lang] || []).filter((s) => s && s !== t.updatedSoon);
  const similar = productList.filter((item) => item.category === 'phu-kien' && item.id !== product.id).slice(0, 4);
  return (
    <section className="product-detail-page landing-detail accessory-detail">
      <div className="shell">
        <article className="product-modal detail-view pro-detail sales-detail landing-detail-card" aria-labelledby="product-modal-title">
          <div className="detail-gallery tech-gallery">
            <div className="product-glow" />
            {activeMedia.type === 'video'
              ? <video className="detail-main-video" src={activeMedia.src} controls playsInline />
              : <SmartImage src={normalizeImagePath(activeMedia.src)} alt={product.name} width={800} height={600} priority sizes="(max-width: 760px) 100vw, 800px" />}
            <div className="gallery-thumbs">
              {mediaItems.map((item, index) => (
                <button type="button" className={activeMedia.src === item.src ? 'active' : ''} key={`${item.type}-${index}`} onClick={() => { trackEvent(item.type === 'video' ? 'product_video_click' : 'product_image_click', productParams(product, { image_index: index })); setActiveMedia(item); }}>
                  {item.type === 'video' ? <span className="video-thumb">▶ Video</span> : <SmartImage src={normalizeImagePath(item.src)} alt={item.label} width={80} height={80} sizes="80px" />}
                </button>
              ))}
            </div>
            <aside className="detail-services">
              <span><ShieldCheck size={16} /> {t.electronicWarranty}</span>
              <span><Truck size={16} /> {t.sameDay}</span>
              <span><Store size={16} /> {t.topStore}</span>
            </aside>
          </div>
          <div className="detail-scroll">
            <div className="detail-info buy-box">
              <div className="breadcrumb">{t.homeBreadcrumb} / {product.brand || t.accessoryBreadcrumb} / {product.name}</div>
              <span className="eyebrow"><PackageCheck size={15} /> {t.accessoryBreadcrumb}</span>
              <h2 id="product-modal-title">{product.name}</h2>
              <strong className="detail-price">{formatCurrency(product.price)}</strong>
              {(() => { const stockCount = Number(product.stock) || 0; const inStock = stockCount > 0; return <span className={`stock-badge ${inStock ? 'stock-badge-in' : 'stock-badge-out'}`}><span className="stock-dot" aria-hidden="true" />{inStock ? `Còn ${stockCount} ${t.productUnit || 'sp'}` : t.outOfStock || 'Hết hàng'}</span>; })()}
              <div className="detail-fit-line"><span>{t.suitableFor}: {product.demand || t.everydayUse || 'Phụ kiện thường ngày'}</span></div>
              <div className="detail-trust-badges">
                <span><ShieldCheck size={15} /> {t.electronicWarranty}</span>
                <span><Truck size={15} /> {t.sameDay}</span>
                <span><Store size={15} /> {t.topStore}</span>
              </div>
              <div className="detail-offer"><Sparkles size={16} /> {t.detailOffer}</div>
              <div className="detail-cta-row sales-cta">
                <a className="primary zalo-main" href={contacts.zalo} target="_blank" rel="noreferrer" onClick={() => trackEvent('zalo_click', productParams(product, { source: 'detail_main_cta' }))}>
                  <MessageCircle size={17} /> {t.messageZalo}
                </a>
                <a className="secondary dark phone-display" href={formatTel(contacts.hotline)} onClick={() => trackEvent('phone_click', productParams(product, { source: 'detail_main_cta' }))}>{t.callNow}: {contacts.hotline}</a>
                <button className="secondary dark share-link" type="button" onClick={shareProduct}>
                  <Share2 size={16} /> {copied ? t.shareCopied : t.share}
                </button>
              </div>
            </div>
            {/* Boss 2026-08-06: removed inline <div className="mobile-detail-sticky">...</div>
                — lifted to <MobileDetailSticky> at App level so position:fixed resolves
                against the viewport (was snapping to .product-modal box on mobile). */}
            <div className="detail-tabs detail-full">
              {accessorySpecs.length > 0 && (
                <section>
                  <h3>{t.specTable}</h3>
                  <table className="spec-table">
                    <tbody>
                      {accessorySpecs.map(([k, v]) => <tr key={k}><td>{k}</td><td>{v}</td></tr>)}
                    </tbody>
                  </table>
                </section>
              )}
              {descriptionLines.length > 0 && (
                <section>
                  <h3>{t.descriptionTitle}</h3>
                  <ul className="description-list">{descriptionLines.map((s, i) => <li key={i}><CheckCircle2 size={16} /><span>{s}</span></li>)}</ul>
                </section>
              )}
              <section>
                <h3>{t.accessoryConditionTitle}</h3>
                <ul className="condition-list">
                  {t.accessoryConditionItems.split('\n').map((s, i) => (
                    <li key={i}><CheckCircle2 size={16} /><span>{s}</span></li>
                  ))}
                </ul>
              </section>
            </div>
            {similar.length > 0 && (
              <div className="similar-products detail-related">
                <div className="related-head"><h3>{t.similarProducts}</h3></div>
                <div className="related-grid">
                  {similar.map((item) => (
                    <button className="related-card" key={item.id} onClick={() => setProduct(item, 'related_product')}>
                      <span className="related-image">
                        <SmartImage src={normalizeImagePath(item.image)} alt="" width={220} height={165} sizes="(max-width: 760px) 50vw, 220px" />
                        <em>{item.brand}</em>
                      </span>
                      <span className="related-info">
                        <b>{item.name}</b>
                        <small>{item.brand || t.accessoryBreadcrumb}</small>
                        <strong>{formatCurrency(item.price)}</strong>
                      </span>
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </article>
      </div>
    </section>
  );
}

function LaptopProductDetail({ lang, onClose, product, productList, setProduct, t, onOrderTotalChange }) {
  const [activeMedia, setActiveMedia] = useState({ type: 'image', src: normalizeImagePath(product?.image) || '/oscar-cover.webp' });
  const [selectedVariantIndex, setSelectedVariantIndex] = useState(0);
  const [copied, setCopied] = useState(false);
  const [addons, setAddons] = useState([]);
  const [selectedAddons, setSelectedAddons] = useState([]);
  const [orderForm, setOrderForm] = useState(ORDER_FIELD_EMPTY);
  const [formError, setFormError] = useState({ name: '', phone: '' });
  const [orderState, setOrderState] = useState({ loading: false, message: '', orderId: null });
  const [configOpen, setConfigOpen] = useState(false);
  // Boss 2026-08-03: stacking-context fix — lock both html + body (iOS Safari needs html-level).
  // Without html-level lock, iOS still allows scroll underneath the sheet.
  // Boss 2026-08-03 (hotfix #3): cleanup now FORCE resets to '' instead of restoring prev.
  // Same race-condition reasoning as the filter-drawer effect and App body lock —
  // if some other effect has locked body more recently, restoring prev here would
  // undo that lock. Inert today because CUSTOM_CONFIGURATION_ENABLED=false gates
  // configOpen=true, but pre-emptive so re-enabling the feature later doesn't
  // reintroduce the bug.
  useEffect(() => {
    if (typeof window === 'undefined' || !configOpen) return undefined;
    document.documentElement.style.overflow = 'hidden';
    document.body.style.overflow = 'hidden';
    const closeOnEscape = (event) => { if (event.key === 'Escape') setConfigOpen(false); };
    window.addEventListener('keydown', closeOnEscape);
    return () => {
      document.documentElement.style.overflow = '';
      document.body.style.overflow = '';
      window.removeEventListener('keydown', closeOnEscape);
    };
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
  // Boss 2026-08-30 P0-3: preload hero image to cut LCP from 6.1s → <4s on mobile.
  useEffect(() => {
    if (!product?.image) return undefined;
    const img = normalizeImagePath(product.image);
    const link = document.createElement('link');
    link.rel = 'preload';
    link.as = 'image';
    link.href = img;
    link.setAttribute('fetchpriority', 'high');
    document.head.appendChild(link);
    return () => link.remove();
  }, [product?.id]);
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
  // Boss 2026-08-23 (fix React #310 on direct /san-pham/...-p<N>/ load):
  // MOVED orderTotal-publish useEffect from below the `if (!product) return ...`
  // early return to here (BEFORE the early-return). Previously this effect
  // lived at line 350+ — register-1 (product=undefined) ran only 13 hooks,
  // register-2 (product=defined) ran 14 hooks → React error #310
  // "Rendered fewer hooks than expected", breaking cold-load product URLs.
  // Computing orderTotal inline here (the L346 const is after the early-return
  // and would throw on `product` access). Deps include every input that
  // determines the total — selectedVariantIndex, addons, selectedAddons,
  // product?.id, onOrderTotalChange — so the effect re-fires on every relevant
  // state change just like the previous version that depended on `orderTotal`.
  useEffect(() => {
    if (onOrderTotalChange && product) {
      const variants = Array.isArray(product.variants) ? product.variants : [];
      const selectedVariant = variants[selectedVariantIndex] || null;
      const displayProduct = selectedVariant ? { ...product, ...selectedVariant } : product;
      const chosenAddons = addons.filter((addon) => selectedAddons.includes(addon.wooId));
      const total = Number(displayProduct.price || 0) + chosenAddons.reduce((sum, addon) => sum + Number(addon.price || 0), 0);
      onOrderTotalChange(total);
    }
  }, [selectedVariantIndex, addons, selectedAddons, product?.id, onOrderTotalChange]);
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
  const validateOrderForm = () => {
    const next = { name: '', phone: '' };
    if (!orderForm.name.trim()) next.name = lang === 'en' ? 'Please enter your name' : 'Vui lòng nhập họ tên';
    if (!orderForm.phone.trim()) next.phone = lang === 'en' ? 'Please enter your phone' : 'Vui lòng nhập số điện thoại';
    else if (!isValidPhoneVN(orderForm.phone)) next.phone = lang === 'en' ? 'Phone must be 10-11 digits, start with 0' : 'Số điện thoại phải có 10-11 chữ số, bắt đầu bằng 0';
    setFormError(next);
    return !next.name && !next.phone;
  };
  const updateOrderField = (field, value) => { setOrderForm((prev) => ({ ...prev, [field]: value })); setFormError((prev) => prev[field] ? { ...prev, [field]: '' } : prev); };
  const submitOrder = async (event) => {
    event.preventDefault(); if (!validateOrderForm()) { setOrderState({ loading: false, message: '', orderId: null }); return; } setOrderState({ loading: true, message: '', orderId: null });
    try {
      const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
      const response = await fetch(`${restBase}orders`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ name: orderForm.name, phone: orderForm.phone, note: orderForm.note, productIds: [product.wooId, ...selectedAddons], variantIndex: selectedVariant ? selectedVariantIndex : null }) });
      const result = await response.json();
      if (!response.ok) throw new Error(result.message || 'Không thể gửi yêu cầu.');
      setOrderState({ loading: false, message: `Đã tạo yêu cầu #${result.orderId}. OSCAR sẽ gọi xác nhận.`, orderId: result.orderId });
      setOrderForm(ORDER_FIELD_EMPTY); setFormError({ name: '', phone: '' });
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
  return <section className="product-detail-page landing-detail"><div className="shell"><article className="product-modal detail-view pro-detail sales-detail landing-detail-card" aria-labelledby="product-modal-title"><div className="detail-gallery tech-gallery"><div className="product-glow" />{activeMedia.type === 'video' ? <video className="detail-main-video" src={activeMedia.src} controls playsInline /> : <SmartImage src={normalizeImagePath(activeMedia.src)} alt={product.name} width={800} height={600} priority sizes="(max-width: 760px) 100vw, 800px" />}<div className="gallery-thumbs">{mediaItems.map((item, index) => <button type="button" className={activeMedia.src === item.src ? 'active' : ''} aria-label={item.type === 'video' ? `${t.productVideo || 'Video'} ${index + 1}` : `${t.productImage || 'Hình ảnh'} ${index + 1}`} key={`${item.type}-${index}`} onClick={() => { trackEvent(item.type === 'video' ? 'product_video_click' : 'product_image_click', productParams(product, { image_index: index })); setActiveMedia(item); }}>{item.type === 'video' ? <span className="video-thumb" aria-hidden="true">▶ Video</span> : <SmartImage src={normalizeImagePath(item.src)} alt={item.label} width={80} height={80} sizes="80px" />}</button>)}</div><aside className="detail-services"><span><ShieldCheck size={16} /> {t.electronicWarranty}</span><span><Wrench size={16} /> {t.shopUpgradeSupport}</span><span><PackageCheck size={16} /> {t.checkBeforeReceive}</span></aside></div><div className="detail-scroll"><div className="detail-info buy-box"><div className="breadcrumb">{t.homeBreadcrumb} / {product.brand} / {product.name}</div><span className="eyebrow"><PackageCheck size={15} /> {text(product.condition, lang)}</span><h2 id="product-modal-title">{product.name}</h2><strong className="detail-price">{formatCurrency(displayProduct.price)}</strong>{(() => { const stockCount = Number(displayProduct.stock ?? product.stock) || 0; const inStock = stockCount > 0; return <span className={`stock-badge ${inStock ? 'stock-badge-in' : 'stock-badge-out'}`}><span className="stock-dot" aria-hidden="true" />{inStock ? `Còn ${stockCount} ${t.productUnit || 'máy'}` : t.outOfStock || 'Hết hàng'}</span>; })()}<div className="detail-fit-line"><span>{t.suitableFor}: {product.demand || t.office}</span></div><div className="detail-trust-badges"><span><ShieldCheck size={15} /> {t.electronicWarranty}</span><span><Wrench size={15} /> {t.shopUpgradeSupport}</span><span><Truck size={15} /> {t.sameDay}</span></div><div className="detail-offer"><Sparkles size={16} /> {t.detailOffer}</div>{variants.length > 0 && <div className="variant-picker"><div className="variant-picker-title"><strong>{t.chooseVariant}</strong><span>{variants[selectedVariantIndex]?.label || `${t.configPrefix} ${selectedVariantIndex + 1}`}</span></div><div>{variants.map((variant, index) => { const active = selectedVariantIndex === index; return <button type="button" aria-pressed={active} className={active ? 'active' : ''} key={`${product.id}-variant-${index}`} onClick={() => { trackEvent('variant_select', productParams(product, { variant_label: variant.label || `${t.configPrefix} ${index + 1}` })); setSelectedVariantIndex(index); }}><span className="variant-check" aria-hidden="true">{active ? '✓' : ''}</span><span className="variant-name">{variant.cpu || variant.label || `${t.configPrefix} ${index + 1}`}</span><small>{[variant.ram, variant.ssd, variant.screen].filter(Boolean).join(' / ')}</small><b>{formatCurrency(variant.price || product.price)}</b>{variant.stockStatus && <em className={variant.stockStatus.toLowerCase().includes('sẵn') ? 'variant-stock ready' : 'variant-stock'}>{variant.stockStatus}</em>}</button>; })}</div></div>}<div className="detail-cta-row sales-cta"><a className="primary zalo-main" href={contacts.zalo} target="_blank" rel="noreferrer" onClick={() => trackEvent('zalo_click', productParams(product, { source: 'detail_main_cta' }))}><MessageCircle size={17} /> {t.askZalo}</a><a className="secondary dark phone-display" href={formatTel(contacts.hotline)} onClick={() => trackEvent('phone_click', productParams(product, { source: 'detail_main_cta' }))}>{t.callNow}: {contacts.hotline}</a><button className="secondary dark share-link" type="button" onClick={shareProduct}><Share2 size={16} /> {copied ? t.shareCopied : t.share}</button></div><div className="detail-spec-strip"><span><Cpu size={18} /><small>CPU</small><b>{detailCpu}</b></span>{detailGpu && <span><Zap size={18} /><small>GPU</small><b>{detailGpu}</b></span>}<span><HardDrive size={18} /><small>RAM/SSD</small><b>{detailRam} / {detailSsd}</b></span><span><Monitor size={18} /><small>{t.screenLabel}</small><b>{detailScreen}</b></span></div>{CUSTOM_CONFIGURATION_ENABLED && <button type="button" className="upgrade-summary upgrade-trigger" aria-expanded={configOpen} onClick={() => setConfigOpen(true)}><span className="upgrade-summary-icon"><Wrench size={19} /></span><span className="upgrade-summary-copy"><b>Lựa chọn cấu hình tùy chỉnh</b><small>{chosenAddons.length ? `${chosenAddons.length} lựa chọn · ${formatCurrency(orderTotal)}` : 'RAM, SSD và bảo hành'}</small></span><span className="upgrade-summary-action">{chosenAddons.length ? 'Thay đổi' : 'Lựa chọn'}</span></button>}{CUSTOM_CONFIGURATION_ENABLED && configOpen && createPortal(<div className="config-overlay"><button type="button" className="config-sheet-backdrop" aria-label="Đóng lựa chọn cấu hình" onClick={() => setConfigOpen(false)} /><div className="config-sheet" role="dialog" aria-modal="true" aria-label="Lựa chọn cấu hình tùy chỉnh"><div className="config-sheet-handle" /><button type="button" className="config-sheet-close" onClick={() => setConfigOpen(false)} aria-label="Đóng">×</button><div className="upgrade-panel-heading"><div><span className="eyebrow"><Wrench size={15} /> Cấu hình & dịch vụ</span><h3>Hoàn thiện chiếc máy của bạn</h3></div><strong>{formatCurrency(orderTotal)}</strong></div><div className="addon-options">{addonSections.map((section) => <section className={`addon-section addon-section-${section.key}`} key={section.key}><h4>{section.label}</h4><div>{section.items.map((addon) => { const active=selectedAddons.includes(addon.wooId); const optionName=section.key==='ram'?(addon.name.match(/DDR\d\s+\d+GB/i)?.[0]||addon.name):section.key==='ssd'?(addon.name.match(/(?:256|512)GB|1TB/i)?.[0]||addon.name):addon.name; return <button type="button" aria-pressed={active} className={active?'selected':''} key={addon.wooId} onClick={()=>toggleAddon(addon)}><span className="addon-corner">{active?'✓':''}</span><b>{optionName}</b><small>{addon.price?`+${formatCurrency(addon.price)}`:'Miễn phí'}</small></button>; })}</div></section>)}</div><div className="config-sheet-actions"><button type="button" className="primary config-sheet-done" onClick={() => setConfigOpen(false)}>{t.configDone}</button></div></div></div>, document.body)}</div>{/* Boss 2026-08-06: removed inline <div className="mobile-detail-sticky">...</div> — lifted to <MobileDetailSticky> at App level so position:fixed resolves against the viewport (was snapping to .product-modal box on mobile). */}<div className="detail-tabs detail-full"><section><h3>{t.specTable}</h3><table className="spec-table"><tbody>{detailSpecs.map(([label, value]) => <tr key={label}><td>{label}</td><td>{value}</td></tr>)}</tbody></table></section><section><h3>{t.machineCondition}</h3><div className="health-grid condition-grid">{health.map(([label, value]) => <span key={label}><CheckCircle2 size={16} /><b>{label}</b><em>{value}</em></span>)}</div></section></div><div className="similar-products detail-related"><div className="related-head"><h3>{t.similarProducts}</h3></div><div className="related-grid">{similar.map((item) => <button className="related-card" key={item.id} aria-label={`${t.viewDetail || 'Xem chi tiết'} ${item.name}`} onClick={() => setProduct(item, 'related_product')}><span className="related-image"><SmartImage src={normalizeImagePath(item.image)} alt="" width={220} height={165} sizes="(max-width: 760px) 50vw, 220px" /><em>{item.brand}</em></span><span className="related-info"><b>{item.name}</b><small>{item.cpu} • {item.ram} • {item.ssd}</small><strong>{formatCurrency(item.price)}</strong></span></button>)}</div></div></div></article></div></section>;
}

export default function ProductDetailPage(props) {
  // Boss 2026-08-01 (Option D): dispatch to accessory template when category is 'phu-kien'.
  // Boss 2026-08-12 (port from src tree commit 7729804): also dispatch when product has
  // no laptop specs (handles legacy data where accessories were filed under laptop-cu,
  // e.g. mouse wooId=27). Mirrors the heuristic in ProductCard.jsx.
  const { product } = props;
  const isAccessory = product && (
    product.category === 'phu-kien' ||
    (!product.cpu && !product.ram && !product.ssd && !product.screen)
  );
  if (product && isAccessory) {
    return <AccessoryProductDetail {...props} />;
  }
  return <LaptopProductDetail {...props} />;
}
