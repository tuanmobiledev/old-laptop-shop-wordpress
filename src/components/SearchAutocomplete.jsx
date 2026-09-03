// SearchAutocomplete — dropdown of product suggestions + popular keywords.
// Extracted from Header (T3 refactor) so Header stays focused on topbar layout
// and theme/url-state wiring. The "open" flag and the source list stay in
// Header because Header already owns the surrounding <div className="global-search">;
// rendering the dropdown is purely presentational here.

import { formatCurrency } from '../data.js';
import { normalizeImagePath } from '../utils.js';
import { SmartImage } from './SmartImage.jsx';

export default function SearchAutocomplete({ suggestions, chooseProduct, t }) {
  return (
    <div className="search-suggestions rich-search">
      {suggestions.map((product) => (
        <button
          key={product.id}
          onMouseDown={(event) => event.preventDefault()}
          onClick={() => chooseProduct(product)}
          aria-label={`${t.viewProductDetail} ${product.name}`}
        >
          <SmartImage src={normalizeImagePath(product.image_webp || product.image)} srcFallback={normalizeImagePath(product.image)} alt="" width={46} height={38} sizes="46px" />
          <span>
            {product.name}
            <small>{product.brand} • {product.cpu}</small>
          </span>
          <strong>{formatCurrency(product.price)}</strong>
        </button>
      ))}
    </div>
  );
}
