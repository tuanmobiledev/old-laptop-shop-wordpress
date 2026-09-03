// SmartImage — drop-in replacement cho <img>:
//  1. Load HÌNH GỐC duy nhất (Boss 2026-09-01 quyết định: bỏ responsive variants)
//  2. Reserves aspect-ratio qua width/height (browser reserves space khi load) → không CLS
//  3. Mặc định priority=true (eager loading) — Boss 2026-09-01 muốn mọi ảnh load ngay
//  4. Async decoding
//  5. Fallback khi 404
//  6. Boss 2026-09-03: srcFallback prop — khi src fail (vd. .webp 404) → swap sang .jpg
//

export function imageFallback(event) {
  const img = event.currentTarget;
  if (img.dataset.fallbackApplied) return;
  img.dataset.fallbackApplied = '1';
  // Use absolute path to theme asset (Boss rule: prefix /wp-content/themes/oscar-shop/)
  img.src = '/wp-content/themes/oscar-shop/assets/images/oscar-cover.webp';
}

export function SmartImage({
  src,
  srcFallback, // optional — fallback URL khi src fail (vd. webp → jpg)
  alt = '',
  width,
  height,
  sizes, // ignored — backward compat (caller có thể vẫn pass, nhưng không có tác dụng khi không có srcset)
  priority = true, // Boss 2026-09-01: mặc định eager
  className,
  style,
  onError,
  ...rest
}) {
  const loading = priority ? 'eager' : 'lazy';
  const fetchpriority = priority ? 'high' : undefined;

  // Inline aspect-ratio as backup (khi width/height DOM attrs bị override bởi CSS)
  const aspectStyle = (width && height)
    ? { aspectRatio: `${width} / ${height}`, ...style }
    : style;

  // Combined error handler: srcFallback (vd. webp → jpg) trước, sau đó caller onError, cuối cùng default imageFallback
  const handleError = (event) => {
    const img = event.currentTarget;
    if (srcFallback && !img.dataset.oscarFallbackApplied && img.src !== srcFallback) {
      img.dataset.oscarFallbackApplied = '1';
      img.src = srcFallback;
      return;
    }
    if (onError) onError(event);
    else imageFallback(event);
  };

  return (
    <img
      src={src}
      alt={alt}
      width={width}
      height={height}
      loading={loading}
      decoding="async"
      fetchPriority={fetchpriority}
      className={className}
      style={aspectStyle}
      onError={handleError}
      {...rest}
    />
  );
}
