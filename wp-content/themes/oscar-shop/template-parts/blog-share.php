<?php
/**
 * Blog post share buttons (Facebook + Zalo + Copy link)
 *
 * Boss 2026-08-25 — Blog phase P1.
 *
 * @package Oscar_Shop
 */

$share = oscar_blog_share_links();
?>
<div class="oscar-share" aria-label="Chia sẻ bài viết">
  <span class="oscar-share-label">Chia sẻ:</span>
  <a href="<?php echo esc_url($share['facebook']); ?>" target="_blank" rel="noopener noreferrer" class="oscar-share-btn oscar-share-fb" aria-label="Chia sẻ lên Facebook">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.198 21.5h4v-8.01h3.604l.396-3.98h-4V7.5a1 1 0 0 1 1-1h3v-4h-3a5 5 0 0 0-5 5v2.01h-2l-.396 3.98h2.396v8.01Z"/></svg>
    <span class="oscar-share-text">Facebook</span>
  </a>
  <a href="<?php echo esc_url($share['zalo']); ?>" target="_blank" rel="noopener noreferrer" class="oscar-share-btn oscar-share-zalo" aria-label="Chia sẻ qua Zalo">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.477 2 2 6.145 2 11.243c0 2.908 1.434 5.502 3.678 7.2V22l3.395-1.866c.926.258 1.91.397 2.927.397 5.523 0 10-4.145 10-9.243S17.523 2 12 2Zm-.594 12.617-2.55-2.722-4.972 2.722L9.41 8.382l2.604 2.722 4.918-2.722-5.526 6.235Z"/></svg>
    <span class="oscar-share-text">Zalo</span>
  </a>
  <button type="button" class="oscar-share-btn oscar-share-copy" data-url="<?php echo esc_attr($share['copy_url']); ?>" aria-label="Sao chép link bài viết">
    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    <span class="oscar-share-text oscar-share-copy-text">Copy link</span>
  </button>
</div>

<script>
(function(){
  function initOscarShare() {
    var btn = document.querySelector('.oscar-share-copy');
    if (!btn || btn.__oscarBound) return;
    btn.__oscarBound = true;
    var labelEl = btn.querySelector('.oscar-share-copy-text');
    var originalLabel = labelEl ? labelEl.textContent : 'Copy link';
    btn.addEventListener('click', function(){
      var url = btn.getAttribute('data-url') || '';
      if (!url) return;
      var fallback = function(){
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.setAttribute('readonly', '');
        ta.style.position = 'absolute';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
      };
      var done = function(){
        if (labelEl) {
          labelEl.textContent = 'Đã copy!';
          setTimeout(function(){ labelEl.textContent = originalLabel; }, 2000);
        }
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(done, function(){ fallback(); done(); });
      } else {
        fallback(); done();
      }
    });
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initOscarShare);
  } else {
    initOscarShare();
  }
})();
</script>