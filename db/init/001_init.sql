-- PostgreSQL bootstrap for LapRevive / Laptop OSCAR Thủ Đức.
-- This file is executed automatically only when the data volume is empty.

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

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id BIGSERIAL PRIMARY KEY,
  email TEXT NOT NULL UNIQUE,
  source TEXT NOT NULL DEFAULT 'footer',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS contact_requests (
  id BIGSERIAL PRIMARY KEY,
  name TEXT,
  phone TEXT,
  email TEXT,
  subject TEXT,
  message TEXT,
  status TEXT NOT NULL DEFAULT 'new',
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_products_category ON products(category);
CREATE INDEX IF NOT EXISTS idx_products_brand ON products(brand);
CREATE INDEX IF NOT EXISTS idx_products_demand ON products(demand);
CREATE INDEX IF NOT EXISTS idx_products_gpu ON products(gpu);
CREATE INDEX IF NOT EXISTS idx_contact_requests_status ON contact_requests(status);
