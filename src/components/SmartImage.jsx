// SmartImage — drop-in replacement cho <img>:
//  1. Load H�NH GỐC duy nhất (Boss 2026-09-01 quyết định: bỏ responsive variants)
//  2. Reserves aspect-ratio qua width/height (browser reserves space khi load) → không CLS
//  3. Mặc định priority=true (eager loading) — Boss 2026-09-01 muốn mọi ảnh load ngay
//  4. Async decoding
//  5. Fallback khi 404
//
// Lý do bỏ srcset:
//  - WordPress auto-generates 100/150/300 px variants bị boss report "nhiều sản phẩm thumb fail"
//  - Trư�c đây dùng srcset để tiết kiệm bandwidth, nhưng gây ra naturalWidth=195 anomaly và
//    một số sản ph�m hiển thị ảnh nhỏ/bị sai khi browser chọn variant
//  - Trade-off: bandwidth tăng (~200KB/card thay vì ~20KB), nhưng ảnh gốc luôn hiển thị đúng

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
      onError={onError || imageFallback}
      {...rest}
    />
  );
}
