CREATE TABLE IF NOT EXISTS competitor_products (
  id BIGSERIAL PRIMARY KEY,
  source TEXT NOT NULL,
  source_url TEXT NOT NULL UNIQUE,
  title TEXT,
  price INTEGER,
  description TEXT,
  images JSONB NOT NULL DEFAULT '[]'::jsonb,
  specs JSONB NOT NULL DEFAULT '{}'::jsonb,
  status TEXT NOT NULL DEFAULT 'ok',
  raw JSONB NOT NULL DEFAULT '{}'::jsonb,
  crawled_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_competitor_products_source ON competitor_products(source);
CREATE INDEX IF NOT EXISTS idx_competitor_products_status ON competitor_products(status);
