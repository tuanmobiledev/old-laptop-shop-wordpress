const GA_ID = import.meta.env.VITE_GA4_MEASUREMENT_ID;
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
  if (!hasWindow || !GA_ID || window.__ga4Loaded) return;
  window.dataLayer = window.dataLayer || [];
  window.gtag = function gtag(){ window.dataLayer.push(arguments); };
  window.gtag('js', new Date());
  window.gtag('config', GA_ID, { send_page_view: false });

  const script = document.createElement('script');
  script.async = true;
  script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA_ID)}`;
  document.head.appendChild(script);
  window.__ga4Loaded = true;
};

export const trackEvent = (name, params = {}) => {
  if (!hasWindow || !GA_ID || !window.gtag) return;
  window.gtag('event', name, cleanParams(params));
};

export const trackPageView = (title = document.title) => {
  if (!hasWindow || !GA_ID || !window.gtag) return;
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
