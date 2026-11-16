// Site Kit loads gtag.js with the configured GA4 property (G-F3JDDL0G2P).
// We do NOT load a second gtag.js to avoid double-config conflicts.
// This module just calls window.gtag (provided by Site Kit) for custom events
// and SPA route-change page_view tracking (gtag's built-in history listener
// does not fire on hashchange, so we fire page_view manually on every route).
const hasWindow = typeof window !== 'undefined';

// Meta (Facebook) Pixel: base code is printed by the theme (functions.php,
// option `oscar_fb_pixel_id`). Here we only fire events; window.fbq is absent
// when the pixel id is not configured, so every call below is a no-op then.
const fb = (event, params) => {
  if (!hasWindow || typeof window.fbq !== 'function') return;
  if (event === 'trackCustom') {
    window.fbq('trackCustom', params.name, params.data);
    return;
  }
  window.fbq('track', event, params);
};

// GA event name -> Meta standard event. Only events that map to a real user
// action are forwarded, so Meta counts stay comparable with GA4.
const FB_EVENT_MAP = {
  product_view: 'ViewContent',
  phone_click: 'Contact',
  zalo_click: 'Contact',
  add_to_cart: 'AddToCart',
};

const fbEventFor = (name, params = {}) => {
  const std = FB_EVENT_MAP[name];
  const data = {
    content_name: params.product_name || params.content_name,
    content_ids: params.product_id ? [params.product_id] : undefined,
    content_type: params.product_id ? 'product' : undefined,
    value: typeof params.price === 'number' ? params.price : undefined,
    currency: typeof params.price === 'number' ? 'VND' : undefined,
    content_category: params.category,
  };
  if (std) return [std, Object.fromEntries(Object.entries(data).filter(([, v]) => v !== undefined))];
  return ['trackCustom', { name, data: cleanParams(params) }];
};

const sanitizeValue = (value) => {
  if (value === undefined || value === null) return undefined;
  if (typeof value === 'string') return value.slice(0, 160);
  if (typeof value === 'number' || typeof value === 'boolean') return value;
  if (Array.isArray(value)) return value.map(sanitizeValue).filter((item) => item !== undefined).join(', ').slice(0, 160);
  return String(value).slice(0, 160);
};

const cleanParams = (params = {}) => Object.fromEntries(
  Object.entries(params)
    .map(([key, value]) => [key, sanitizeValue(value)])
    .filter(([, value]) => value !== undefined && value !== '')
);

export const initGA = () => {
  // No-op: rely on Site Kit's gtag.js. Functions below detect window.gtag lazily.
};

export const trackEvent = (name, params = {}) => {
  if (!hasWindow) return;
  const clean = cleanParams(params);
  if (typeof window.gtag === 'function') window.gtag('event', name, clean);
  const [fbEvent, fbParams] = fbEventFor(name, clean);
  fb(fbEvent, fbParams);
};

export const trackPageView = (title = document.title) => {
  if (!hasWindow) return;
  if (typeof window.gtag === 'function') {
    window.gtag('event', 'page_view', {
      page_title: title,
      page_location: window.location.href,
      page_path: `${window.location.pathname}${window.location.hash}`,
    });
  }
  // SPA hash routing: the pixel's own PageView only fires on first load.
  if (typeof window.fbq === 'function') {
    window.fbq('track', 'PageView', { page_title: title });
  }
};

export const productParams = (product = {}, extra = {}) => cleanParams({
  product_id: product.id,
  product_name: product.name,
  brand: product.brand,
  category: product.category,
  price: product.price,
  ...extra,
});
