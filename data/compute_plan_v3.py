#!/usr/bin/env python3
"""
Compute specs plan for Nhanh v3 products.
Parses structured data from content bullets (no carrier) + brandId lookup + demand inference.

Boss 2026-08-11: replaces specs-fix-2026-07-27/compute_plan.py + compute_battery.py.
Differences from v2:
- v3 has NO `<!--OSCAR:...-->` carrier → parse `• CPU:`, `• RAM:` bullets from content
- v3 has brandId + /v3.0/product/brand → lookup brand name
- v3 has description + content (v2 had desc + content)
- Now also writes categoryId + brandId for _nhanh_category_id / _nhanh_brand_id
"""
import json
import re
import sys
from collections import Counter

# Nhanh brandId → brand name (verified 2026-08-11 via /v3.0/product/brand)
BRAND_ID_TO_NAME = {
    1: 'Dell',
    2: 'HP',
    3: 'Lenovo',
    4: 'Apple',
    5: 'Microsoft',
    6: 'Intel',
}

# Brand prefix/substring → name (fallback for products without brandId)
BRAND_PREFIX_MAP = {
    'dell': 'Dell',
    'hp ': 'HP',
    'lenovo': 'Lenovo',
    'asus': 'Asus',
    'acer': 'Acer',
    'msi': 'MSI',
    'apple': 'Apple',
    'microsoft': 'Microsoft',
    'macbook': 'Apple',
}

# Name substring → brand (for products without explicit prefix)
BRAND_NAME_PATTERNS = [
    (r'\bthinkpad\b', 'Lenovo'),
    (r'\bideapad\b', 'Lenovo'),
    (r'\blenovo\b', 'Lenovo'),
    (r'\blatitude\b', 'Dell'),
    (r'\bxps\b', 'Dell'),
    (r'\binspiron\b', 'Dell'),
    (r'\bvostro\b', 'Dell'),
    (r'\bprecision\b', 'Dell'),
    (r'\belitebook\b', 'HP'),
    (r'\bprobook\b', 'HP'),
    (r'\bpavilion\b', 'HP'),
    (r'\benvy\b', 'HP'),
    (r'\bzbook\b', 'HP'),
    (r'\bomen\b', 'HP'),
    (r'\bvivobook\b', 'Asus'),
    (r'\bzenbook\b', 'Asus'),
    (r'\brog\b', 'Asus'),
    (r'\btuf\b', 'Asus'),
    (r'\bmacbook\b', 'Apple'),
    (r'\bsurface\b', 'Microsoft'),
]

# Demand inference by brand prefix (matches specs-fix-2026-07-27 logic)
DEMAND_INFER = {
    'precision':   'Render / Workstation',
    'thinkpad':    'Văn phòng',
    'latitude':    'Văn phòng',
    'xps':         'Đồ họa - Creator',
    'ideapad':     'Văn phòng',
    'inspiron':    'Văn phòng',
    'vostro':      'Văn phòng',
    'elitebook':   'Văn phòng',
    'probook':     'Văn phòng',
    'pavilion':    'Văn phòng',
    'vivobook':    'Sinh viên',
    'zenbook':     'Văn phòng',
    'rog':         'Gaming',
    'tuf':         'Gaming',
    'omen':        'Gaming',
    'legion':      'Gaming',
    'macbook':     'Đồ họa - Creator',
    'surface':     'Văn phòng',
    'yoga':        'Sinh viên',
}


def parse_bullet(content, key):
    """Parse `• KEY: value` from content."""
    pattern = rf'•\s*{re.escape(key)}\s*:\s*([^\n•]+)'
    m = re.search(pattern, content, re.IGNORECASE)
    if m:
        return m.group(1).strip()
    return ''


def parse_runtime(description, content):
    """Parse battery runtime `dùng X giờ` from description."""
    text = description + ' ' + content
    m = re.search(r'dùng\s+(\d+(?:\s*-\s*\d+)?)\s*giờ', text)
    if m:
        runtime = re.sub(r'\s+', ' ', m.group(1).strip())
        return runtime + ' giờ'
    return ''


def detect_brand(name, brand_id):
    """Brand from brandId (preferred) or name pattern (fallback)."""
    if brand_id and brand_id in BRAND_ID_TO_NAME:
        return BRAND_ID_TO_NAME[brand_id]
    n_lower = name.lower()
    for prefix, brand in BRAND_PREFIX_MAP.items():
        if n_lower.startswith(prefix):
            return brand
    for pattern, brand in BRAND_NAME_PATTERNS:
        if re.search(pattern, n_lower):
            return brand
    return ''


def infer_demand(name):
    """Infer demand category from product name."""
    lower = name.lower()
    for k, v in DEMAND_INFER.items():
        if k in lower:
            return v
    return 'Văn phòng'


def is_accessory(code, name):
    """Accessories (mouse, charger, etc.) — skip spec extraction."""
    if code.startswith('Mouse'):
        return True
    if 'chuột' in name.lower() or 'mouse' in name.lower():
        return True
    if 'sạc' in name.lower() or 'charger' in name.lower():
        return True
    return False


def parse_ssd(content, name):
    """Parse SSD bullet, fallback to name pattern."""
    raw = parse_bullet(content, 'Ổ cứng')
    if not raw:
        raw = parse_bullet(content, 'Storage')
    if not raw:
        raw = parse_bullet(content, 'SSD')
    if not raw:
        m = re.search(r'(\d+GB(?:\s+SSD)?|\d+TB(?:\s+SSD)?)', name, re.IGNORECASE)
        if m:
            raw = m.group(1)
    return raw.strip()


def main():
    if len(sys.argv) < 2:
        print("Usage: compute_plan_v3.py <nhanh-detail.jsonl> [output.json]")
        sys.exit(1)

    in_path = sys.argv[1]
    out_path = sys.argv[2] if len(sys.argv) > 2 else '/tmp/final_plan_v3.json'

    items = []
    with open(in_path) as f:
        for line in f:
            items.append(json.loads(line))

    plan = []
    for it in items:
        code = it.get('code', '')
        name = it.get('name', '')
        description = it.get('description', '') or ''
        content = it.get('content', '') or ''
        brand_id = it.get('brandId')

        if is_accessory(code, name):
            plan.append({
                'code': code,
                'name': name,
                'category_id': it.get('categoryId'),
                'brand_id': brand_id,
                'writes': {},
                'is_accessory': True,
            })
            continue

        cpu = parse_bullet(content, 'CPU')
        ram = parse_bullet(content, 'RAM')
        ssd = parse_ssd(content, name)
        screen = parse_bullet(content, 'Màn hình')
        gpu = parse_bullet(content, 'Card đồ họa')
        battery_str = parse_bullet(content, 'Pin')
        condition = parse_bullet(content, 'Tình trạng')
        warranty_str = parse_bullet(content, 'Bảo hành')

        # Battery Wh: extract number from "57Wh"
        battery_wh = ''
        if battery_str:
            m = re.match(r'(\d+(?:\.\d+)?)', battery_str)
            if m:
                battery_wh = str(int(float(m.group(1))))

        # Warranty months: extract number from "3 tháng"
        warranty_months = ''
        if warranty_str:
            m = re.match(r'(\d+)', warranty_str)
            if m:
                warranty_months = str(int(m.group(1)))

        runtime = parse_runtime(description, content)
        brand = detect_brand(name, brand_id)
        demand = infer_demand(name)

        plan.append({
            'code': code,
            'name': name,
            'category_id': it.get('categoryId'),
            'brand_id': brand_id,
            'writes': {
                '_oscar_brand':            brand,
                '_oscar_cpu':              cpu,
                '_oscar_ram':              ram,
                '_oscar_ssd':              ssd,
                '_oscar_screen':           screen,
                '_oscar_gpu':              gpu,
                '_oscar_battery_wh':       battery_wh,
                '_oscar_battery_runtime':  runtime,
                '_oscar_demand':           demand,
                '_oscar_condition_vi':     condition,
                '_oscar_warranty_months':  warranty_months,
            },
        })

    # Coverage stats
    field_cov = Counter()
    skipped_accessory = sum(1 for p in plan if p.get('is_accessory'))
    real_products = [p for p in plan if not p.get('is_accessory')]

    for p in real_products:
        for k, v in p['writes'].items():
            if v:
                field_cov[k] += 1

    print(f"=== Compute plan v3 ===")
    print(f"Total products: {len(items)}")
    print(f"Accessories (skipped): {skipped_accessory}")
    print(f"Real products: {len(real_products)}")
    print()
    print(f"Field coverage:")
    for k in ['_oscar_brand', '_oscar_cpu', '_oscar_ram', '_oscar_ssd', '_oscar_screen',
              '_oscar_gpu', '_oscar_battery_wh', '_oscar_battery_runtime',
              '_oscar_demand', '_oscar_condition_vi', '_oscar_warranty_months']:
        print(f"  {k:30s}: {field_cov[k]}/{len(real_products)}")

    demand_dist = Counter(p['writes']['_oscar_demand'] for p in real_products)
    print(f"\nDemand distribution:")
    for k, n in demand_dist.most_common():
        print(f"  {k}: {n}")

    print(f"\n=== 2 sample plan entries ===")
    for p in real_products[:2]:
        print(f"\n  {p['code']}: {p['name'][:60]}")
        print(f"    category_id: {p['category_id']}, brand_id: {p['brand_id']}")
        for k, v in p['writes'].items():
            print(f"    {k}: {v or '(empty)'}")

    with open(out_path, 'w', encoding='utf-8') as f:
        json.dump(plan, f, ensure_ascii=False, indent=2)
    print(f"\nSaved to {out_path}")


if __name__ == '__main__':
    main()