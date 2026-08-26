<?php
/**
 * Blog post Table of Contents (TOC)
 * Auto-rendered từ H2/H3 trong content. Skip nếu post không có heading.
 *
 * Boss 2026-08-25 — Blog phase P1.
 *
 * @package Oscar_Shop
 */

$post_id = get_the_ID();
if (!$post_id) {
    return;
}
$post        = get_post($post_id);
$toc_content = $post ? $post->post_content : '';
$toc         = oscar_blog_extract_toc($toc_content);

if (empty($toc)) {
    return; // Không có H2/H3 → không hiển thị TOC
}
?>
<nav class="oscar-toc" aria-labelledby="oscar-toc-heading">
  <h2 id="oscar-toc-heading" class="oscar-toc-title">
    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
    Mục lục bài viết
  </h2>
  <ol class="oscar-toc-list" role="list">
    <?php foreach ($toc as $item) : ?>
      <li class="oscar-toc-item oscar-toc-l<?php echo (int) $item['level']; ?>">
        <a href="#<?php echo esc_attr($item['id']); ?>" class="oscar-toc-link">
          <?php echo esc_html($item['text']); ?>
        </a>
      </li>
    <?php endforeach; ?>
  </ol>
</nav>