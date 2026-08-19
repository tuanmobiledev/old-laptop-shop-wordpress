// Site Kit loads gtag.js with the configured GA4 property (G-F3JDDL0G2P).
// We do NOT load a second gtag.js to avoid double-config conflicts.
// This module just calls window.gtag (provided by Site Kit) for custom events
// and SPA route-change page_view tracking (gtag's built-in history listener
// does not fire on hashchange, so we fire page_view manually on every route).
const hasWindow = typeof window !== 'undefined';

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
  if (!hasWindow || typeof window.gtag !== 'function') return;
  window.gtag('event', name, cleanParams(params));
};

export const trackPageView = (title = document.title) => {
  if (!hasWindow || typeof window.gtag !== 'function') return;
  window.gtag('event', 'page_view', {
    page_title: title,
    page_location: window.location.href,
    page_path: `${window.location.pathname}${window.location.hash}`,
  });
};

export const productParams = (product = {}, extra = {}) => cleanParams({
  product_id: product.id,
  product_name: product.name,
  brand: product.brand,
  category: product.category,
  price: product.price,
  ...extra,
});
