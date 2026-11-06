// ProductArticle — bài viết dài của PDP. Nguồn: Nhanh `content` → WP `post_content`
// (sync 15 phút). Fetch qua endpoint riêng để payload /products không phình.
import { useEffect, useState } from 'react';
import { contacts, formatTel } from '../data.js';

// Số hotline trong bài (Nhanh) là text trơn -> bấm trên mobile không gọi được.
// Khớp cả "0984.496.260" và "0984 496 260".
const PHONE_RE = new RegExp(contacts.hotline.replace(/\./g, '[.\\s-]?'), 'g');
// shortcut: bỏ qua nếu bài đã có sẵn link tel:, nâng cấp khi content Nhanh tự chèn link
const linkifyHotline = (html) => (html.includes('href="tel:')
  ? html
  : html.replace(PHONE_RE, (m) => `<a href="${formatTel(contacts.hotline)}">${m}</a>`));

export default function ProductArticle({ wooId }) {
  const [html, setHtml] = useState('');
  useEffect(() => {
    if (!wooId) return;
    let alive = true;
    const restBase = window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
    fetch(`${restBase}product-article?id=${wooId}`)
      .then((r) => r.json())
      .then((d) => { if (alive) setHtml(linkifyHotline(d?.html || '')); })
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
