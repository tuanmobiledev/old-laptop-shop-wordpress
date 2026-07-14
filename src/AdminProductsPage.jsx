import React, { useState } from 'react';
import { Check, Edit3, ImagePlus, Link as LinkIcon, LogOut, Plus, Save, ShieldCheck, Trash2, X } from 'lucide-react';
import { formatCurrency } from './data.js';

const restBase = () => window.OSCAR_WP?.restUrl || '/wp-json/oscar/v1/';
const normalizeImagePath = (path) => typeof path === 'string' && path.startsWith('/product-images/') ? path.replace(/\.jpg(?=($|[?#]))/i, '.webp') : path;
const imageFallback = (event) => {
  const img = event.currentTarget;
  if (img.dataset.fallbackApplied === 'true') return;
  img.dataset.fallbackApplied = 'true';
  img.src = '/oscar-cover.webp';
};

const uploadMediaFile = async (file) => {
  const body = new FormData();
  body.append('file', file);
  const response = await fetch(`${restBase()}admin/media`, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-WP-Nonce': window.OSCAR_WP?.nonce || '' },
    body,
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok || !payload.url) throw new Error(payload.error || 'upload_failed');
  return payload.url;
};

const imageToWebpFile = (file, quality = 0.82) => new Promise((resolve, reject) => {
  if (file.type === 'image/webp') {
    resolve(file);
    return;
  }

  const image = new Image();
  const objectUrl = URL.createObjectURL(file);
  image.onload = () => {
    const canvas = document.createElement('canvas');
    canvas.width = image.naturalWidth || image.width;
    canvas.height = image.naturalHeight || image.height;
    canvas.getContext('2d').drawImage(image, 0, 0);
    canvas.toBlob((blob) => {
      URL.revokeObjectURL(objectUrl);
      if (!blob) {
        reject(new Error('webp_conversion_failed'));
        return;
      }
      const webpName = (file.name || 'image').replace(/\.[a-z0-9]+$/i, '') + '.webp';
      resolve(new File([blob], webpName, { type: 'image/webp', lastModified: Date.now() }));
    }, 'image/webp', quality);
  };
  image.onerror = () => {
    URL.revokeObjectURL(objectUrl);
    reject(new Error('image_load_failed'));
  };
  image.src = objectUrl;
});

const createProductId = () => Date.now();
const uniqueList = (items) => [...new Set((items || []).filter(Boolean))];
const getProductImages = (product) => uniqueList([...(Array.isArray(product.images) ? product.images : []), product.image].map(normalizeImagePath));

const productDraft = () => ({
  id: '', name: '', category: 'laptop-cu', brand: 'Dell',
  cpu: '', gpu: '', ram: '', ssd: '', screen: '',
  demand: 'office', stock: 1, price: 0, oldPrice: 0,
  image: '/oscar-cover.webp', images: ['/oscar-cover.webp'], video: '',
  color: '#255f85', batteryWh: '', batteryRuntime: '',
  condition: { vi: 'Máy cũ', en: 'Used' },
  badge: { vi: 'Liên hệ xác nhận hàng', en: 'Contact to confirm' },
  specs: { vi: [], en: [] },
  promo: { vi: 'Hàng tuyển chọn tại Laptop OSCAR', en: 'Laptop OSCAR selected device' },
  rating: 4.7, reviews: 0, type: 'catalog',
});

const normalizeAdminProduct = (draft) => {
  const images = getProductImages(draft);
  const mainImage = normalizeImagePath(draft.image || images[0]) || '/oscar-cover.webp';
  return {
    ...productDraft(),
    ...draft,
    image: mainImage,
    images: uniqueList([mainImage, ...images]),
    video: draft.video || '',
    id: Number(draft.id) || createProductId(),
    price: Number(draft.price) || 0,
    oldPrice: Number(draft.oldPrice) || Number(draft.price) || 0,
    stock: 1,
    batteryWh: draft.batteryWh ? Number(draft.batteryWh) : undefined,
    specs: {
      vi: [draft.cpu, draft.gpu, `${draft.ram} RAM`, `${draft.ssd} SSD`, draft.screen].filter(Boolean),
      en: [draft.cpu, draft.gpu, `${draft.ram} RAM`, `${draft.ssd} SSD`, draft.screen].filter(Boolean),
    },
  };
};

export default function AdminProductsPage({ products, setProducts, t }) {
  const [session, setSession] = useState('loading');
  const [editing, setEditing] = useState(null);
  const [draft, setDraft] = useState(productDraft());
  const [query, setQuery] = useState('');
  const [mediaUrl, setMediaUrl] = useState('');
  React.useEffect(() => {
    fetch(`${restBase()}admin/session`, { credentials: 'same-origin', headers: { 'X-WP-Nonce': window.OSCAR_WP?.nonce || '' } })
      .then((response) => setSession(response.ok ? 'authenticated' : 'anonymous'))
      .catch(() => setSession('anonymous'));
  }, []);
  const isAdmin = session === 'authenticated';
  const visibleProducts = products.filter((p) =>
    `${p.name} ${p.brand} ${p.cpu} ${p.gpu}`.toLowerCase().includes(query.toLowerCase())
  );
  const draftImages = getProductImages(draft);
  const draftMedia = [
    ...draftImages.map((src, index) => ({ type: 'image', src, index })),
    ...(draft.video ? [{ type: 'video', src: draft.video, index: 0 }] : []),
  ];
  const logout = () => {
    window.location.assign(`/wp-login.php?action=logout&redirect_to=${encodeURIComponent(window.location.origin)}`);
  };
  const startCreate = () => { setEditing('new'); setDraft(productDraft()); setMediaUrl(''); };
  const startEdit = (p) => { setEditing(p.id); setDraft({ ...p, images: getProductImages(p), batteryWh: p.batteryWh || '' }); setMediaUrl(''); };
  const addImage = (image) => setDraft((cur) => {
    const images = uniqueList([...getProductImages(cur).filter((src) => src !== '/oscar-cover.webp'), normalizeImagePath(image)]);
    return { ...cur, image: images[0] || image, images };
  });
  const removeImage = (index) => setDraft((cur) => {
    const images = getProductImages(cur).filter((_, i) => i !== index);
    return { ...cur, images, image: images[0] || '/oscar-cover.webp' };
  });
  const setMainImage = (image) => setDraft((cur) => ({ ...cur, image, images: uniqueList([image, ...getProductImages(cur).filter((src) => src !== image)]) }));
  const addMediaUrl = () => {
    const url = mediaUrl.trim();
    if (!url) return;
    if (/\.(mp4|webm|mov)([?#].*)?$/i.test(url)) setDraft((cur) => ({ ...cur, video: url }));
    else addImage(url);
    setMediaUrl('');
  };
  const handleMediaUpload = async (event) => {
    const file = event.target.files?.[0];
    if (!file) return;
    const isImage = file.type.startsWith('image/');
    const isVideo = file.type.startsWith('video/');
    if (!isImage && !isVideo) {
      window.alert('Vui lòng chọn file hình ảnh hoặc video.');
      event.target.value = '';
      return;
    }
    const limit = isVideo ? 12 * 1024 * 1024 : 2 * 1024 * 1024;
    if (file.size > limit) {
      window.alert(isVideo ? 'Video tối đa 12MB.' : 'Hình ảnh tối đa 2MB.');
      event.target.value = '';
      return;
    }
    try {
      const mediaFile = isImage ? await imageToWebpFile(file) : file;
      const mediaUrl = await uploadMediaFile(mediaFile);
      if (isVideo) setDraft((cur) => ({ ...cur, video: mediaUrl }));
      else addImage(mediaUrl);
    } catch {
      window.alert('Upload media thất bại. Vui lòng thử lại.');
    } finally {
      event.target.value = '';
    }
  };
  const saveProduct = (e) => {
    e.preventDefault();
    const saved = normalizeAdminProduct(draft);
    setProducts((cur) => editing === 'new' ? [saved, ...cur] : cur.map((i) => i.id === editing ? saved : i));
    setEditing(null);
    setDraft(productDraft());
  };
  const removeProduct = (id) => {
    if (!window.confirm(t.adminDeleteConfirm)) return;
    setProducts((cur) => cur.filter((i) => i.id !== id));
  };
  const field = (label, key, type = 'text') => (
    <label>
      <span>{label}</span>
      <input
        type={type}
        value={draft[key] || ''}
        onChange={(e) => setDraft((cur) => ({ ...cur, [key]: e.target.value }))}
        required={['name', 'price'].includes(key)}
      />
    </label>
  );

  if (session === 'loading') return <section className="section shell admin-page admin-login"><p>Đang kiểm tra phiên quản trị…</p></section>;
  if (!isAdmin) return (
    <section className="section shell admin-page admin-login" id="admin">
      <div className="admin-login-card">
        <span className="eyebrow"><ShieldCheck size={16} /> {t.adminOnly}</span>
        <h1>{t.adminLoginTitle}</h1>
        <p>Đăng nhập WordPress bằng tài khoản có quyền quản lý cửa hàng để tiếp tục.</p>
        <a className="primary" href={`/wp-login.php?redirect_to=${encodeURIComponent(window.location.href)}`}>{t.login}</a>
      </div>
    </section>
  );

  return (
    <section className="section shell admin-page" id="admin">
      <div className="admin-hero">
        <div>
          <span className="eyebrow"><ShieldCheck size={16} /> {t.adminDashboard}</span>
          <h1>{t.adminTitle}</h1>
          <p>{t.adminDesc}</p>
        </div>
        <div className="admin-actions">
          <button className="primary" onClick={startCreate}><Plus size={18} /> {t.addProduct}</button>
          <button className="secondary dark" onClick={logout}><LogOut size={18} /> {t.logout}</button>
        </div>
      </div>
      {editing && (
        <form className="admin-form" onSubmit={saveProduct}>
          <div className="admin-form-head">
            <h2>{editing === 'new' ? t.addProduct : t.editProduct}</h2>
            <button type="button" onClick={() => setEditing(null)}><X size={18} /></button>
          </div>
          <div className="admin-fields">
            {field(t.adminProductName, 'name')}
            {field(t.filterBrand, 'brand')}
            {field('CPU', 'cpu')}
            {field(t.adminGpu, 'gpu')}
            {field('RAM', 'ram')}
            {field('SSD', 'ssd')}
            {field(t.screenLabel, 'screen')}
            {field(t.adminSalePrice, 'price', 'number')}
            {field(t.adminOldPrice, 'oldPrice', 'number')}
            <div className="admin-media-panel">
              <div className="admin-media-head">
                <div><span>Media sản phẩm</span><small>{draftImages.length} ảnh{draft.video ? ' + 1 video' : ''}</small></div>
                <label className="admin-upload-button compact">
                  <ImagePlus size={17} />
                  <span>Upload ảnh/video</span>
                  <input type="file" accept="image/*,video/*" onChange={handleMediaUpload} />
                </label>
              </div>
              <div className="admin-url-row">
                <LinkIcon size={17} />
                <input value={mediaUrl} onChange={(e) => setMediaUrl(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); addMediaUrl(); } }} placeholder="Dán link ảnh hoặc video rồi bấm Thêm" />
                <button type="button" onClick={addMediaUrl}>Thêm</button>
              </div>
              <div className="admin-media-grid">
                {draftMedia.map((media) => (
                  <div className={`admin-media-tile ${media.type === 'image' && draft.image === media.src ? 'active' : ''}`} key={`${media.type}-${media.src}-${media.index}`}>
                    {media.type === 'video' ? <video src={media.src} controls /> : <img src={normalizeImagePath(media.src) || '/oscar-cover.webp'} alt="" onError={imageFallback} />}
                    <div>
                      {media.type === 'image' && <button type="button" onClick={() => setMainImage(media.src)}>{draft.image === media.src ? <><Check size={14} /> Chính</> : 'Đặt chính'}</button>}
                      <button className="danger" type="button" onClick={() => media.type === 'video' ? setDraft((cur) => ({ ...cur, video: '' })) : removeImage(media.index)}>Xóa</button>
                    </div>
                  </div>
                ))}
              </div>
              <small className="admin-help">File upload được lưu trên server trong Docker volume và trả về link /uploads/... Ảnh chính nằm đầu danh sách.</small>
            </div>
            <label>
              <span>{t.category}</span>
              <select value={draft.category} onChange={(e) => setDraft((cur) => ({ ...cur, category: e.target.value }))}>
                <option value="laptop-cu">Laptop</option>
                <option value="linh-kien">{t.partsCategory}</option>
              </select>
            </label>
            <label>
              <span>{t.demand}</span>
              <select value={draft.demand} onChange={(e) => setDraft((cur) => ({ ...cur, demand: e.target.value }))}>
                <option value="office">{t.office}</option>
                <option value="student">{t.student}</option>
                <option value="gaming">{t.gaming}</option>
                <option value="creator">{t.creator}</option>
                <option value="render">{t.render}</option>
              </select>
            </label>
          </div>
          <div className="admin-save-bar"><button className="primary" type="submit"><Save size={17} /> {t.saveProduct}</button><button type="button" onClick={() => setEditing(null)}>Hủy</button></div>
        </form>
      )}
      <div className="admin-toolbar">
        <strong>{products.length} {t.productCount}</strong>
        <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={t.adminSearchPlaceholder} />
      </div>
      <div className="admin-table">
        {visibleProducts.map((p) => (
          <article key={p.id}>
            <img src={normalizeImagePath(p.image || p.images?.[0]) || '/oscar-cover.webp'} alt="" loading="lazy" onError={imageFallback} />
            <div>
              <h3>{p.name}</h3>
              <p>{p.brand} • {p.cpu} • {p.ram}/{p.ssd}</p>
              <strong>{formatCurrency(p.price)}</strong>
              <small>{getProductImages(p).length} ảnh{p.video ? ' + video' : ''}</small>
            </div>
            <button onClick={() => startEdit(p)}><Edit3 size={17} /> {t.edit}</button>
            <button className="danger" onClick={() => removeProduct(p.id)}><Trash2 size={17} /> {t.delete}</button>
          </article>
        ))}
      </div>
    </section>
  );
}
