#!/usr/bin/env node
import fs from 'node:fs';
import path from 'node:path';

const root = path.resolve(new URL('..', import.meta.url).pathname);
const inputArg = process.argv[2] || path.join(root, 'exports', 'products-google-sheets.csv');
const inputPath = path.resolve(process.cwd(), inputArg);
const dataPath = path.join(root, 'src', 'data.js');

const columnsWithNumbers = new Set(['id', 'batteryWh', 'stock', 'rating', 'reviews', 'price', 'oldPrice']);
const columns = [
  'id', 'name', 'category', 'brand', 'type', 'cpu', 'gpu', 'ram', 'ssd', 'screen',
  'batteryWh', 'batteryRuntime', 'demand', 'stock', 'rating', 'reviews',
  'promo_vi', 'promo_en', 'price', 'oldPrice', 'condition_vi', 'condition_en',
  'badge_vi', 'badge_en', 'specs_vi', 'specs_en', 'variants_json', 'color', 'image', 'sourceUrl'
];

const parseCsv = (text) => {
  const rows = [];
  let row = [];
  let field = '';
  let quoted = false;
  for (let i = 0; i < text.length; i += 1) {
    const char = text[i];
    if (quoted) {
      if (char === '"' && text[i + 1] === '"') {
        field += '"';
        i += 1;
      } else if (char === '"') {
        quoted = false;
      } else {
        field += char;
      }
    } else if (char === '"') {
      quoted = true;
    } else if (char === ',') {
      row.push(field);
      field = '';
    } else if (char === '\n') {
      row.push(field);
      rows.push(row);
      row = [];
      field = '';
    } else if (char !== '\r') {
      field += char;
    }
  }
  if (field || row.length) {
    row.push(field);
    rows.push(row);
  }
  return rows.filter((items) => items.some((item) => item !== ''));
};

const parseNumber = (value) => {
  let trimmed = String(value ?? '').trim();
  if (!trimmed) return undefined;
  trimmed = trimmed.replace(/\s/g, '');
  if (trimmed.includes(',') && trimmed.includes('.')) {
    trimmed = trimmed.replace(/\./g, '').replace(',', '.');
  } else if ((trimmed.match(/\./g) || []).length > 1) {
    trimmed = trimmed.replace(/\./g, '');
  } else if (trimmed.includes(',')) {
    trimmed = trimmed.replace(',', '.');
  }
  const number = Number(trimmed.replace(/[^0-9.-]/g, ''));
  return Number.isFinite(number) ? number : undefined;
};

const rowToProduct = (row, headers) => {
  const raw = Object.fromEntries(headers.map((header, index) => [header, row[index] ?? '']));
  const product = {};
  for (const column of columns) {
    if (column.endsWith('_vi') || column.endsWith('_en') || column === 'variants_json') continue;
    const value = raw[column];
    if (columnsWithNumbers.has(column)) {
      const number = parseNumber(value);
      if (number !== undefined) product[column] = number;
    } else if (value !== '') {
      product[column] = value;
    }
  }
  product.promo = { vi: raw.promo_vi || '', en: raw.promo_en || raw.promo_vi || '' };
  product.condition = { vi: raw.condition_vi || '', en: raw.condition_en || raw.condition_vi || '' };
  product.badge = { vi: raw.badge_vi || '', en: raw.badge_en || raw.badge_vi || '' };
  product.specs = {
    vi: (raw.specs_vi || '').split('|').map((item) => item.trim()).filter(Boolean),
    en: (raw.specs_en || raw.specs_vi || '').split('|').map((item) => item.trim()).filter(Boolean),
  };
  if (raw.variants_json?.trim()) {
    try {
      product.variants = JSON.parse(raw.variants_json).map((variant) => ({
        ...variant,
        price: Number(variant.price),
      }));
    } catch (error) {
      console.error(`Invalid variants_json for product ${product.id || product.name}: ${error.message}`);
      process.exit(1);
    }
  }
  return product;
};

const formatValue = (value) => {
  if (Array.isArray(value)) return `[${value.map(formatValue).join(', ')}]`;
  if (value && typeof value === 'object') {
    return `{ ${Object.entries(value).map(([key, entry]) => `${key}: ${formatValue(entry)}`).join(', ')} }`;
  }
  return JSON.stringify(value);
};

const formatProduct = (product) => `  ${formatValue(product)}`;

if (!fs.existsSync(inputPath)) {
  console.error(`CSV not found: ${inputPath}`);
  process.exit(1);
}

const rows = parseCsv(fs.readFileSync(inputPath, 'utf8'));
const headers = rows.shift();
const missing = columns.filter((column) => !headers.includes(column));
if (missing.length) {
  console.error(`Missing required columns: ${missing.join(', ')}`);
  process.exit(1);
}

const products = rows.map((row) => rowToProduct(row, headers));
const seenIds = new Set();
for (const product of products) {
  if (!Number.isInteger(product.id) || product.id <= 0) {
    console.error(`Invalid numeric id for product: ${product.name || '(unnamed)'}`);
    process.exit(1);
  }
  if (seenIds.has(product.id)) {
    console.error(`Duplicate product id: ${product.id}`);
    process.exit(1);
  }
  seenIds.add(product.id);
}
const dataSource = fs.readFileSync(dataPath, 'utf8');
const start = dataSource.indexOf('export const products = [');
const end = dataSource.indexOf('\n]\n', start);
if (start === -1 || end === -1) {
  console.error('Could not locate products array in src/data.js');
  process.exit(1);
}

const nextProductsBlock = `export const products = [\n${products.map(formatProduct).join(',\n')}\n]`;
const nextSource = dataSource.slice(0, start) + nextProductsBlock + dataSource.slice(end + 2);
fs.writeFileSync(dataPath, nextSource, 'utf8');
console.log(`Synced ${products.length} products from ${path.relative(root, inputPath)} to src/data.js`);
