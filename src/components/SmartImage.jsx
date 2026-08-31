// SmartImage — drop-in replacement cho <img>:
//  1. WordPress srcset (100/150/300w + original) cho responsive bandwidth
//  2. Reserves aspect-ratio qua width/height (browser reserves space khi load) → không CLS
//  3. Native lazy loading
//  4. Async decoding
//  5. Fallback khi 404
//
// Không dùng @unpic/react vì nó strip manually-passed srcset cho non-CDN URLs
// (Boss: images self-host trên /wp-content/uploads, không có Cloudflare Images transform).

// WordPress auto-generates 100/150/300 px variants cho mỗi upload.
// Pattern: "image.png" → "image-100x100.png", "image-150x150.png", "image-300x300.png"
const WP_SIZES = [100, 150, 300];

function buildWpSrcset(src) {
  if (!src) return null;
  // Match .png/.jpg/.jpeg (KHÔNG .webp vì WP đã serve webp trực tiếp, không có size variants)
  const m = src.match(/^(.+)\.(png|jpg|jpeg)(\?.*)?$/i);
  if (!m) return null;
  const base = m[1];
  const ext = m[2];
  const suffix = m[3] || '';
  // Extract original width hint from filename (default 600 = WP medium size)
  const items = WP_SIZES.map(size => `${base}-${size}x${size}.${ext}${suffix} ${size}w`);
  items.push(`${src} 600w`); // fallback to original
  return items.join(', ');
}

const DEFAULT_SIZES = '(max-width: 760px) 100vw, (max-width: 1180px) 50vw, 600px';

export function imageFallback(event) {
  const img = event.currentTarget;
  if (img.dataset.fallbackApplied) return;
  img.dataset.fallbackApplied = '1';
  // Use absolute path to theme asset (Boss rule: prefix /wp-content/themes/oscar-shop/)
  img.src = '/wp-content/themes/oscar-shop/assets/images/oscar-cover.webp';
}

export function SmartImage({
  src,
  alt = '',
  width,
  height,
  sizes = DEFAULT_SIZES,
  priority = false,
  className,
  style,
  onError,
  ...rest
}) {
  const srcset = buildWpSrcset(src);
  const loading = priority ? 'eager' : 'lazy';
  const fetchpriority = priority ? 'high' : undefined;

  // Inline aspect-ratio as backup (khi width/height DOM attrs bị override bởi CSS)
  const aspectStyle = (width && height)
    ? { aspectRatio: `${width} / ${height}`, ...style }
    : style;

  return (
    <img
      src={src}
      alt={alt}
      width={width}
      height={height}
      srcSet={srcset || undefined}
      sizes={srcset ? sizes : undefined}
      loading={loading}
      decoding="async"
      fetchPriority={fetchpriority}
      className={className}
      style={aspectStyle}
      onError={onError || imageFallback}
      {...rest}
    />
  );
}
