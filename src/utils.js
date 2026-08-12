// Shared helpers extracted from main.jsx so component files can import them
// without creating a circular dependency back through main.jsx.
//
// Keep this module pure (no React imports): it must remain usable from both
// main.jsx and any extracted component under src/components/.

import { productMediaMap } from './product-media-map.js';
import { products } from './data.js';

// ---------- Image / asset path resolution ----------

/**
 * Resolves a root-absolute `/path` against the WordPress theme URL exposed
 * via `window.OSCAR_WP.themeUrl`. Used for product images and brand assets
 * that live under the theme's `assets/images/` directory.
 *
 * If `themeUrl` isn't available (e.g. dev/Vite preview), returns the path
 * unchanged so Vite can serve `/public/...` directly.
 */
export const themeAssetUrl = (path) => {
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

/**
 * Local-folder `/product-images/*.jpg` gets normalized to `.webp` because the
 * WordPress theme ships WebP equivalents. `themeAssetUrl` then resolves the
 * final URL. Non-`/product-images/` paths are returned unchanged.
 */
export const normalizeImagePath = (path) => themeAssetUrl(
  typeof path === 'string' && path.startsWith('/product-images/')
    ? path.replace(/\.jpg(?=($|[?#]))/i, '.webp')
    : path
);

const uniqueList = (items) => [...new Set((items || []).filter(Boolean))];

/**
 * Normalizes an array of products so `image` and `images[]` always contain
 * resolved WebP URLs and deduped entries. Combines each product's own
 * images with the static `products` catalog as a fallback, so admin-
 * edited products in `localStorage` still get the catalog's bundled
 * gallery if their own data is missing it.
 */
export const normalizeProductImages = (items) => Array.isArray(items)
  ? items.map((product) => {
    const catalogProduct = products.find((item) => item.id === product.id)
      || products.find((item) => item.name === product.name)
      || {};
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

/**
 * `<img>` onError handler. Falls back to the catalog product's image
 * (matched by `alt` text) or `/oscar-cover.webp` if the original URL 404s.
 * Guarded with `data-fallback-applied` so we don't infinite-loop if the
 * fallback itself also fails.
 */
export const productImageFallback = (event) => {
  const img = event.currentTarget;
  if (img.dataset.fallbackApplied === 'true') return;
  img.dataset.fallbackApplied = 'true';
  const fallback = products.find((product) => product.name === img.alt)?.image || '/oscar-cover.webp';
  img.src = normalizeImagePath(fallback);
};

// ---------- Form validation ----------

/**
 * Loose email regex. Permissive enough for retail order forms; strict
 * RFC-style validation isn't required here.
 */
export const isValidEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(value || '').trim());

/**
 * Vietnamese phone number: 10-11 digits, must start with 0. Strips spaces,
 * dots, dashes before matching.
 */
export const isValidPhoneVN = (value) => /^0[0-9]{9,10}$/.test(String(value || '').replace(/[\s.-]/g, ''));

/**
 * Initial state for the product-detail order form. Shared between
 * LaptopProductDetail and AccessoryProductDetail so a reset always yields
 * the same shape.
 */
export const ORDER_FIELD_EMPTY = { name: '', phone: '', note: '' };

// ---------- URL / slug helpers ----------

/**
 * ASCII slug used in product URLs. Strips Vietnamese diacritics, replaces
 * `đ`→`d`, and collapses non-alphanumeric runs to `-`. Trims to 90 chars
 * to keep URLs sane for very long product names.
 */
export const slugify = (value) => String(value || '')
  .toLowerCase()
  .normalize('NFD')
  .replace(/[\u0300-\u036f]/g, '')
  .replace(/đ/g, 'd')
  .replace(/[^a-z0-9]+/g, '-')
  .replace(/^-+|-+$/g, '')
  .slice(0, 90);

/**
 * Canonical product URL pattern: `/san-pham/<slug>-p<id>`. Both id and
 * slug are present so the deep-link parser can read the id even if the
 * slug drifts.
 */
export const productPath = (product) => `/san-pham/${slugify(product.name)}-p${product.id}`;

/**
 * Parses the current `window.location.pathname` back to a product id.
 * Returns null outside the browser or on non-detail routes.
 */
export const productIdFromPath = () => {
  if (typeof window === 'undefined') return null;
  const match = window.location.pathname.match(/^\/san-pham\/.+-p(\d+)\/?$/);
  return match ? Number(match[1]) : null;
};
