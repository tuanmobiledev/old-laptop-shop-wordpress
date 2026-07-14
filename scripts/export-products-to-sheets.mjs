#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';
import { products } from '../src/data.js';

const root = path.resolve(new URL('..', import.meta.url).pathname);
const outDir = path.join(root, 'exports');
fs.mkdirSync(outDir, { recursive: true });

const columns = [
  'id', 'name', 'category', 'brand', 'type', 'cpu', 'gpu', 'ram', 'ssd', 'screen',
  'batteryWh', 'batteryRuntime', 'demand', 'stock', 'rating', 'reviews',
  'promo_vi', 'promo_en', 'price', 'oldPrice', 'condition_vi', 'condition_en',
  'badge_vi', 'badge_en', 'specs_vi', 'specs_en', 'variants_json', 'color', 'image', 'sourceUrl'
];

const valueFor = (product, column, index) => {
  if (column === 'id') return Number.isFinite(Number(product.id)) ? Number(product.id) : index + 1;
  if (column === 'variants_json') return product.variants?.length ? JSON.stringify(product.variants) : '';
  if (column.endsWith('_vi')) {
    const key = column.slice(0, -3);
    const value = product[key];
    return Array.isArray(value?.vi) ? value.vi.join(' | ') : value?.vi ?? '';
  }
  if (column.endsWith('_en')) {
    const key = column.slice(0, -3);
    const value = product[key];
    return Array.isArray(value?.en) ? value.en.join(' | ') : value?.en ?? '';
  }
  return product[column] ?? '';
};

const csvEscape = (value) => {
  const text = String(value ?? '');
  return /[",\n\r]/.test(text) ? `"${text.replaceAll('"', '""')}"` : text;
};

const csv = [
  columns.join(','),
  ...products.map((product, index) => columns.map((column) => csvEscape(valueFor(product, column, index))).join(',')),
].join('\n') + '\n';

fs.writeFileSync(path.join(outDir, 'products-google-sheets.csv'), csv, 'utf8');
fs.writeFileSync(path.join(outDir, 'products-google-sheets.json'), JSON.stringify(products, null, 2) + '\n', 'utf8');
console.log(`Exported ${products.length} products to exports/products-google-sheets.csv`);
