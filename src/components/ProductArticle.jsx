// ProductArticle — bài viết dài của PDP. Nguồn: Nhanh `content` → WP `post_content`
// (sync 15 phút). Fetch qua endpoint riêng để payload /products không phình.
import { useEffect, useState } from 'react';

export default function ProductArticle({ wooId }) {
  const [html, setHtml] = useState('');
  useEffect(() => {
    if (!wooId) return;
    let alive = true;
    const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
    fetch(`${restBase}product-article?id=${wooId}`)
      .then((r) => r.json())
      .then((d) => { if (alive) setHtml(d?.html || ''); })
      .catch(() => {});
    return () => { alive = false; };
  }, [wooId]);
  if (!html) return null;
  return (
    <section className="product-article">
      <div className="product-article-body" dangerouslySetInnerHTML={{ __html: html }} />
    </section>
  );
}
