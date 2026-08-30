// ProductCard — grid card for a single product in the catalog list and
// the compact "related products" rail. Pure presentational; receives the
// product + language bundle and a callback to open the detail page.
//
// Spec normalization rules (see Boss 2026-07-30 notes):
//   - CPU: parsed from the product name (e.g. "i7-1260P"), falls back to
//     product.cpu, then to "Đang cập nhật / Updated soon" when both are
//     missing/`N/A`/`Liên hệ`.
//   - RAM/SSD: hard-coded category defaults (`8GB`, `256 GB`) when missing
//     rather than rendering `N/A` — empty spec rows hurt card readability.
//   - GPU: only shown when `isDiscreteGpu(product.gpu)` is true; integrated
//     GPUs are dropped to keep the card dense.
//   - Accessories (`category === 'phu-kien'`) show brand + warranty instead
//     of the laptop spec rows.

import { Cpu, HardDrive, Monitor, PackageCheck, ShieldCheck, Zap } from 'lucide-react';
import { formatCurrency } from '../data.js';
import { discount, isDiscreteGpu } from '../productUtils.js';
import { normalizeImagePath, productImageFallback } from '../utils.js';

const DEFAULT_VALUES = {
  ram: '8GB',
  ssd: '256 GB',
};

const PLACEHOLDER = 'N/A';

function resolveLabel(value, fallback, t) {
  return value && value !== PLACEHOLDER && value !== 'Liên hệ' ? value : fallback;
}

export default function ProductCard({ product, lang, t, setSelectedProduct, compact = false }) {
  const openDetail = () => setSelectedProduct(product, compact ? 'related_product' : 'product_card');
  // Boss 2026-08-12 (port from src tree commit 7729804): heuristic fallback for legacy
  // data where accessories were filed under laptop-cu (e.g. mouse wooId=27). Treat
  // those products as accessories here and in the ProductDetailPage dispatcher.
  const isAccessory = product.category === 'phu-kien' || (!product.cpu && !product.ram && !product.ssd && !product.screen);

  const cpuFromName = product.name.match(/(?:i[3579]|Core\s*i[3579]|Ryzen\s*[3579]|Ultra\s*[579]|Xeon)[-\s]?[A-Z0-9]{3,6}[A-Z]?/i)?.[0];
  const cpuLabel = (cpuFromName || resolveLabel(product.cpu, t.updatedSoon, t))
    .replace(/^Core\s+/i, '')
    .replace(/^(i[3579])\s+(\d)/i, '$1-$2');
  const gpuLabel = isDiscreteGpu(product.gpu) ? product.gpu : '';
  const ramLabel = resolveLabel(product.ram, DEFAULT_VALUES.ram, t);
  const ssdLabel = resolveLabel(product.ssd, DEFAULT_VALUES.ssd, t).replace(/(\d)(GB|TB)$/i, '$1 $2');
  const screenLabel = resolveLabel(product.screen, t.updatedSoon, t);
  const storageLabel = `${ramLabel} / ${ssdLabel}`;

  const specRows = isAccessory
    ? [
        product.brand ? { label: t.brandLabel, value: product.brand, icon: <PackageCheck size={14} /> } : null,
        { label: t.warranty, value: product.badge?.[lang] || t.hardwareWarranty6, icon: <ShieldCheck size={14} /> },
      ].filter(Boolean)
    : [
        { label: 'CPU', value: cpuLabel, icon: <Cpu size={14} /> },
        gpuLabel ? { label: 'GPU', value: gpuLabel, icon: <Zap size={14} /> } : null,
        { label: 'RAM/SSD', value: storageLabel, icon: <HardDrive size={14} /> },
        { label: t.screenLabel, value: screenLabel, icon: <Monitor size={14} /> },
      ].filter(Boolean);

  return (
    <article
      className={`product-card showcase-card ${compact ? 'compact' : ''}`}
      onClick={openDetail}
      role="button"
      tabIndex={0}
      aria-label={`${t.viewProductDetail} ${product.name}`}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          openDetail();
        }
      }}
    >
      <div className="product-art" style={{ '--accent': product.color }}>
        <span className="deal-badge">-{discount(product)}%</span>
        <img src={normalizeImagePath(product.image)} alt={product.name} loading="lazy" width="600" height="450" onError={productImageFallback} />
      </div>
      <div className="product-body">
        <div className="product-title-row">
          <div>
            <h3 title={product.name}>{product.name}</h3>
          </div>
        </div>
        <div className="spec-lines">
          {specRows.map((item) => (
            <span key={item.label}>
              {item.icon}
              <small>{item.label}</small>
              <b title={item.value}>{item.value}</b>
            </span>
          ))}
        </div>
        <div className="price-row">
          <div>
            <strong>{formatCurrency(product.price)}</strong>
            <del>{formatCurrency(product.oldPrice)}</del>
          </div>
        </div>
      </div>
    </article>
  );
}
