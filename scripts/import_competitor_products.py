#!/usr/bin/env python3
"""Import crawled competitor product JSON into PostgreSQL via psql."""

from __future__ import annotations

import json
import os
import subprocess
from pathlib import Path

DATA = Path("data/crawled/competitor_products.json")

DDL = """
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
"""

UPSERT = """
INSERT INTO competitor_products (source, source_url, title, price, description, images, specs, status, raw, crawled_at)
VALUES (%(source)s, %(source_url)s, %(title)s, %(price)s, %(description)s, %(images)s::jsonb, %(specs)s::jsonb, %(status)s, %(raw)s::jsonb, NOW())
ON CONFLICT (source_url) DO UPDATE SET
  source = EXCLUDED.source,
  title = EXCLUDED.title,
  price = EXCLUDED.price,
  description = EXCLUDED.description,
  images = EXCLUDED.images,
  specs = EXCLUDED.specs,
  status = EXCLUDED.status,
  raw = EXCLUDED.raw,
  crawled_at = NOW();
"""


def psql(sql: str) -> None:
    cmd = [
        "docker", "compose", "exec", "-T", "laprevive-db", "psql",
        "-U", os.environ.get("POSTGRES_USER", "laprevive"),
        "-d", os.environ.get("POSTGRES_DB", "laprevive"),
        "-v", "ON_ERROR_STOP=1",
    ]
    subprocess.run(cmd, input=sql, text=True, check=True)


def quote(value) -> str:
    if value is None:
        return "NULL"
    return "'" + str(value).replace("'", "''") + "'"


def main() -> None:
    payload = json.loads(DATA.read_text(encoding="utf-8"))
    psql(DDL)
    statements = []
    for row in payload["products"]:
        values = {
            "source": row.get("source"),
            "source_url": row.get("url"),
            "title": row.get("title") or None,
            "price": row.get("price"),
            "description": row.get("description") or None,
            "images": json.dumps(row.get("images") or [], ensure_ascii=False),
            "specs": json.dumps(row.get("specs") or {}, ensure_ascii=False),
            "status": row.get("status") or "ok",
            "raw": json.dumps(row, ensure_ascii=False),
        }
        sql = UPSERT
        for key, value in values.items():
            if key == "price" and value is not None:
                replacement = str(int(value))
            else:
                replacement = quote(value)
            sql = sql.replace(f"%({key})s", replacement)
        statements.append(sql)
    psql("\n".join(statements))
    print(f"imported {len(statements)} competitor products")


if __name__ == "__main__":
    main()
