#!/usr/bin/env node
import { spawnSync } from 'node:child_process';
import { products } from '../src/data.js';

const dbUser = process.env.POSTGRES_USER || 'laprevive';
const dbName = process.env.POSTGRES_DB || 'laprevive';
const service = process.env.DB_SERVICE || 'laprevive-db';

const sqlString = (value) => {
  if (value === undefined || value === null || value === '') return 'NULL';
  return `'${String(value).replace(/'/g, "''")}'`;
};

const sqlNumber = (value, fallback = 0) => {
  const number = Number(value);
  return Number.isFinite(number) ? String(number) : String(fallback);
};

const sqlJson = (value) => `${sqlString(JSON.stringify(value ?? null))}::jsonb`;

const ddl = `
CREATE TABLE IF NOT EXISTS products (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  category TEXT NOT NULL,
  brand TEXT,
  type TEXT,
  cpu TEXT,
  gpu TEXT,
  ram TEXT,
  ssd TEXT,
  screen TEXT,
  battery_wh INTEGER,
  battery_runtime TEXT,
  demand TEXT,
  stock INTEGER NOT NULL DEFAULT 0,
  rating NUMERIC(2,1),
  reviews INTEGER NOT NULL DEFAULT 0,
  price INTEGER NOT NULL DEFAULT 0,
  old_price INTEGER,
  condition_vi TEXT,
  condition_en TEXT,
  badge_vi TEXT,
  badge_en TEXT,
  promo_vi TEXT,
  promo_en TEXT,
  image TEXT,
  color TEXT,
  source_url TEXT,
  specs_vi JSONB NOT NULL DEFAULT '[]'::jsonb,
  specs_en JSONB NOT NULL DEFAULT '[]'::jsonb,
  variants JSONB,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
ALTER TABLE products ADD COLUMN IF NOT EXISTS gpu TEXT;
ALTER TABLE products ADD COLUMN IF NOT EXISTS battery_wh INTEGER;
ALTER TABLE products ADD COLUMN IF NOT EXISTS battery_runtime TEXT;
ALTER TABLE products ADD COLUMN IF NOT EXISTS source_url TEXT;
ALTER TABLE products ADD COLUMN IF NOT EXISTS variants JSONB;
CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand);
CREATE INDEX IF NOT EXISTS idx_products_demand ON products(demand);
CREATE INDEX IF NOT EXISTS idx_products_gpu ON products(gpu);
`;

const rows = products.map((product) => `(
  ${sqlString(product.id)},
  ${sqlString(product.name)},
  ${sqlString(product.category)},
  ${sqlString(product.brand)},
  ${sqlString(product.type)},
  ${sqlString(product.cpu)},
  ${sqlString(product.gpu)},
  ${sqlString(product.ram)},
  ${sqlString(product.ssd)},
  ${sqlString(product.screen)},
  ${product.batteryWh == null ? 'NULL' : sqlNumber(product.batteryWh)},
  ${sqlString(product.batteryRuntime)},
  ${sqlString(product.demand)},
  ${sqlNumber(product.stock)},
  ${product.rating == null ? 'NULL' : sqlNumber(product.rating)},
  ${sqlNumber(product.reviews)},
  ${sqlNumber(product.price)},
  ${product.oldPrice == null ? 'NULL' : sqlNumber(product.oldPrice)},
  ${sqlString(product.condition?.vi)},
  ${sqlString(product.condition?.en)},
  ${sqlString(product.badge?.vi)},
  ${sqlString(product.badge?.en)},
  ${sqlString(product.promo?.vi)},
  ${sqlString(product.promo?.en)},
  ${sqlString(product.image)},
  ${sqlString(product.color)},
  ${sqlString(product.sourceUrl)},
  ${sqlJson(product.specs?.vi || [])},
  ${sqlJson(product.specs?.en || [])},
  ${product.variants ? sqlJson(product.variants) : 'NULL'}
)`).join(',\n');

const dml = rows ? `
WITH incoming (
  id, name, category, brand, type, cpu, gpu, ram, ssd, screen, battery_wh, battery_runtime,
  demand, stock, rating, reviews, price, old_price, condition_vi, condition_en, badge_vi,
  badge_en, promo_vi, promo_en, image, color, source_url, specs_vi, specs_en, variants
) AS (VALUES
${rows}
), upserted AS (
  INSERT INTO products (
    id, name, category, brand, type, cpu, gpu, ram, ssd, screen, battery_wh, battery_runtime,
    demand, stock, rating, reviews, price, old_price, condition_vi, condition_en, badge_vi,
    badge_en, promo_vi, promo_en, image, color, source_url, specs_vi, specs_en, variants, updated_at
  )
  SELECT *, NOW() FROM incoming
  ON CONFLICT (id) DO UPDATE SET
    name = EXCLUDED.name,
    category = EXCLUDED.category,
    brand = EXCLUDED.brand,
    type = EXCLUDED.type,
    cpu = EXCLUDED.cpu,
    gpu = EXCLUDED.gpu,
    ram = EXCLUDED.ram,
    ssd = EXCLUDED.ssd,
    screen = EXCLUDED.screen,
    battery_wh = EXCLUDED.battery_wh,
    battery_runtime = EXCLUDED.battery_runtime,
    demand = EXCLUDED.demand,
    stock = EXCLUDED.stock,
    rating = EXCLUDED.rating,
    reviews = EXCLUDED.reviews,
    price = EXCLUDED.price,
    old_price = EXCLUDED.old_price,
    condition_vi = EXCLUDED.condition_vi,
    condition_en = EXCLUDED.condition_en,
    badge_vi = EXCLUDED.badge_vi,
    badge_en = EXCLUDED.badge_en,
    promo_vi = EXCLUDED.promo_vi,
    promo_en = EXCLUDED.promo_en,
    image = EXCLUDED.image,
    color = EXCLUDED.color,
    source_url = EXCLUDED.source_url,
    specs_vi = EXCLUDED.specs_vi,
    specs_en = EXCLUDED.specs_en,
    variants = EXCLUDED.variants,
    updated_at = NOW()
  RETURNING id
)
DELETE FROM products WHERE id NOT IN (SELECT id FROM incoming);
` : 'TRUNCATE products;';

const verify = "SELECT json_build_object('count', COUNT(*), 'with_gpu', COUNT(*) FILTER (WHERE COALESCE(gpu, '') <> '')) FROM products;";
const sql = `${ddl}\nBEGIN;\n${dml}\nCOMMIT;\n${verify}`;

const result = spawnSync('docker', ['compose', 'exec', '-T', service, 'psql', '-U', dbUser, '-d', dbName, '-v', 'ON_ERROR_STOP=1'], {
  input: sql,
  encoding: 'utf8',
  stdio: ['pipe', 'pipe', 'pipe'],
});

if (result.error) {
  console.error(`Could not run docker compose exec: ${result.error.message}`);
  process.exit(1);
}
if (result.status !== 0) {
  console.error(result.stderr || result.stdout);
  process.exit(result.status || 1);
}

process.stdout.write(result.stdout);
console.log(`Synced ${products.length} products from src/data.js to PostgreSQL service ${service}`);
