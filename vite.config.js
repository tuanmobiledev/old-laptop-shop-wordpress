import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// SPAs run under WordPress theme, so all asset URLs must be absolute
// relative to the theme directory. Without `base`, Vite uses `/assets/...`
// which resolves to https://maytinhthuduc.com/assets/ on prod (404).
// Theme folder is the actual server path:
//   https://maytinhthuduc.com/wp-content/themes/oscar-shop/
// Caught 2026-08-04: 6 woff2 fonts 404 on prod, only the main and lazy
// JS chunks had relative URL issues too. Fix pinned via base config.
export default defineConfig({
  plugins: [react()],
  base: '/wp-content/themes/oscar-shop/',
  build: {
    outDir: 'dist',
  },
});
