#!/usr/bin/env python3
"""Crawl public product metadata from hienhuy.vn and nextgold.vn.

The crawler uses public robots/sitemaps, keeps requests slow, and writes a JSON
review file. It does not bypass logins, captchas, or private APIs.
"""

from __future__ import annotations

import html
import json
import re
import time
import urllib.parse
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOTS = {
    "hienhuy": "https://hienhuy.vn",
    "nextgold": "https://nextgold.vn",
}
UA = "Mozilla/5.0 (compatible; LapReviveProductCrawler/1.0; +https://laprevive.local)"
OUT = Path("data/crawled/competitor_products.json")


def fetch(url: str, timeout: int = 30) -> str:
    req = urllib.request.Request(url, headers={"User-Agent": UA, "Accept": "text/html,application/xml"})
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        raw = resp.read()
        charset = resp.headers.get_content_charset() or "utf-8"
        return raw.decode(charset, "ignore")


def locs(xml: str) -> list[str]:
    return [html.unescape(x.strip()) for x in re.findall(r"<loc>(.*?)</loc>", xml, flags=re.I | re.S)]


def strip_tags(text: str) -> str:
    text = re.sub(r"<script[\s\S]*?</script>|<style[\s\S]*?</style>", " ", text, flags=re.I)
    text = re.sub(r"<[^>]+>", " ", text)
    return re.sub(r"\s+", " ", html.unescape(text)).strip()


def meta(html_text: str, key: str) -> str:
    patterns = [
        rf'<meta[^>]+property=["\']{re.escape(key)}["\'][^>]+content=["\']([^"\']*)',
        rf'<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']{re.escape(key)}["\']',
        rf'<meta[^>]+name=["\']{re.escape(key)}["\'][^>]+content=["\']([^"\']*)',
        rf'<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']{re.escape(key)}["\']',
    ]
    for pattern in patterns:
        m = re.search(pattern, html_text, flags=re.I)
        if m:
            return html.unescape(m.group(1)).strip()
    return ""


def first_json_ld(html_text: str) -> list[dict]:
    out = []
    for m in re.finditer(r'<script[^>]+type=["\']application/ld\+json["\'][^>]*>([\s\S]*?)</script>', html_text, flags=re.I):
        raw = html.unescape(m.group(1)).strip()
        try:
            data = json.loads(raw)
        except Exception:
            continue
        out.append(data)
    return out


def find_product_jsonld(items: list[dict]) -> dict | None:
    stack = list(items)
    while stack:
        item = stack.pop(0)
        if isinstance(item, dict):
            typ = item.get("@type")
            if typ == "Product" or (isinstance(typ, list) and "Product" in typ):
                return item
            graph = item.get("@graph")
            if isinstance(graph, list):
                stack.extend(graph)
            for value in item.values():
                if isinstance(value, dict):
                    stack.append(value)
                elif isinstance(value, list):
                    stack.extend([x for x in value if isinstance(x, dict)])
    return None


def normalize_price(value) -> int | None:
    if value is None:
        return None
    text = str(value)
    nums = re.findall(r"\d+", text.replace(".", ""))
    if not nums:
        return None
    try:
        return int("".join(nums))
    except ValueError:
        return None


def extract_table_specs(html_text: str) -> dict[str, str]:
    specs = {}
    for row in re.findall(r"<tr[\s\S]*?</tr>", html_text, flags=re.I):
        cells = re.findall(r"<t[dh][^>]*>([\s\S]*?)</t[dh]>", row, flags=re.I)
        if len(cells) >= 2:
            key = strip_tags(cells[0]).strip().lower()
            value = strip_tags(cells[1]).strip()
            if not value:
                continue
            if key in {"cpu", "processor"}:
                specs["cpu"] = value
            elif key in {"ram", "memory"}:
                specs["ram"] = value
            elif key in {"ổ cứng", "ssd", "storage"}:
                specs["ssd"] = value
            elif key in {"màn hình", "display", "screen"}:
                specs["screen"] = value
            elif key in {"vga", "gpu", "card đồ họa"}:
                specs["gpu"] = value
            elif key in {"tình trạng", "condition"}:
                specs["condition"] = value
            elif key in {"bảo hành", "warranty"}:
                specs["warranty"] = value
    return specs


def extract_specs(text: str) -> dict[str, str]:
    specs = {}
    patterns = {
        "cpu": r"(?:CPU|Processor|Chip)\s*[:\-]?\s*([^\n\r|•]+?(?:i[3579]|Ryzen|Core|Intel|AMD)[^\n\r|•,.]*)",
        "ram": r"(?:RAM|Memory)\s*[:\-]?\s*([^\n\r|•,.]*(?:GB|DDR)[^\n\r|•,.]*)",
        "ssd": r"(?:SSD|Storage|Ổ cứng|Ổ Cứng)\s*[:\-]?\s*([^\n\r|•,.]*(?:GB|TB|NVMe|SATA)[^\n\r|•,.]*)",
        "screen": r"(?:Màn hình|Display|Screen)\s*[:\-]?\s*([^\n\r|•]*(?:inch|FHD|FullHD|2K|4K|Touch)[^\n\r|•]*)",
        "gpu": r"(?:VGA|GPU|Graphics|Card đồ họa)\s*[:\-]?\s*([^\n\r|•]+)",
    }
    for key, pattern in patterns.items():
        m = re.search(pattern, text, flags=re.I)
        if m:
            specs[key] = re.sub(r"\s+", " ", m.group(1)).strip(" :-|,.")[:180]
    return specs


def unique_images(html_text: str, base: str) -> list[str]:
    urls = []
    for m in re.finditer(r'https?://[^"\'<>\s]+\.(?:jpg|jpeg|png|webp)(?:\?[^"\'<>\s]*)?', html_text, flags=re.I):
        url = html.unescape(m.group(0))
        if any(skip in url.lower() for skip in ["logo", "favicon", "icon"]):
            continue
        urls.append(url)
    for m in re.finditer(r'(?:src|data-src|data-large_image)=["\']([^"\']+)["\']', html_text, flags=re.I):
        url = html.unescape(m.group(1))
        if not re.search(r"\.(jpg|jpeg|png|webp)(\?|$)", url, flags=re.I):
            continue
        urls.append(urllib.parse.urljoin(base, url))
    dedup = []
    seen = set()
    for url in urls:
        clean = url.split(" ")[0]
        if clean not in seen:
            seen.add(clean)
            dedup.append(clean)
    return dedup[:12]


def crawl_nextgold() -> list[dict]:
    sitemap = fetch("https://nextgold.vn/product-sitemap.xml")
    product_urls = [u for u in locs(sitemap) if u.rstrip("/") != "https://nextgold.vn/san-pham"]
    rows = []
    for index, url in enumerate(product_urls, 1):
        try:
            body = fetch(url)
            product_ld = find_product_jsonld(first_json_ld(body)) or {}
            title = product_ld.get("name") or meta(body, "og:title") or re.sub(r"[-|].*$", "", meta(body, "title"))
            desc = product_ld.get("description") or meta(body, "description") or meta(body, "og:description")
            offers = product_ld.get("offers") or {}
            if isinstance(offers, list):
                offers = offers[0] if offers else {}
            price = normalize_price(offers.get("price") or meta(body, "product:price:amount") or desc)
            text = strip_tags(body)
            images = unique_images(body, url)
            table_specs = extract_table_specs(body)
            fallback_specs = extract_specs(text + " " + desc + " " + title)
            rows.append({
                "source": "nextgold",
                "url": url,
                "title": title.replace(" - Công Nghệ Next Gold", "").strip(),
                "price": price,
                "description": desc,
                "images": images,
                "specs": {**fallback_specs, **table_specs},
                "status": "ok",
            })
        except Exception as exc:
            rows.append({"source": "nextgold", "url": url, "status": "error", "error": str(exc)})
        time.sleep(0.4)
        if index % 20 == 0:
            print(f"nextgold {index}/{len(product_urls)}", flush=True)
    return rows


def crawl_hienhuy() -> list[dict]:
    sitemap = fetch("https://hienhuy.vn/sitemaps/500650127/product1/product.xml")
    product_urls = locs(sitemap)
    rows = []
    for url in product_urls:
        rows.append({
            "source": "hienhuy",
            "url": url,
            "title": "",
            "price": None,
            "description": "",
            "images": [],
            "specs": {},
            "status": "needs_browser_or_kiotviet_api",
            "note": "Sitemap exposes product URLs, but static HTML contains generic KiotViet metadata. Product image/specs are rendered client-side or loaded from KiotViet APIs.",
        })
    print(f"hienhuy sitemap urls {len(product_urls)}", flush=True)
    return rows


def main() -> None:
    OUT.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "note": "Public metadata crawl. Review image rights before using on production.",
        "products": crawl_nextgold() + crawl_hienhuy(),
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"wrote {OUT} with {len(payload['products'])} records")


if __name__ == "__main__":
    main()
